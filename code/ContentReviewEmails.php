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
		
		$now = class_exists('SS_Datetime') ? SS_Datetime::now()->URLDate() : SSDatetime::now()->URLDate();
		
		// First grab all the pages with a custom setting
		$pages = Page::get('Page')
			->leftJoin('Group_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerGroups"."SiteTreeID"', 'OwnerGroups')
			->leftJoin('Member_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerUsers"."SiteTreeID"', "OwnerUsers")
			->where('"SiteTree"."ContentReviewType" != \'Custom\' AND "SiteTree"."NextReviewDate" <= \''.$now.'\' AND' .
					' ("OwnerGroups"."ID" IS NOT NULL OR "OwnerUsers"."ID" IS NOT NULL)')
		;
		
		$this->notify($pages);
		
		// Then grab all the pages with that inherits their settings
		
		$pages = Page::get('Page')
			->leftJoin('Group_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerGroups"."SiteTreeID"', 'OwnerGroups')
			->leftJoin('Member_SiteTreeContentReview', '"SiteTree"."ID" = "OwnerUsers"."SiteTreeID"', "OwnerUsers")
			->where('"SiteTree"."ContentReviewType" = \'Inherit\'')
		;
		
		$overduePages = $this->findInheritedSettings($pages);
		
		// Lets send one email to one owner with all the pages in there instead of no of pages of emails
		foreach($overduePages as $memberID => $pages) {
			$this->notify_user($memberID, $pages);
		}
		
		// Revert subsite filter (if installed)
		if(ClassInfo::exists('Subsite')) {
			Subsite::$disable_subsite_filter = $oldSubsiteState;
		}
	}
	
	/**
	 * 
	 * @param SS_list $pages
	 * @return type
	 */
	protected function findInheritedSettings(SS_list $pages) {
		$overduePages = array();
		
		foreach($pages as $page) {
			
			$settings = $this->findContentSettingFor($page);
			// This page has a parent with the 'Disabled' option
			if(!$settings) {
				continue;
			}
			
			$owners = $settings->ContentReviewOwners();
			if(!$owners->count()) {
				continue;
			}
			if(!$settings->ReviewPeriodDays) {
				continue;
			}
			// Calculate next time this page should be reviewed from the LastEdited datea
			$nextReviewUnixSec = strtotime($page->LastEdited . ' + '.$settings->ReviewPeriodDays . ' days');
			if($nextReviewUnixSec > time()) {
				continue;
			}
			
			foreach($owners as $owner) {
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
	 * @param SiteTree $page
	 * @return DataObject or false if no settings found
	 */
	protected function findContentSettingFor($page) {
		while($parent = $page->Parent()) {
			// Root page, use siteconfig
			if(!$parent->exists()) {
				return SiteConfig::current_site_config();
			}
			if($parent->ContentReviewType == 'Custom') {
				return $parent;
			}
			if($parent->ContentReviewType == 'Disabled') {
				return false;
			}
			$page = $parent;
		}
		throw new Exception('This shouldnt really happen, as usual.');
	}
	
	/**
	 * 
	 * @param int $owner
	 * @param array $pages
	 */
	protected function notify_user($ownerID, array $pages) {
		$owner = self::$member_cache[$ownerID];
		echo $owner->Email.PHP_EOL;
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
