<?php

namespace SilverStripe\ContentReview\Extensions;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ContentReview\Forms\ReviewContentHandler;
use SilverStripe\ContentReview\Traits\PermissionChecker;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Security;

/**
 * CMSPageEditController extension to receive the additional action button from
 * SiteTreeContentReview::updateCMSActions()
 *
 * @extends LeftAndMainExtension<CMSMain>
 */
class ContentReviewCMSExtension extends LeftAndMainExtension
{
    use PermissionChecker;

    private static $allowed_actions = [
        'ReviewContentForm',
        'savereview',
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
        $page = $this->findRecord(['ID' => $id]);
        $user = Security::getCurrentUser();
        if (!$this->isContentReviewable($page, $user)) {
            $this->owner->httpError(403, _t(
                __CLASS__.'.ErrorItemPermissionDenied',
                'It seems you don\'t have the necessary permissions to review this content'
            ));
            return null;
        }

        $form = $this->getReviewContentHandler()->Form($page);
        $form->setValidationResponseCallback(function (ValidationResult $errors) use ($form, $id) {
            $schemaId = $this->owner->join_links($this->owner->Link('schema/ReviewContentForm'), $id);
            return $this->getSchemaResponse($schemaId, $form, $errors);
        });

        return $form;
    }

    /**
     * Action handler for processing the submitted content review
     *
     * @param array $data
     * @param Form $form
     * @return DBHTMLText|HTTPResponse|null
     */
    public function savereview($data, Form $form)
    {
        $page = $this->findRecord($data);

        $results = $this->getReviewContentHandler()->submitReview($page, $data);
        if (is_null($results)) {
            return null;
        }
        if ($this->getSchemaRequested()) {
            // Send extra "message" data with schema response
            $extraData = ['message' => $results];
            $schemaId = $this->owner->join_links($this->owner->Link('schema/ReviewContentForm'), $page->ID);
            return $this->getSchemaResponse($schemaId, $form, null, $extraData);
        }

        return $results;
    }

    /**
     * Return a handler or reviewing content
     *
     * @return ReviewContentHandler
     */
    protected function getReviewContentHandler()
    {
        return ReviewContentHandler::create($this->owner);
    }

    /**
     * Find the page this form is updating
     *
     * @param array $data Form data
     * @return SiteTree Record
     * @throws HTTPResponse_Exception
     */
    protected function findRecord($data)
    {
        if (empty($data["ID"])) {
            throw new HTTPResponse_Exception("No record ID", 404);
        }
        $page = null;
        $id = $data["ID"];
        if (is_numeric($id)) {
            $page = SiteTree::get()->byID($id);
        }
        if (!$page || !$page->ID) {
            throw new HTTPResponse_Exception("Bad record ID #{$id}", 404);
        }
        return $page;
    }

    /**
     * Check if the current request has a X-Formschema-Request header set.
     * Used by conditional logic that responds to validation results
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

        $response = HTTPResponse::create(json_encode($data));
        $response->addHeader('Content-Type', 'application/json');

        return $response;
    }
}
