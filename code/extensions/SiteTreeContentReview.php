<?php
/**
 * Set dates at which content needs to be reviewed and provide
 * a report and emails to alert to content needing review
 *
 * @package contentreview
 */
class SiteTreeContentReview extends DataExtension implements PermissionProvider {

	/**
	 *
	 * @var array
	 */
	private static $db = array(
		"ContentReviewType" => "Enum('Inherit, Disabled, Custom', 'Inherit')",
		"ReviewPeriodDays" => "Int",
		"NextReviewDate" => "Date",
		'LastEditedByName' => 'Varchar(255)',
		'OwnerNames' => 'Varchar(255)'
	);

	
	/**
	 *
	 * @var array
	 */
	private static $defaults = array(
		'ContentReviewType' => 'Inherit'
	);
	
	/**
	 *
	 * @var array
	 */
	private static $has_many = array(
		'ReviewLogs' => 'ContentReviewLog' 
	);

	/**
	 *
	 * @var array
	 */
	private static $belongs_many_many = array(
		'ContentReviewGroups' => 'Group',
		'ContentReviewUsers' => 'Member'
	);
	
	/**
	 *
	 * @var array
	 */
	private static $schedule = array(
		0 => "No automatic review date",
		1 => "1 day",
		7 => "1 week",
		30 => "1 month",
		60 => "2 months",
		91 => "3 months",
		121 => "4 months",
		152 => "5 months",
		183 => "6 months",
		365 => "12 months",
	);
	
	/**
	 * @return array
	 */
	public static function get_schedule() {
		return self::$schedule;
	}
	
	/**
	 * 
	 * @param \FieldList $actions
	 */
	public function updateCMSActions(\FieldList $actions) {
		if($this->canBeReviewedBy(Member::currentUser())) {
			$reviewAction = FormAction::create('reviewed', _t('ContentReview.BUTTONREVIEWED', 'Content reviewed'))
				->setAttribute('data-icon', 'pencil')
				->setAttribute('data-text-alternate', _t('ContentReview.BUTTONREVIEWED', 'Content reviewed'));
			$actions->push($reviewAction);
		}
	}
	
	/**
	 * 
	 * @param SiteTree $page
	 * @return Date | false - returns false if the content review have disabled
	 */
	public function getReviewDate(SiteTree $page=null) {
		if($page === null) {
			$page = $this->owner;
		}
		if($page->obj('NextReviewDate')->exists()) {
			return $page->obj('NextReviewDate');
		}
		
		$options = $this->owner->getOptions();
		
		if(!$options) {
			return false;
		}
		if(!$options->ReviewPeriodDays) {
			return false;
		}
		// Failover to check on ReviewPeriodDays + LastEdited
		$nextReviewUnixSec = strtotime(' + '.$options->ReviewPeriodDays . ' days', SS_Datetime::now()->format('U'));
		$date = Date::create('NextReviewDate');
		$date->setValue(date('Y-m-d H:i:s', $nextReviewUnixSec));
		return $date;
	}
	
