<?php

/**
 * This extensions add a default schema for new pages and pages without a content review setting
 * 
 */
class ContentReviewDefaultSettings extends DataExtension {
	
	/**
	 *
	 * @var array
	 */
	private static $db = array(
		"ReviewPeriodDays" => "Int",
	);
	
	/**
	 *
	 * @var array
	 */
	private static $many_many = array(
		'ContentReviewGroups' => 'Group',
		'ContentReviewUsers' => 'Member'
	);
	
	/**
	 * @return ManyManyList
	 */
	public function OwnerGroups() {
		return $this->owner->getManyManyComponents('ContentReviewGroups');
	}
	
	/**
	 * @return ManyManyList
	 */
	public function OwnerUsers() {
		return $this->owner->getManyManyComponents('ContentReviewUsers');
	}
	
	/**
	 * 
	 * @param \FieldList $fields
	 */
	public function updateCMSFields(\FieldList $fields) {
		
		$helpText = LiteralField::create('ContentReviewHelp', _t('ContentReview.DEFAULTSETTINGSHELP', 'These content review '
			. 'settings will apply to all pages that does not have specific Content Review schedule.'));
		$fields->addFieldToTab('Root.ContentReview', $helpText);
		
		$reviewFrequency = DropdownField::create("ReviewPeriodDays", _t("ContentReview.REVIEWFREQUENCY", "Review frequency"), SiteTreeContentReview::get_schedule())
			->setDescription(_t('ContentReview.REVIEWFREQUENCYDESCRIPTION', 'The review date will be set to this far in the future whenever the page is published'));
		
		$fields->addFieldToTab('Root.ContentReview', $reviewFrequency);
		
		$users = Permission::get_members_by_permission(array("CMS_ACCESS_CMSMain", "ADMIN"));
		
		$usersMap = $users->map('ID', 'Title')->toArray();
		asort($usersMap);
		
		$userField = ListboxField::create('OwnerUsers', _t("ContentReview.PAGEOWNERUSERS", "Users"), $usersMap)
			->setMultiple(true)
			->setAttribute('data-placeholder', _t('ContentReview.ADDUSERS', 'Add users'))
			->setDescription(_t('ContentReview.OWNERUSERSDESCRIPTION', 'Page owners that are responsible for reviews'));
		$fields->addFieldToTab('Root.ContentReview', $userField);
		
		$groupsMap = array();
		foreach(Group::get() as $group) {
			// Listboxfield values are escaped, use ASCII char instead of &raquo;
			$groupsMap[$group->ID] = $group->getBreadcrumbs(' > ');
		}
		asort($groupsMap);
		
		$groupField = ListboxField::create('OwnerGroups', _t("ContentReview.PAGEOWNERGROUPS", "Groups"), $groupsMap)
			->setMultiple(true)
			->setAttribute('data-placeholder', _t('ContentReview.ADDGROUP', 'Add groups'))
			->setDescription(_t('ContentReview.OWNERGROUPSDESCRIPTION', 'Page owners that are responsible for reviews'));
		$fields->addFieldToTab('Root.ContentReview', $groupField);
	}
	
	/**
	 * Get all Members that are default Content Owners 
	 * 
	 * This includes checking group hierarchy and adding any direct users
	 * 
	 * @return \ArrayList
	 */
	public function ContentReviewOwners() {
		$contentReviewOwners = new ArrayList();
		$toplevelGroups = $this->OwnerGroups();
		if($toplevelGroups->count()) {
			$groupIDs = array();
			foreach($toplevelGroups as $group) {
				$familyIDs = $group->collateFamilyIDs();
				if(is_array($familyIDs)) {
					$groupIDs = array_merge($groupIDs, array_values($familyIDs));
				}
			}
			array_unique($groupIDs);
			if(count($groupIDs)) {
				$groupMembers = DataObject::get('Member')->where("\"Group\".\"ID\" IN (" . implode(",",$groupIDs) . ")")
				->leftJoin("Group_Members", "\"Member\".\"ID\" = \"Group_Members\".\"MemberID\"")
				->leftJoin("Group", "\"Group_Members\".\"GroupID\" = \"Group\".\"ID\"");
				$contentReviewOwners->merge($groupMembers);
			}
		}
		$contentReviewOwners->merge($this->OwnerUsers());
		$contentReviewOwners->removeDuplicates();
		return $contentReviewOwners;
	}
}
