<?php

Class ContentReview extends Object {
	
	/**
	 * Get the object that have the information about the content
	 * review settings
	 * 
	 * Will go through parents and root pages will use the siteconfig 
	 * if their setting is Inherit.
	 * 
	 * @param SiteTree $page
	 * @return DataObject or false if no settings found
	 */
	public function getContentReviewSetting($page) {
		if($page->ContentReviewType == 'Custom') {
			return $page;
		}
		if($page->ContentReviewType == 'Disabled') {
			return false;
		}
		
		// $page is inheriting it's settings from it's parent, find
		// the first valid parent with a valid setting
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
		throw new Exception('This shouldn\'t really happen, as per usual developer logic.');
	}
	
}