	/**
	 * Get the object that have the information about the content
	 * review settings. Either 
	 *  - a SiteTreeContentReview decorated object
	 *  - the default SiteTree config 
	 *  - false if this page have it's content review disabled
	 * 
	 * Will go through parents and root pages will use the siteconfig 
	 * if their setting is Inherit.
	 * 
	 * @return DataObject or false if no settings found
	 */
	public function getOptions() {
		if($this->owner->ContentReviewType == 'Custom') {
			return $this->owner;
		}
		if($this->owner->ContentReviewType == 'Disabled') {
			return false;
		}
		
		$page = $this->owner;
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
	
	/**
	 * 
	 * @return string
	 */
	public function getOwnerNames() {
		$options = $this->getOptions();
		$names = array();
		if(!$options) {
			return '';
		}
		foreach($options->OwnerGroups() as $group) {
			$names[] = $group->getBreadcrumbs(' > ');
		}
		
		foreach($options->OwnerUsers() as $group) {
			$names[] = $group->getName();
		}
		return implode(', ', $names);
	}

	/**
	 * 
	 * @return string
	 */
	public function getEditorName() {
		$member = Member::currentUser();
		if($member) {
			 return $member->FirstName .' '. $member->Surname;
		}
		return null;
	}
	
	/**
	 * Get all Members that are Content Owners to this page
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
	 * @param FieldList $fields
	 * @return void
	 */
	public function updateSettingsFields(FieldList $fields) {
		
		Requirements::javascript('contentreview/javascript/contentreview.js');
		
		// Display read-only version only
		if(!Permission::check("EDIT_CONTENT_REVIEW_FIELDS")) {
			$schedule = self::get_schedule();
			$contentOwners = ReadonlyField::create('ROContentOwners', _t('ContentReview.CONTENTOWNERS', 'Content Owners'), $this->getOwnerNames());
			$nextReviewAt = DateField::create('RONextReviewDate', _t("ContentReview.NEXTREVIEWDATE", "Next review date"), $this->owner->NextReviewDate);
			if(!isset($schedule[$this->owner->ReviewPeriodDays])) {
				$reviewFreq = ReadonlyField::create("ROReviewPeriodDays", _t("ContentReview.REVIEWFREQUENCY", "Review frequency"), $schedule[0]);
			} else {
				$reviewFreq = ReadonlyField::create("ROReviewPeriodDays", _t("ContentReview.REVIEWFREQUENCY", "Review frequency"), $schedule[$this->owner->ReviewPeriodDays]);
			}
			
			$logConfig = GridFieldConfig::create()
				->addComponent(new GridFieldSortableHeader())
				->addComponent($logColumns = new GridFieldDataColumns());
			// Cast the value to the users prefered date format
			$logColumns->setFieldCasting(array(
				'Created' => 'DateTimeField->value'
			));
			$logs = GridField::create('ROReviewNotes', 'Review Notes', $this->owner->ReviewLogs(), $logConfig);
			
			
			$optionsFrom = ReadonlyField::create('ROType', _t('ContentReview.SETTINGSFROM', "Options are"), $this->owner->ContentReviewType);
			
			$fields->addFieldsToTab("Root.ContentReview", array(
				$contentOwners,
				$nextReviewAt->performReadonlyTransformation(),
				$reviewFreq,
				$optionsFrom,
				$logs
			));
			return;
		}
		
		$options = array();
		$options["Disabled"] = _t('ContentReview.DISABLE', "Disable content review");
		$options["Inherit"] = _t('ContentReview.INHERIT', "Inherit from parent page");
		$options["Custom"] = _t('ContentReview.CUSTOM', "Custom settings");
		$viewersOptionsField = OptionsetField::create("ContentReviewType", _t('ContentReview.OPTIONS', "Options"), $options);
		
		$users = Permission::get_members_by_permission(array("CMS_ACCESS_CMSMain", "ADMIN"));
		
		$usersMap = $users->map('ID', 'Title')->toArray();
		asort($usersMap);
		
		$userField = ListboxField::create('OwnerUsers', _t("ContentReview.PAGEOWNERUSERS", "Users"), $usersMap)
			->setMultiple(true)
			->setAttribute('data-placeholder', _t('ContentReview.ADDUSERS', 'Add users'))
			->setDescription(_t('ContentReview.OWNERUSERSDESCRIPTION', 'Page owners that are responsible for reviews'));

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
		
		$reviewDate = DateField::create("NextReviewDate", _t("ContentReview.NEXTREVIEWDATE", "Next review date"))
			->setConfig('showcalendar', true)
			->setConfig('dateformat', 'yyyy-MM-dd')
			->setConfig('datavalueformat', 'yyyy-MM-dd')
			->setDescription(_t('ContentReview.NEXTREVIEWDATADESCRIPTION', 'Leave blank for no review'));
		
		$reviewFrequency = DropdownField::create("ReviewPeriodDays", _t("ContentReview.REVIEWFREQUENCY", "Review frequency"), self::get_schedule())
			->setDescription(_t('ContentReview.REVIEWFREQUENCYDESCRIPTION', 'The review date will be set to this far in the future whenever the page is published'));
		
		$notesField = GridField::create('ReviewNotes', 'Review Notes', $this->owner->ReviewLogs(), GridFieldConfig_RecordEditor::create());
		
		$schedule = self::get_schedule();
		
		$fields->addFieldsToTab("Root.ContentReview", array(
			new HeaderField(_t('ContentReview.REVIEWHEADER', "Content review"), 2),
			$viewersOptionsField,
			CompositeField::create(
				ReadonlyField::create('ROContentOwners', _t('ContentReview.CONTENTOWNERS', 'Content Owners'), $this->getOwnerNames()),
				ReadonlyField::create('RONextReviewDate', _t("ContentReview.NEXTREVIEWDATE", "Next review date"), $this->owner->NextReviewDate)
			)->addExtraClass('inherited-settings'),
			CompositeField::create(
				$userField,
				$groupField,
				$reviewDate,
				$reviewFrequency
			)->addExtraClass('custom-settings'),
			$notesField
		));
	}
	
	/**
	 * Creates a ContentReviewLog and connects it to this Page
	 * 
	 * @param Member $reviewer
	 * @param string $message
	 */
	public function addReviewNote(Member $reviewer, $message) {
		$reviewLog = ContentReviewLog::create();
		$reviewLog->Note = $message;
		$reviewLog->ReviewerID = $reviewer->ID;
		$this->owner->ReviewLogs()->add($reviewLog);
	}
	
	/**
	 * Advance review date to the next date based on review period or set it to null
	 * if there is no schedule
	 * 
	 * @return bool - returns true if date was set and false is content review is 'off'
	 */
	public function advanceReviewDate() {
		$options = $this->getOptions();
		$nextDate = false;
		if($options && $options->ReviewPeriodDays) {
			$nextDate = date('Y-m-d', strtotime('+ '.$options->ReviewPeriodDays.' days', SS_Datetime::now()->format('U')));
		}
		$this->owner->NextReviewDate = $nextDate;
		$this->owner->write();
		return (bool)$nextDate;
	}
	
	/**
	 * Check if a review is due by a member for this owner
	 * 
	 * @param Member $member
	 * @return boolean
	 */
	public function canBeReviewedBy(Member $member = null) {
		if(!$this->owner->obj('NextReviewDate')->exists()) {
			return false;
		}
		if($this->owner->obj('NextReviewDate')->InFuture()) {
			return false;
		}
		
		$options = $this->getOptions();
		
		if($options->OwnerGroups()->count() == 0 && $options->OwnerUsers()->count() == 0) {
			return false;
		}
		
		// This content should be reviewed by someone
		if(!$member) {
			return true;
		}
		
		if($member->inGroups($options->OwnerGroups())) {
			return true;
		}
		
		if($options->OwnerUsers()->find('ID', $member->ID)) {
			return true;
		}
		return false;
	}

	/**
	 * Set the review data from the review period, if set.
	 */
	public function onBeforeWrite() {
		$this->owner->LastEditedByName=$this->owner->getEditorName();
		$this->owner->OwnerNames = $this->owner->getOwnerNames();
		
		// If the user changed the Type, we need to recalculate the
		// Next review date
		if($this->owner->isChanged('ContentReviewType', 2)) {
			
			// Changed to Disabled
			if($this->owner->ContentReviewType == 'Disabled') {
				$this->setDefaultReviewDateForDisabled();
			// Changed to Custom
			} elseif($this->owner->ContentReviewType == 'Custom') {
				$this->setDefaultReviewDateForCustom();
			// Changed to Inherit
			} else {
				$this->setDefaultReviewDateForInherited();
			}
		}
		
		// Ensure that a inherited page always have a next review date
		if($this->owner->ContentReviewType == 'Inherit' && !$this->owner->NextReviewDate) {
			$this->setDefaultReviewDateForInherited();
		}
		
		// Oh yey.. now we need to update all the child pages that inherit this setting
		// We can only change children after this record has been created, otherwise the stageChildren
		// method will grab all pages in the DB (this messes up unittesting)
		if(!$this->owner->exists()) {
			return;
		}
		
		// parent page change it's review period 
		// && !$this->owner->isChanged('ContentReviewType', 2)
		if($this->owner->isChanged('ReviewPeriodDays', 2)) {
			$nextReviewUnixSec = strtotime(' + '.$this->owner->ReviewPeriodDays . ' days', SS_Datetime::now()->format('U'));
			$this->owner->NextReviewDate = date('Y-m-d', $nextReviewUnixSec);
		}
		
		//
		if($this->owner->isChanged('NextReviewDate', 2)) {
			$children = $this->owner->stageChildren(true)->filter('ContentReviewType', 'Inherit');
			foreach($children as $child) {
				$child->NextReviewDate = $this->owner->NextReviewDate;
				$child->write();
			}
		}
	}
	
	/**
	 * 
	 */
	private function setDefaultReviewDateForDisabled() {
		$this->owner->NextReviewDate = null; 
	}
	
	/**
	 * 
	 */
	protected function setDefaultReviewDateForCustom() {
		if($this->owner->NextReviewDate) {
			return;
		}
		
		$this->owner->NextReviewDate = null;
		$nextDate = $this->getReviewDate();
		
		if(is_object($nextDate)) {
			$this->owner->NextReviewDate = $nextDate->getValue(); 
		} else {
			$this->owner->NextReviewDate = $nextDate; 
		}
		
	}
	
	/**
	 * 
	 */
	protected function setDefaultReviewDateForInherited() {
		$options = $this->getOptions();
		$nextDate = null;
		// Set the review date to be the same as the parents reviewdate
		if($options && $this->owner->parent()->exists()) {
			$nextDate = $this->getReviewDate($this->owner->parent());
		// Use the defualt settings
		} elseif($options instanceof SiteConfig) {
			$nextDate = $this->getReviewDate();
		} 
		if(is_object($nextDate)) {
			$this->owner->NextReviewDate = $nextDate->getValue(); 
		} else {
			$this->owner->NextReviewDate = $nextDate; 
		}
	}
	
	/**
	 * Provide permissions to the CMS
	 * 
	 * @return array
	 */
	public function providePermissions() {
		return array(
			"EDIT_CONTENT_REVIEW_FIELDS" => array(
				'name' => "Set content owners and review dates",
				'category' => _t('Permissions.CONTENT_CATEGORY', 'Content permissions'),
				'sort' => 50
			)
		);
	}
}
