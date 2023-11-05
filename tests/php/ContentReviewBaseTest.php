<?php

namespace SilverStripe\ContentReview\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\CMS\Model\SiteTree;

/**
 * Extend this class when writing unit tests which are compatible with other modules.
 * All compatibility code goes here.
 */
abstract class ContentReviewBaseTest extends FunctionalTest
{
    /**
     * @var bool
     */
    protected $translatableEnabledBefore;
}
