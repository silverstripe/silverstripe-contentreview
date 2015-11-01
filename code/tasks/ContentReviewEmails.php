<?php

/**
 * Daily task to send emails to the owners of content items when the review date rolls around.
 */
class ContentReviewEmails extends BuildTask
{
    /**
     * Holds a cached array for looking up members via their ID.
     *
     * @var array
     */
    protected static $member_cache = array();

    /**
     * @param SS_HTTPRequest $request
     */
    public function run($request)
    {
        $compatibility = ContentReviewCompatability::start();

        $now = class_exists("SS_Datetime") ? SS_Datetime::now()->URLDate() : SSDatetime::now()->URLDate();

        // First grab all the pages with a custom setting
        $pages = Page::get("Page")->where("\"SiteTree\".\"NextReviewDate\" <= '{$now}'");

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
                if (!isset(self::$member_cache[$owner->ID])) {
                    self::$member_cache[$owner->ID] = $owner;
                }

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
        $owner = self::$member_cache[$ownerID];
        $sender = Security::findAnAdministrator();
        $senderEmail = ($sender->Email) ? $sender->Email : Config::inst()->get("Email", "admin_email");

        $subject = _t("ContentReviewEmails.SUBJECT", "Page(s) are due for content review");
        $email = new Email();
        $email->setTo($owner->Email);
        $email->setFrom($senderEmail);
        $email->setTemplate("ContentReviewEmail");
        $email->setSubject($subject);
        $email->populateTemplate(array(
            "Recipient" => $owner,
            "Sender"    => $sender,
            "Pages"     => $pages,
        ));

        $email->send();
    }
}
