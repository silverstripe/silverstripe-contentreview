<?php

namespace SilverStripe\ContentReview\Tasks;

use Page;
use RuntimeException;
use SilverStripe\ContentReview\Compatibility\ContentReviewCompatability;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * Daily task to send emails to the owners of content items when the review date rolls around.
 */
class ContentReviewEmails extends BuildTask
{
    private array $invalid_emails = [];

    /**
     * @param HTTPRequest $request
     * @throws RuntimeException
     */
    public function run($request)
    {
        if (!$this->isValidEmail($senderEmail = SiteConfig::current_site_config()->ReviewFrom)) {
            throw new RuntimeException(
                sprintf(
                    'Provided sender email address is invalid: "%s".',
                    $senderEmail
                )
            );
        }

        $compatibility = ContentReviewCompatability::start();

        // First grab all the pages with a custom setting
        $pages = Page::get()
            ->filter('NextReviewDate:LessThanOrEqual', DBDatetime::now()->URLDate());

        $overduePages = $this->getOverduePagesForOwners($pages);

        // Lets send one email to one owner with all the pages in there instead of no of pages
        // of emails.
        foreach ($overduePages as $memberID => $pages) {
            $this->notifyOwner($memberID, $pages);
        }

        ContentReviewCompatability::done($compatibility);

        if (is_array($this->invalid_emails) && count($this->invalid_emails) > 0) {
            $plural = count($this->invalid_emails) > 1 ? 's are' : ' is';
            throw new RuntimeException(
                sprintf(
                    'Provided email' . $plural . ' invalid: "%s".',
                    implode(', ', $this->invalid_emails)
                )
            );
        }
    }

    /**
     * @param SS_List $pages
     *
     * @return array
     */
    protected function getOverduePagesForOwners(SS_List $pages)
    {
        $overduePages = [];

        foreach ($pages as $page) {
            if (!$page->canBeReviewedBy()) {
                continue;
            }

            // get most recent review log of current [age]
            $contentReviewLog = $page->ReviewLogs()->sort('Created DESC')->first();

            // check log date vs NextReviewDate. If someone has left a content review
            // after the review date, then we don't need to notify anybody
            if ($contentReviewLog && $contentReviewLog->Created >= $page->NextReviewDate) {
                $page->advanceReviewDate();
                continue;
            }

            $options = $page->getOptions();

            if ($options) {
                foreach ($options->ContentReviewOwners() as $owner) {
                    if (!isset($overduePages[$owner->ID])) {
                        $overduePages[$owner->ID] = ArrayList::create();
                    }

                    $overduePages[$owner->ID]->push($page);
                }
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

        if (!$this->isValidEmail($owner->Email)) {
            $this->invalid_emails[] = $owner->Name . ': ' . $owner->Email;

            return;
        }

        $templateVariables = $this->getTemplateVariables($owner, $siteConfig, $pages);

        // Build email
        $email = Email::create();
        $email->setTo($owner->Email);
        $email->setFrom($siteConfig->ReviewFrom);
        $email->setSubject($siteConfig->ReviewSubject);

        // Get user-editable body
        $body = $this->getEmailBody($siteConfig, $templateVariables);

        // Populate mail body with fixed template
        $email->setHTMLTemplate($siteConfig->config()->get('content_review_template'));
        $email->setData(
            array_merge(
                $templateVariables,
                [
                    'EmailBody' => $body,
                    'Recipient' => $owner,
                    'Pages' => $pages,
                ]
            )
        );
        $email->send();
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
        $value = $template->process(ArrayData::create($variables));

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
        return [
            'Subject' => $config->ReviewSubject,
            'PagesCount' => $pages->count(),
            'FromEmail' => $config->ReviewFrom,
            'ToFirstName' => $recipient->FirstName,
            'ToSurname' => $recipient->Surname,
            'ToEmail' => $recipient->Email,
        ];
    }

    /**
     * Check validity of email
     */
    protected function isValidEmail(?string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
