<?php

class ContentReviewLog extends DataObject
{
    /**
     * @var array
     */
    private static $db = array(
        "Note" => "Text",
    );

    /**
     * @var array
     */
    private static $has_one = array(
        "Reviewer" => "Member",
        "SiteTree" => "SiteTree",
    );

    /**
     * @var array
     */
    private static $summary_fields = array(
        "Note"           => array("title" => "Note"),
        "Created"        => array("title" => "Reviewed at"),
        "Reviewer.Title" => array("title" => "Reviewed by"),
    );

    /**
     * @var string
     */
    private static $default_sort = "Created DESC";

    /**
     * @param mixed $member
     *
     * @return bool
     */
    public function canView($member = null)
    {
        return (bool) Member::currentUser();
    }


    /**
     * allow the user to edit the fields
     * @return \FieldList
     */
    public function getCMSFields() {
        $fields = FieldList::create();
        $fields->push(TextareaField::create('Note'));
        $fields->push(HiddenField::create('ReviewerID', 'ReviewerID', Member::currentUserID()));
        return $fields;
    }
}
