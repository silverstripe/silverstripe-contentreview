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
	 * @return string
	 */
	public function getOwnerNames() {
		$names = array();
		foreach($this->OwnerGroups() as $group) {
			$names[] = $group->getBreadcrumbs(' > ');
		}
		
		foreach($this->OwnerUsers() as $group) {
			$names[] = $group->getName();
		}
		return implode(', ', $names);
	}

	/**
	 * 
	 * @return string
	 */
	public function getEditorName() {
		if($member = Member::currentUser()) {
			 return $member->FirstName .' '. $member->Surname;
		}
		return NULL;
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
			
			$fields->addFieldsToTab("Root.ContentReview", array(
				$contentOwners,
				$nextReviewAt->performReadonlyTransformation(),
				$reviewFreq,
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
		
		$fields->addFieldsToTab("Root.ContentReview", array(
			new HeaderField(_t('ContentReview.REVIEWHEADER', "Content review"), 2),
			$viewersOptionsField,
			CompositeField::create(
				$userField,
				$groupField,
				$reviewDate,
				$reviewFrequency
			)->addExtraClass('contentReviewSettings'),
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
		$hasNextReview = true;
		if($this->owner->ReviewPeriodDays) {
			$this->owner->NextReviewDate = date('Y-m-d', strtotime('+' . $this->owner->ReviewPeriodDays . ' days'));
		} else {
			
			$hasNextReview = false;
			$this->owner->NextReviewDate = null;
		}
		
		$this->owner->write();
		return $hasNextReview;
	}
	
	/**
	 * 
	 * @param \FieldList $actions
	 */
	public function updateCMSActions(\FieldList $actions) {
		if($this->isContentReviewOverdue($this->owner, Member::currentUser())) {
			$reviewAction = FormAction::create('reviewed', _t('ContentReview.BUTTONREVIEWED', 'Content reviewed'))
				->setAttribute('data-icon', 'pencil')
				->setAttribute('data-text-alternate', _t('ContentReview.BUTTONREVIEWED', 'Content reviewed'));
			$actions->push($reviewAction);
		}
	}
	
	/**
	 * This method calculates if this page review date is over due.
	 * 
	 * If NextReviewDate is set, it will use the it, otherwise if fallsback to
	 * LastEdited and ReviewPeriodDays
	 * 
	 * @param DataObject $settings
	 * @param Member $null - optional check for a certain Member
	 * @return boolean
	 */
	public function isContentReviewOverdue(SiteTree $page, Member $member = null) {
		$settings = $this->getContentReviewSetting($page);
		
		if(!$settings) {
			return false;
		}
		
		if(!$settings->ContentReviewOwners()->count()) {
			return false;
		}
		
		if($member !== null) {
			// member must exists in either owner groups ro owner users
			if(!($member->inGroups($settings->OwnerGroups()) || $settings->OwnerUsers()->find('ID', $member->ID))) {
				return false;
			}
		}
		
		if($page->obj('NextReviewDate')->exists() && !$page->obj('NextReviewDate')->InFuture()) {
			return true;
		}
		
		// Fallover to check on ReviewPeriodDays + LastEdited > Now
		if(!$settings->ReviewPeriodDays) {
			return false;
		}
		
		// Calculate next time this page should be reviewed from the LastEdited datea
		$nextReviewUnixSec = strtotime($this->owner->LastEdited . ' + '.$settings->ReviewPeriodDays . ' days');
		
		if($nextReviewUnixSec < time()) {
			return true;
		}
		return false;
	}
	
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
	 * Check if a review is due by a member for this owner
	 * 
	 * @param Member $member
	 * @return boolean
	 */
	public function canBeReviewedBy(Member $member) {
		if(!$this->owner->obj('NextReviewDate')->exists()) {
			return false;
		}
		if($this->owner->obj('NextReviewDate')->InFuture()) {
			return false;
		}
		if($this->OwnerGroups()->count() == 0 && $this->OwnerUsers()->count() == 0) {
			return false;
		}
		if($member->inGroups($this->OwnerGroups())) {
			return true;
		}
		if($this->OwnerUsers()->find('ID', $member->ID)) {
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
