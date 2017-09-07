<?php

namespace SilverStripe\ContentReview\Models;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

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
        "Reviewer" => Member::class,
        "SiteTree" => SiteTree::class,
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

    private static $table_name = 'ContentReviewLog';

    /**
     * @param mixed $member
     *
     * @return bool
     */
    public function canView($member = null)
    {
        return (bool) Security::getCurrentUser();
    }
}
