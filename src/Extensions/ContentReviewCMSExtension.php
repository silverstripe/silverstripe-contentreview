<?php

namespace SilverStripe\ContentReview\Extensions;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ContentReview\Forms\ReviewContentHandler;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Security;

/**
 * CMSPageEditController extension to receive the additional action button from
 * SiteTreeContentReview::updateCMSActions()
 */
class ContentReviewCMSExtension extends LeftAndMainExtension
{
    private static $allowed_actions = [
        'ReviewContentForm',
        'submitReview',
    ];

    /**
     * URL handler for the "content due for review" form
     *
     * @param HTTPRequest $request
     * @return Form|null
     */
    public function ReviewContentForm(HTTPRequest $request)
    {
        // Get ID either from posted back value, or url parameter
        $id = $request->param('ID') ?: $request->postVar('ID');
        return $this->getReviewContentForm($id);
    }

    /**
     * Return a handler for "content due for review" forms, according to the given object ID
     *
     * @param  int $id
     * @return Form|null
     */
    public function getReviewContentForm($id)
    {
        $record = SiteTree::get()->byID($id);

        if (!$record) {
            $this->owner->httpError(404, _t(__CLASS__ . '.ErrorNotFound', 'That object couldn\'t be found'));
            return null;
        }

        $user = Security::getCurrentUser();
        if (!$record->canEdit() || ($record->hasMethod('canBeReviewedBy') && !$record->canBeReviewedBy($user))) {
            $this->owner->httpError(403, _t(
                __CLASS__.'.ErrorItemPermissionDenied',
                'It seems you don\'t have the necessary permissions to review this content'
            ));
            return null;
        }

        $handler = ReviewContentHandler::create($this->owner, $record);
        $form = $handler->Form($record);

        $form->setValidationResponseCallback(function (ValidationResult $errors) use ($form, $id) {
            $schemaId = $this->owner->join_links($this->owner->Link('schema/ReviewContentForm'), $id);
            return $this->owner->getSchemaResponse($schemaId, $form, $errors);
        });

        return $form;
    }

    /**
     * Action handler for processing the submitted content review
     *
     * @param array $data
     * @param Form $form
     * @return DBHTMLText|HTTPResponse
     */
    public function submitReview($data = '', $form = '')
    {
        $id = $data['ID'];
        $record = SiteTree::get()->byID($id);

        $handler = ReviewContentHandler::create($this->owner, $record);
        $form = $handler->Form($record);
        $results = $handler->submitReview($record, $data);
        if (is_null($results)) {
            return null;
        }

        if ($this->getSchemaRequested()) {
            // Send extra "message" data with schema response
            $extraData = ['message' => $results];
            $schemaId = $this->owner->join_links($this->owner->Link('schema/ReviewContentForm'), $id);
            return $this->getSchemaResponse($schemaId, $form, null, $extraData);
        }

        return $results;
    }

    /**
     * Check if the current request has a X-Formschema-Request header set.
     * Used by conditional logic that responds to validation results
     *
     * @todo Remove duplication. See https://github.com/silverstripe/silverstripe-admin/issues/240
     *
     * @return bool
     */
    protected function getSchemaRequested()
    {
        $parts = $this->owner->getRequest()->getHeader(LeftAndMain::SCHEMA_HEADER);
        return !empty($parts);
    }

    /**
     * Generate schema for the given form based on the X-Formschema-Request header value
     *
     * @todo Remove duplication. See https://github.com/silverstripe/silverstripe-admin/issues/240
     *
     * @param string $schemaID ID for this schema. Required.
     * @param Form $form Required for 'state' or 'schema' response
     * @param ValidationResult $errors Required for 'error' response
     * @param array $extraData Any extra data to be merged with the schema response
     * @return HTTPResponse
     */
    protected function getSchemaResponse($schemaID, $form = null, ValidationResult $errors = null, $extraData = [])
    {
        $parts = $this->owner->getRequest()->getHeader(LeftAndMain::SCHEMA_HEADER);
        $data = $this->owner
            ->getFormSchema()
            ->getMultipartSchema($parts, $schemaID, $form, $errors);

        if ($extraData) {
            $data = array_merge($data, $extraData);
        }

        $response = HTTPResponse::create(Convert::raw2json($data));
        $response->addHeader('Content-Type', 'application/json');

        return $response;
    }
}
