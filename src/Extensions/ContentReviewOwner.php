<?php

namespace SilverStripe\ContentReview\Extensions;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class ContentReviewOwner extends DataExtension
{
    /**
     * @var array
     */
    private static $many_many = [
        "SiteTreeContentReview" => SiteTree::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        // Remove automatically scaffolded GridField in Member CMS fields
        $fields->removeByName('SiteTreeContentReview');
    }
}
