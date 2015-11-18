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
        "savereview"
    );

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
    public function savereview($data, Form $form)
    {
        $page = $this->findRecord($data);
        if (!$page->canEdit()) {
            return Security::permissionFailure($this->owner);
        }

        $notes = (!empty($data["ReviewNotes"]) ? $data["ReviewNotes"] : _t("ContentReview.NOCOMMENTS", "(no comments)"));
        $page->addReviewNote(Member::currentUser(), $notes);
        $page->advanceReviewDate();
        
        $this->owner->getResponse()->addHeader("X-Status", _t("ContentReview.REVIEWSUCCESSFUL", "Content reviewed successfully"));
        return $this->owner->redirectBack();
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
