<?php
/**
 * Set dates at which content needs to be reviewed and provide
 * a report and emails to alert to content needing review
 *
 * @package contentreview
 */
class SiteTreeContentReview extends DataObjectDecorator implements PermissionProvider {

	function extraStatics() {
		return array(
			'db' => array(
				"ReviewPeriodDays" => "Int",
				"NextReviewDate" => "Date",
				'ReviewNotes' => 'Text',
				'LastEditedByName' => 'Varchar(255)',
				'OwnerNames' => 'Varchar(255)'
			),
			'has_one' => array(
				'Owner' => 'Member',
			),
		);
	}

	function getOwnerName() {
		if($this->owner->OwnerID && $this->owner->Owner()) return $this->owner->Owner()->FirstName . ' ' . $this->owner->Owner()->Surname;
	}

	function getEditorName() {
		if( $member = Member::currentUser() ) {
			 return $member->FirstName .' '. $member->Surname;
		}
		return NULL;
	}

	public function updateCMSFields(&$fields) {
		if(Permission::check("EDIT_CONTENT_REVIEW_FIELDS")) {

			$cmsUsers = Permission::get_members_by_permission(array("CMS_ACCESS_CMSMain", "ADMIN"));

			$fields->addFieldsToTab("Root.Review", array(
				new HeaderField(_t('SiteTreeCMSWorkflow.REVIEWHEADER', "Content review"), 2),
				new DropdownField("OwnerID", _t("SiteTreeCMSWorkflow.PAGEOWNER",
					"Page owner (will be responsible for reviews)"), $cmsUsers->map('ID', 'Title', '(no owner)')),
				new CalendarDateField("NextReviewDate", _t("SiteTreeCMSWorkflow.NEXTREVIEWDATE",
					"Next review date (leave blank for no review)")),
				new DropdownField("ReviewPeriodDays", _t("SiteTreeCMSWorkflow.REVIEWFREQUENCY",
					"Review frequency (the review date will be set to this far in the future whenever the page is published.)"), array(
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
				)),
				new TextareaField('ReviewNotes', 'Review Notes')
			));
		}
	}

	function onBeforeWrite() {
		if($this->owner->ReviewPeriodDays && !$this->owner->NextReviewDate) {
			$this->owner->NextReviewDate = date('Y-m-d', strtotime('+' . $this->owner->ReviewPeriodDays . ' days'));
		}
		$this->owner->LastEditedByName=$this->owner->getEditorName();
		$this->owner->OwnerNames = $this->owner->getOwnerName();
	}

	function providePermissions() {
		return array(
			"EDIT_CONTENT_REVIEW_FIELDS" => array(
				'name' => "Set content owners and review dates",
				'category' => _t('Permissions.CONTENT_CATEGORY', 'Content permissions'),
				'sort' => 50
			)
		);
	}
}
