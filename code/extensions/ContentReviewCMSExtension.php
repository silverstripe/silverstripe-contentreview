<?php

/**
 * CMSPageEditController extension to recieve the additonal action button from 
 * SiteTreeContentReview::updateCMSActions()
 * 
 */
class ContentReviewCMSExtension extends LeftAndMainExtension {
	
	/**
	 *
	 * @var array
	 */
	private static $allowed_actions = array(
		'reviewed',
		'save_review',
	);
	
	/**
	 * Shows a form with review notes 
	 * 
	 * @param array $data 
	 * @param Form $form
	 * @return SS_HTTPResponse
	 */
	public function reviewed($data, Form $form) {
		if(!isset($data['ID'])) {
			throw new SS_HTTPResponse_Exception("No record ID", 404);
		}
		$SQL_id = (int) $data['ID'];
		$record = SiteTree::get()->byID($SQL_id);
		
		if(!$record || !$record->ID) {
			throw new SS_HTTPResponse_Exception("Bad record ID #$SQL_id", 404);
		}
		
		if(!$record->canEdit()) {
			return Security::permissionFailure($this->owner);
		}
			
		$fields = new FieldList();
		$fields->push(HiddenField::create('ID', 'ID', $SQL_id));
		$fields->push(TextareaField::create('ReviewNotes', 'Review notes'));
		
		$actions = new FieldList(
			FormAction::create('save_review', 'Save')
		);
		
		$form = CMSForm::create($this->owner, "EditForm", $fields, $actions)->setHTMLID('Form_EditForm');
		$form->setResponseNegotiator($this->owner->getResponseNegotiator());
		$form->loadDataFrom($record);
		$form->disableDefaultAction();
		
		// TODO Can't merge $FormAttributes in template at the moment
		$form->setTemplate($this->owner->getTemplatesWithSuffix('LeftAndMain_EditForm'));
		return $form->forTemplate();
	}
	
	/**
	 * Save the review notes and redirect back to the page edit form
	 * 
	 * @param array $data 
	 * @param Form $form
	 * @return string - html
	 */
	public function save_review($data, Form $form) {
		if(!isset($data['ID'])) {
			throw new SS_HTTPResponse_Exception("No record ID", 404);
		}
		$SQL_id = (int) $data['ID'];
		$page = SiteTree::get()->byID($SQL_id);
		if($page && !$page->canEdit()) {
			return Security::permissionFailure();
		}
		if(!$page || !$page->ID) {
			throw new SS_HTTPResponse_Exception("Bad record ID #$SQL_id", 404);
		}
		
		$page->addReviewNote(Member::currentUser(), $data['ReviewNotes']);
		$page->advanceReviewDate();
		
		return $this->owner->redirect($this->owner->Link('show/'.$page->ID));
	}
}
