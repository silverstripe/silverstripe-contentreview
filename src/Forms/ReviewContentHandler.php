<?php

namespace SilverStripe\ContentReview\Forms;

use SilverStripe\ContentReview\Extensions\SiteTreeContentReview;
use SilverStripe\ContentReview\Traits\PermissionChecker;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Security;

class ReviewContentHandler
{
    use Injectable;
    use PermissionChecker;

    /**
     * Parent controller for this form
     *
     * @var Controller
     */
    protected $controller;

    /**
     * Form name to use
     *
     * @var string
     */
    protected $name;

    /**
     * @param Controller       $controller
     * @param string           $name
     */
    public function __construct($controller = null, $name = 'ReviewContentForm')
    {
        $this->controller = $controller;
        $this->name = $name;
    }

    /**
     * Bootstrap the form fields for the content review modal
     *
     * @param DataObject $object
     * @return Form
     */
    public function Form($object)
    {
        $placeholder = _t(__CLASS__ . '.Placeholder', 'Add comments (optional)');
        $title = _t(__CLASS__ . '.MarkAsReviewedAction', 'Mark as reviewed');

        $fields = FieldList::create([
            HiddenField::create('ID', null, $object->ID),
            HiddenField::create('ClassName', null, $object->baseClass()),
            TextareaField::create('Review', '')
                ->setAttribute('placeholder', $placeholder)
                ->setSchemaData(['attributes' => ['placeholder' => $placeholder]])
        ]);

        $action = FormAction::create('savereview', $title)
            ->setTitle($title)
            ->setUseButtonTag(false)
            ->addExtraClass('review-content__action btn btn-primary');
        $actions = FieldList::create([$action]);

        $form = Form::create($this->controller, $this->name, $fields, $actions)
            ->setHTMLID('Form_EditForm_ReviewContent')
            ->addExtraClass('form--no-dividers review-content__form');

        return $form;
    }

    /**
     * Validate, and save the submitted form's review
     *
     * @param  DataObject $record
     * @param  array $data
     * @return HTTPResponse|string
     * @throws ValidationException If the user cannot submit the review
     */
    public function submitReview($record, $data)
    {
        /** @var DataObject|SiteTreeContentReview $record */
        if (!$this->canSubmitReview($record)) {
            throw new ValidationException(_t(
                __CLASS__ . '.ErrorReviewPermissionDenied',
                'It seems you don\'t have the necessary permissions to submit a content review'
            ));
        }

        $notes = (!empty($data['Review']) ? $data['Review'] : _t(__CLASS__ . '.NoComments', '(no comments)'));
        $record->addReviewNote(Security::getCurrentUser(), $notes);
        $record->advanceReviewDate();

        $request = $this->controller->getRequest();
        $message = _t(__CLASS__ . '.Success', 'Review successfully added');

        if ($request->getHeader('X-Formschema-Request')) {
            return $message;
        } elseif (Director::is_ajax()) {
            $response = HTTPResponse::create($message, 200);
            $response->addHeader('Content-Type', 'text/html; charset=utf-8');
            return $response;
        }

        return $this->controller->redirectBack();
    }

    /**
     * Determine whether the user can submit a review
     *
     * @param DataObject $record
     * @return bool
     */
    public function canSubmitReview($record)
    {
        // Ensure the parameter of correct data type
        if (!$record instanceof DataObject) {
            return false;
        }

        return $this->isContentReviewable($record, Security::getCurrentUser());
    }
}
