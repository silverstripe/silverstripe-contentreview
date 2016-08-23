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

        // for each of these pages, check if today is the date the First or Second reminder should be sent
        foreach ($pendingPages as $page) {
            $notifyDate1 = date('Y-m-d', strtotime($page->NextReviewDate . ' -' . $firstReview . ' day'));
            $notifyDate2 = date('Y-m-d', strtotime($page->NextReviewDate . ' -' . $secondReview . ' day'));
        }

        die();

        $overduePages = $this->getOverduePagesForOwners($pages);

        // Lets send one email to one owner with all the pages in there instead of no of pages
        // of emails.
        foreach ($overduePages as $memberID => $pages) {
            $this->notifyOwner($memberID, $pages);
        }

        ContentReviewCompatability::done($compatibility);
    }

    /**
     * @param SS_list $pages
     *
     * @return array
     */
    protected function getOverduePagesForOwners(SS_list $pages)
    {
        $overduePages = array();

        foreach ($pages as $page) {
            if (!$page->canBeReviewedBy()) {
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

    /**
     * @param int           $ownerID
     * @param array|SS_List $pages
     */
    protected function notifyOwner($ownerID, SS_List $pages)
    {
        // Prepare variables
        $siteConfig = SiteConfig::current_site_config();
        $owner = Member::get()->byID($ownerID);
        $templateVariables = $this->getTemplateVariables($owner, $siteConfig, $pages);

        // Build email
        $email = new Email();
        $email->setTo($owner->Email);
        $email->setFrom($siteConfig->ReviewFrom);
        $email->setSubject($siteConfig->ReviewSubject);

        // Get user-editable body
        $body = $this->getEmailBody($siteConfig, $templateVariables);

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
    protected function getEmailBody($config, $variables)
    {
        $template = SSViewer::fromString($config->ReviewBody);
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
     *
     * @return array
     */
    protected function getTemplateVariables($recipient, $config, $pages)
    {
        return array(
            'Subject' => $config->ReviewSubject,
            'PagesCount' => $pages->count(),
            'FromEmail' => $config->ReviewFrom,
            'ToFirstName' => $recipient->FirstName,
            'ToSurname' => $recipient->Surname,
            'ToEmail' => $recipient->Email,
        );
    }
}
