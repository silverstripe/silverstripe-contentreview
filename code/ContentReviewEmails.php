<?php

/**
 * Daily task to send emails to the owners of content items
 * when the review date rolls around
 *
 * 
 * @todo create a page cache for the inherited so that we dont unneccesary need to look up parent pages
 * @package contentreview
 */
class ContentReviewEmails extends BuildTask {

	/**
	 * Holds a cached array for looking up members via their ID
	 *
	 * @var array
	 */
	protected static $member_cache = array();
	
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
		
		$overduePages = array();
		
		$now = class_exists('SS_Datetime') ? SS_Datetime::now()->URLDate() : SSDatetime::now()->URLDate();
		
		// First grab all the pages with a custom setting
		//$customSettingsPages = Page::get('Page')
		//	->leftJoin('Group_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerGroups"."SiteTreeID"', 'OwnerGroups')
		//	->leftJoin('Member_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerUsers"."SiteTreeID"', "OwnerUsers")
		//	->where('"SiteTree"."ContentReviewType" = \'Custom\' AND "SiteTree"."NextReviewDate" <= \''.$now.'\' AND' .
		//			' ("OwnerGroups"."ID" IS NOT NULL OR "OwnerUsers"."ID" IS NOT NULL)')
		//;
		
		//$this->getOverduePagesForOwners($customSettingsPages, $overduePages);
		
		// Then grab all the pages with that inherits their settings
		//$inheritedSettingsPages = Page::get('Page')
		//	->leftJoin('Group_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerGroups"."SiteTreeID"', 'OwnerGroups')
		//	->leftJoin('Member_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerUsers"."SiteTreeID"', "OwnerUsers")
		//	->where('"SiteTree"."ContentReviewType" = \'Inherit\'')
		//;
		
		
		$pages = Page::get();
		$this->getOverduePagesForOwners($pages, $overduePages);
		
		// Lets send one email to one owner with all the pages in there instead of no of pages of emails
		foreach($overduePages as $memberID => $pages) {
			$this->notifyOwner($memberID, $pages);
		}
		
		// Revert subsite filter (if installed)
		if(ClassInfo::exists('Subsite')) {
			Subsite::$disable_subsite_filter = $oldSubsiteState;
		}
	}
	
	/**
	 * 
	 * @param SS_list $pages
	 * @param array &$pages
	 * @return array
	 */
	protected function getOverduePagesForOwners(SS_list $pages, array &$overduePages) {
		foreach($pages as $page) {
			
			// Update the NextReviewDate cache for this page
			//$page->updateNextReviewDate($forceWrite = true);
			
			if(!$page->isContentReviewOverdue()) {
				continue;
			}
			
			$settings = SiteTreeContentReview::get_options($page);
			foreach($settings->ContentReviewOwners() as $owner) {
				if(!isset(self::$member_cache[$owner->ID])) {
					self::$member_cache[$owner->ID] = $owner;
				}
				if(!isset($overduePages[$owner->ID])) {
					$overduePages[$owner->ID] = array();
				}
				$overduePages[$owner->ID][] = $page;
			}
		}
		return $overduePages;
	}
	
	/**
	 * 
	 * @param int $owner
	 * @param array $pages
	 */
	protected function notifyOwner($ownerID, array $pages) {
		$owner = self::$member_cache[$ownerID];
		echo "----- ".$owner->Email." -----".PHP_EOL;
			foreach($pages as $page) {
				echo $page->Title.PHP_EOL;
			}
	}
	
	/**
	 * 
	 * @param SS_List $pages
	 * @return void
	 */
	protected function notify(SS_List $pages) {
		if(!$pages) {
			return;
		}
		if(!$pages->Count()) {
			return;
		}
		
		foreach($pages as $page) {
			// Resolve the content owner groups and members to a single list of members
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
				//$email->send();
				$message = '<strong>'._t('ContentReviewEmails.EMAIL_HEADING','Page due for review').'</strong><br/>'.
					'The page "'.$page->Title.'" is due for review today by you.<br/>
					<a href="admin/pages/edit/show/'.$page->ID.'">'. _t('ContentReviewEmails.REVIEWPAGELINK','Review the page in the CMS') .'</a> &mdash;
					<a href="#">'. _t('ContentReviewEmails.VIEWPUBLISHEDLINK','View this page on the website') .'</a>';
				if(class_exists('Notification')) {
				//	Notification::notify($recipient, $message);
				}
			//	echo $page->Title.' - '.$recipient->Email.PHP_EOL;
			}
		}
	}
}
