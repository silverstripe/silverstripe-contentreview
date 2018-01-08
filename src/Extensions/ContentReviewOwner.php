<?php

namespace SilverStripe\ContentReview\Extensions;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataExtension;

/**
 * Description of GroupContentReview.
 */
class ContentReviewOwner extends DataExtension
{
    /**
     * @var array
     */
    private static $many_many = [
        "SiteTreeContentReview" => SiteTree::class,
    ];
}
