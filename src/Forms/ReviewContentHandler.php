<?php

namespace SilverStripe\ContentReview\Forms;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Security;

class ReviewContentHandler
{
    use Injectable;

    /**
     * Parent controller for this form
     *
     * @var Controller
     */
    protected $controller;

    /**
     * The submitted form data
     *
     * @var array
     */
    protected $data;

    /**
     * Form name to use
     *
     * @var string
     */
    protected $name;

    /**
     * @param Controller       $controller
     * @param array|DataObject $data
     * @param string           $name
     */
    public function __construct($controller = null, $data = [], $name = 'ReviewContentForm')
    {
        $this->controller = $controller;
        if ($data instanceof DataObject) {
            $data = $data->toMap();
        }
        $this->data = $data;
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
        $placeholder = 'Add comments (optional)';

        $fields = FieldList::create([
            HiddenField::create('ID', null, $object->ID),
            HiddenField::create('ClassName', null, $object->baseClass()),
            TextareaField::create('Review', '')
                ->setAttribute('placeholder', $placeholder)
                ->setSchemaData(['attributes' => ['placeholder' => $placeholder]])
        ]);

        $actions = FieldList::create([
            ReviewContentHandlerFormAction::create()
                ->setTitle(_t(__CLASS__ . '.MarkAsReviewedAction', 'Mark as reviewed'))
                ->addExtraClass('review-content__action')
        ]);

        $form = Form::create($this->controller, $this->name, $fields, $actions);

        $form->setHTMLID('Form_EditForm_ReviewContent');
        $form->addExtraClass('form--no-dividers review-content__form');

        return $form;
    }

    /**
     * Validate, and save the submitted form's review
     *
     * @param  DataObject $record
     * @param  array $data
     * @return HTTPResponse|string
     */
    public function submitReview($record, $data)
    {
        if (!$record || !$record->exists()) {
            throw new ValidationException(_t(__CLASS__ . '.ObjectDoesntExist', 'That object doesn\'t exist'));
        }

        if (!$record->canEdit()
            || !$record->hasMethod('canBeReviewedBy')
            || !$record->canBeReviewedBy(Security::getCurrentUser())
        ) {
            throw new ValidationException(_t(
                __CLASS__ . '.ErrorReviewPermissionDenied',
                'It seems you don\'t have the necessary permissions to submit a content review'
            ));
        }

        $this->saveRecord($record, $data);

        $request = $this->controller->getRequest();
        $message = _t(__CLASS__ . '.Success', 'Review successfully added');

        if ($request->getHeader('X-Formschema-Request')) {
            return $message;
        } elseif (Director::is_ajax()) {
            $response = HTTPResponse::create($message, 200);
            $response->addHeader('Content-Type', 'text/html; charset=utf-8');
            return $response;
        } else {
            return $this->controller->redirectBack();
        }
    }

    /**
     * Save the review provided in $data to the $record
     *
     * @param DataObject $record
     * @param array $data
     */
    protected function saveRecord($record, $data)
    {
        $notes = (!empty($data['Review']) ? $data['Review'] : _t(__CLASS__ . '.NoComments', '(no comments)'));
        $record->addReviewNote(Security::getCurrentUser(), $notes);
        $record->advanceReviewDate();
    }
}
