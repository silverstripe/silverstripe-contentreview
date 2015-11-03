<?php

/**
 * CMSPageEditController extension to receive the additional action button from
 * SiteTreeContentReview::updateCMSActions()
 */
class ContentReviewCMSExtension extends LeftAndMainExtension
{

    /**
     * @var array
     */
    private static $allowed_actions = array(
        "reviewed",
        "AddReviewForm",
    );

    /**
     * Shows a form with review notes.
     *
     * @param array $data
     * @param Form  $form
     *
     * @return SS_HTTPResponse
     * @throws SS_HTTPResponse_Exception
     */
    public function reviewed($data, Form $form)
    {
        $record = $this->findRecord($data);
        if (!$record->canEdit()) {
            return Security::permissionFailure($this->owner);
        }

        // Populate and respond
        $form = $this->AddReviewForm();
        $form->loadDataFrom($record);
        return $form->forTemplate();
    }

    /**
     * Form handler for this page
     *
     * @return CMSForm
     */
    public function AddReviewForm()
    {
        $reviewNotes = TextareaField::create("ReviewNotes", _t("ContentReview.REVIEWNOTES", "Review notes"));
        $reviewNotes->setDescription(_t("ContentReview.REVIEWNOTESDESCRIPTION ", "Add comments for the content of this page."));
        $fields = new FieldList();
        $fields->push(HiddenField::create("ID"));
        $fields->push($reviewNotes);

        $actions = new FieldList(
            FormAction::create("save_review", _t("ContentReview.SAVE", "Save"))
        );

        $form = CMSForm::create($this->owner, "AddReviewForm", $fields, $actions)->setHTMLID("Form_EditForm");
        $form->setResponseNegotiator($this->owner->getResponseNegotiator());
        $form->disableDefaultAction();

        // TODO Can't merge $FormAttributes in template at the moment
        $form->setTemplate($this->owner->getTemplatesWithSuffix("LeftAndMain_EditForm"));

        return $form;
    }

    /**
     * Save the review notes and redirect back to the page edit form.
     *
     * @param array $data
     * @param Form  $form
     *
     * @return string
     *
     * @throws SS_HTTPResponse_Exception
     */
    public function save_review($data, Form $form)
    {
        $page = $this->findRecord($data);
        if (!$page->canEdit()) {
            return Security::permissionFailure($this->owner);
        }

        $page->addReviewNote(Member::currentUser(), $data["ReviewNotes"]);
        $page->advanceReviewDate();

        return $this->owner->redirect($this->owner->Link("show/" . $page->ID));
    }

    /**
     * Find the page this form is updating
     *
     * @param array $data Form data
     * @return SiteTree Record
     * @throws SS_HTTPResponse_Exception
     */
    protected function findRecord($data)
    {
        if (empty($data["ID"])) {
            throw new SS_HTTPResponse_Exception("No record ID", 404);
        }

        $page = null;
        $id = $data["ID"];
        if (is_numeric($id)) {
            $page = SiteTree::get()->byID($id);
        }

        if (!$page || !$page->ID) {
            throw new SS_HTTPResponse_Exception("Bad record ID #{$id}", 404);
        }

        return $page;
    }
}
