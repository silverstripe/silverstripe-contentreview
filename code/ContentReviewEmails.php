<?php

/**
 * Daily task to send emails to the owners of content items
 * when the review date rolls around
 *
 * @package contentreview
 */
class ContentReviewEmails extends BuildTask {

	/**
	 * 
	 * @param SS_HTTPRequest $request
	 */
	public function run($request) {
		// Disable subsite filter (if installed)
		if (ClassInfo::exists('Subsite')) {
			$oldSubsiteState = Subsite::$disable_subsite_filter;
			Subsite::$disable_subsite_filter = true;
		}
		
		$now = class_exists('SS_Datetime') ? SS_Datetime::now()->URLDate() : SSDatetime::now()->URLDate();
		
		$pages = Page::get('Page')
			->leftJoin('Group_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerGroups"."SiteTreeID"', 'OwnerGroups')
			->leftJoin('Member_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerUsers"."SiteTreeID"', "OwnerUsers")
			->where('"SiteTree"."NextReviewDate" <= \''.$now.'\' AND' .' ("OwnerGroups"."ID" IS NOT NULL OR "OwnerUsers"."ID" IS NOT NULL)')
		;
		
		if ($pages && $pages->Count()) {
			foreach($pages as $page) {
				$owners = $page->ContentReviewOwners();
				if(!$owners->count()) {
					continue;
				}
				$sender = Security::findAnAdministrator();
				
				foreach($owners as $recipient) { 
					$subject = sprintf(_t('ContentReviewEmails.SUBJECT', 'Page %s due for content review'), $page->Title);

					$email = new Email();
					$email->setTo($recipient->Email);
					$email->setFrom(($sender->Email) ? $sender->Email : Email::getAdminEmail());
					$email->setTemplate('ContentReviewEmails');
					$email->setSubject($subject);
					$email->populateTemplate(array(
						"PageCMSLink" => "admin/pages/edit/show/".$page->ID,
						"Recipient" => $recipient,
						"Sender" => $sender,
						"Page" => $page,
						"StageSiteLink"	=> Controller::join_links($page->Link(), "?stage=Stage"),
						"LiveSiteLink"	=> Controller::join_links($page->Link(), "?stage=Live"),
					));
					$email->send();
				}
			}
		}
		
		// Revert subsite filter (if installed)
		if (ClassInfo::exists('Subsite')) {
			Subsite::$disable_subsite_filter = $oldSubsiteState;
		}
	}
}
