<?php

/**
 * Description of GroupContentReview.
 */
class ContentReviewOwner extends DataExtension
{
    /**
     * @var array
     */
    private static $many_many = array(
        "SiteTreeContentReview" => "SiteTree",
    );
}
