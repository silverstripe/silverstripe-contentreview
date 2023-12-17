<?php

namespace SilverStripe\ContentReview\Models;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * @method Member Reviewer()
 * @method SiteTree SiteTree()
 */
class ContentReviewLog extends DataObject
{
    /**
     * @var array
     */
    private static $db = [
        "Note" => "Text",
    ];

    /**
     * @var array
     */
    private static $has_one = [
        "Reviewer" => Member::class,
        "SiteTree" => SiteTree::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        "Note"           => ["title" => "Note"],
        "Created"        => ["title" => "Reviewed at"],
        "Reviewer.Title" => ["title" => "Reviewed by"]
    ];

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
