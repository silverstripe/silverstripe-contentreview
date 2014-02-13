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
		"ReviewPeriodDays" => "Int",
		"NextReviewDate" => "Date",
		'ReviewNotes' => 'Text',
		'LastEditedByName' => 'Varchar(255)',
		'OwnerNames' => 'Varchar(255)'
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
	 * @return string
	 */
	public function getOwnerNames() {
		$names = array();
		foreach($this->DirectGroups() as $group) {
			$names[] = $group->Title;
		}
		
		foreach($this->DirectUsers() as $group) {
			$names[] = $group->getName();
		}
		return implode(', ', $names);
	}

	/**
	 * 
	 * @return string
	 */
	public function getEditorName() {
		if( $member = Member::currentUser() ) {
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
		
		$toplevelGroups = $this->DirectGroups();
		if($toplevelGroups) {
			$groupIDs = array();
			foreach($toplevelGroups as $group) {
				$familyIDs = $group->collateFamilyIDs();
				if(is_array($familyIDs)) {
					$groupIDs = array_merge($groupIDs, array_values($familyIDs));
				}
			}
			if(count($groupIDs)) {
				$groupMembers = DataObject::get('Member')->where("\"Group\".\"ID\" IN (" . implode(",",$groupIDs) . ")")
				->leftJoin("Group_Members", "\"Member\".\"ID\" = \"Group_Members\".\"MemberID\"")
				->leftJoin("Group", "\"Group_Members\".\"GroupID\" = \"Group\".\"ID\"");
				$contentReviewOwners->merge($groupMembers);
			}
			
		}
		$contentReviewOwners->merge($this->DirectUsers());
		$contentReviewOwners->removeDuplicates();
		return $contentReviewOwners;
	}
	
	/**
	 * @return ManyManyList
	 */
	public function DirectGroups() {
		return $this->owner->getManyManyComponents('ContentReviewGroups');
	}
	
	/**
	 * @return ManyManyList
	 */
	public function DirectUsers() {
		return $this->owner->getManyManyComponents('ContentReviewUsers');
	}

	/**
	 * 
	 * @param FieldList $fields
	 * @return void
	 */
	public function updateCMSFields(FieldList $fields) {
		if(!Permission::check("EDIT_CONTENT_REVIEW_FIELDS")) {
			return;
		}
		$users = Permission::get_members_by_permission(array("CMS_ACCESS_CMSMain", "ADMIN"));
		
		$usersMap = array();
		foreach($users as $user) {
			// Listboxfield values are escaped, use ASCII char instead of &raquo;
			$usersMap[$user->ID] = $user->getTitle();
		}
		asort($usersMap);
		
		$userField = ListboxField::create('DirectUsers', _t("ContentReview.PAGEOWNERUSERS", "Users"))
			->setMultiple(true)
			->setSource($usersMap)
			->setAttribute('data-placeholder', _t('ContentReview.ADDUSERS', 'Add users'))
			->setDescription(_t('ContentReview.OWNERUSERSDESCRIPTION', 'Page owners that are responsible for reviews'));

		$groupsMap = array();
		foreach(Group::get() as $group) {
			// Listboxfield values are escaped, use ASCII char instead of &raquo;
			$groupsMap[$group->ID] = $group->getBreadcrumbs(' > ');
		}
		asort($groupsMap);
		$groupField = ListboxField::create('DirectGroups', _t("ContentReview.PAGEOWNERGROUPS", "Groups"))
			->setMultiple(true)
			->setSource($groupsMap)
			->setAttribute('data-placeholder', _t('ContentReview.ADDGROUP', 'Add groups'))
			->setDescription(_t('ContentReview.OWNERGROUPSDESCRIPTION', 'Page owners that are responsible for reviews'));
		
		$reviewDate = DateField::create(
			"NextReviewDate",
			_t("ContentReview.NEXTREVIEWDATE", "Next review date")
		)->setConfig('showcalendar', true)
			->setConfig('dateformat', 'yyyy-MM-dd')
			->setConfig('datavalueformat', 'yyyy-MM-dd')
			->setDescription(_t('ContentReview.NEXTREVIEWDATADESCRIPTION', 'Leave blank for no review'));
		
		$reviewFrequency = DropdownField::create(
			"ReviewPeriodDays",
			_t("ContentReview.REVIEWFREQUENCY", "Review frequency"),
			array(
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
			)
		)->setDescription(_t('ContentReview.REVIEWFREQUENCYDESCRIPTION', 'The review date will be set to this far in the future whenever the page is published'));
		
		$fields->addFieldsToTab("Root.Review", array(
			new HeaderField(_t('ContentReview.REVIEWHEADER', "Content review"), 2),
			$userField,
			$groupField,
			$reviewDate,
			$reviewFrequency,
			new TextareaField('ReviewNotes', 'Review Notes')
		));
	}

	/**
	 * Set the review data from the review period, if set.
	 */
	public function onBeforeWrite() {
		if($this->owner->ReviewPeriodDays && !$this->owner->NextReviewDate) {
			$this->owner->NextReviewDate = date('Y-m-d', strtotime('+' . $this->owner->ReviewPeriodDays . ' days'));
		}
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
