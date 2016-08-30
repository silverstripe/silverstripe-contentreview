<?php

/**
 * Daily task to send emails to the owners of content items when the review date rolls around.
 */
class ContentReviewEmails extends BuildTask
{
    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        $compatibility = ContentReviewCompatability::start();

        $now = SS_Datetime::now();

        // First grab all the pages with a custom setting
        $pages = Page::get()
            ->filter('NextReviewDate:LessThanOrEqual', $now->URLDate());



        // Calculate whether today is the date a First or Second review should occur
        $config = SiteConfig::current_site_config();
        $firstReview = $config->FirstReviewDaysBefore;
        $secondReview = $config->SecondReviewDaysBefore;
        // Subtract the number of days prior to the review, from the current date

        // Get all pages where the NextReviewDate is still in the future
        $pendingPages = Page::get()->filter('NextReviewDate:GreaterThan', $now->URLDate());

        // for each of these pages, check if today is the date the First or Second
        // reminder should be sent, and if so, add it to the appropriate ArrayList
        $firstReminderPages = new ArrayList();
        $secondReminderPages = new ArrayList();

        foreach ($pendingPages as $page) {
            $notifyDate1 = date('Y-m-d', strtotime($page->NextReviewDate . ' -' . $firstReview . ' days'));
            $notifyDate2 = date('Y-m-d', strtotime($page->NextReviewDate . ' -' . $secondReview . ' days'));

            if ($notifyDate1 == $now->URLDate()) {
                $firstReminderPages->push($page);
            }
            if ($notifyDate2 == $now->URLDate()) {
                $secondReminderPages->push($page);
            }
        }





Debug::show('====================================================================FIRST REMINDER');
        foreach ($firstReminderPages as $p) {
            Debug::show($p->Title);
        }
Debug::show('====================================================================SECOND REMINDER');
        foreach ($secondReminderPages as $p) {
            Debug::show($p->Title);
        }
Debug::show('====================================================================DUE/OVERDUE');
        foreach ($pages as $p) {
            Debug::show($p->Title);
        }
        //die();



        $overduePages = $this->getNotifiablePagesForOwners($pages);

        // Send one email to one owner with all the pages in there instead of no of pages of emails.
        foreach ($overduePages as $memberID => $pages) {
            //$this->notifyOwner($memberID, $pages, "due");
        }

        // Send an email to the generic address with any first or second reminders
        $this->notifyTeam($firstReminderPages, $secondReminderPages);
        die();

        ContentReviewCompatability::done($compatibility);
    }

    /**
     * @param SS_list $pages
     *
     * @return array
     */
    protected function getNotifiablePagesForOwners(SS_list $pages)
    {
        $overduePages = array();

        foreach ($pages as $page) {

            if (!$page->canRemind()) {
                continue;
            }
            
            $option = $page->getOptions();

            foreach ($option->ContentReviewOwners() as $owner) {
                if (!isset($overduePages[$owner->ID])) {
                    $overduePages[$owner->ID] = new ArrayList();
                }

                $overduePages[$owner->ID]->push($page);
            }
        }

        return $overduePages;
    }
    
    protected function notifyTeam($firstReminderPages, $secondReminderPages) {
        // Prepare variables
        $siteConfig = SiteConfig::current_site_config();
        $templateVariables1 = $this->getTemplateVariables('', $siteConfig, $firstReminderPages, 'reminder1');
        $templateVariables2 = $this->getTemplateVariables('', $siteConfig, $secondReminderPages, 'reminder2');

        // Build email
        $email = new Email();
        $email->setTo($siteConfig->Email);
        $email->setFrom($siteConfig->ReviewFrom);

        $subject = $siteConfig->ReviewSubject;
        $email->setSubject($subject);




        // Get user-editable body
        $bodyFirstReminder = $this->getEmailBody($siteConfig, $templateVariables1, 'reminder1');
        $bodySecondReminder = $this->getEmailBody($siteConfig, $templateVariables2, 'reminder2');

        // Populate mail body with fixed template
        $email->setTemplate($siteConfig->config()->content_review_reminder_template);
        $email->populateTemplate($templateVariables1, $templateVariables2);
        $email->populateTemplate(array(
            'EmailBodyFirstReminder' => $bodyFirstReminder,
            'EmailBodySecondReminder' => $bodySecondReminder,
            'Recipient' => $siteConfig->ReviewReminderEmail,
            'FirstReminderPages' => $firstReminderPages,
            'SecondReminderPages' => $secondReminderPages,
        ));

        Debug::show($email);
        //$email->send();



    }

    /**
     * @param int           $ownerID
     * @param array|SS_List $pages
     * @param string        $type
     */
    protected function notifyOwner($ownerID, SS_List $pages, $type)
    {
        // Prepare variables
        $siteConfig = SiteConfig::current_site_config();
        $owner = Member::get()->byID($ownerID);
        $templateVariables = $this->getTemplateVariables($owner, $siteConfig, $pages, $type);

        // Build email
        $email = new Email();
        $email->setTo($owner->Email);
        $email->setFrom($siteConfig->ReviewFrom);

        $subject = $siteConfig->ReviewSubject;
        $email->setSubject($subject);

        // Get user-editable body
        $body = $this->getEmailBody($siteConfig, $templateVariables, $type);

        // Populate mail body with fixed template
        $email->setTemplate($siteConfig->config()->content_review_template);
        $email->populateTemplate($templateVariables);
        $email->populateTemplate(array(
            'EmailBody' => $body,
            'Recipient' => $owner,
            'Pages' => $pages,
        ));

        Debug::show($email);
        //$email->send();
    }

    /**
     * Get string value of HTML body with all variable evaluated.
     *
     * @param SiteConfig $config
     * @param array List of safe template variables to expose to this template
     *
     * @return HTMLText
     */
    protected function getEmailBody($config, $variables, $type)
    {
        if ($type == "reminder1") {
            $template = SSViewer::fromString($config->ReviewBodyFirstReminder);
        }
        if ($type == "reminder2") {
            $template = SSViewer::fromString($config->ReviewBodySecondReminder);
        }
        if ($type == "due") {
            $template = SSViewer::fromString($config->ReviewBody);
        }
        
        $value = $template->process(new ArrayData($variables));

        // Cast to HTML
        return DBField::create_field('HTMLText', (string) $value);
    }

    /**
     * Gets list of safe template variables and their values which can be used
     * in both the static and editable templates.
     *
     * {@see ContentReviewAdminHelp.ss}
     *
     * @param Member     $recipient
     * @param SiteConfig $config
     * @param SS_List    $pages
     * @param string     $type
     *
     * @return array
     */
    protected function getTemplateVariables($recipient = null, $config, $pages)
    {
        if ($recipient != null) {
            return array(
                'Subject' => $config->ReviewSubject,
                'PagesCount' => $pages->count(),
                'FromEmail' => $config->ReviewFrom,
                'ToFirstName' => $recipient->FirstName,
                'ToSurname' => $recipient->Surname,
                'ToEmail' => $recipient->Email
            );
        } else {
            return array(
                'Subject' => $config->ReviewSubjectReminder,
                'FirstReminderPagesCount' => $pages->count(),
                'SecondReminderPagesCount' => $pages->count(),
                'FromEmail' => $config->ReviewFrom,
                'ToEmail' => $config->ReviewReminderEmail
            );

        }



    }
}
