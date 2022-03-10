<?php

namespace SilverStripe\ContentReview\Tests;

use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ContentReview\Extensions\ContentReviewCMSExtension;
use SilverStripe\ContentReview\Extensions\ContentReviewDefaultSettings;
use SilverStripe\ContentReview\Extensions\ContentReviewOwner;
use SilverStripe\ContentReview\Extensions\SiteTreeContentReview;
use SilverStripe\ContentReview\Reports\PagesDueForReviewReport;
use SilverStripe\ContentReview\Reports\PagesWithoutReviewScheduleReport;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;

class ContentReviewReportTest extends FunctionalTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'ContentReviewTest.yml';

    /**
     * @var array
     */
    protected static $required_extensions = [
        SiteTree::class              => [SiteTreeContentReview::class],
        Group::class                 => [ContentReviewOwner::class],
        Member::class                => [ContentReviewOwner::class],
        CMSPageEditController::class => [ContentReviewCMSExtension::class],
        SiteConfig::class            => [ContentReviewDefaultSettings::class],
    ];

    public function testPagesDueForReviewReport()
    {
        /** @var Member $editor */
        $editor = $this->objFromFixture(Member::class, "editor");

        $this->logInAs($editor);

        $report = new PagesDueForReviewReport();

        $report->parameterFields();
        $report->columns();
        $report->title();

        $results = $report->sourceRecords([
            "ReviewDateAfter"  => "2010-01-01",
            "ReviewDateBefore" => "2010-12-12",
        ]);

        $this->assertListEquals([
            ['Title' => 'Contact Us Child'],
            ['Title' => 'Home'],
            ['Title' => 'About Us'],
            ['Title' => 'Staff'],
            ['Title' => 'Contact Us'],
        ], $results);

        DBDatetime::set_mock_now("2010-02-13 00:00:00");

        $results = $report->sourceRecords([]);

        $this->assertEquals([
            "Home",
            "About Us",
        ], $results->column("Title"));

        DBDatetime::clear_mock_now();
    }

    public function testPagesWithoutReviewScheduleReport()
    {
        /** @var Member $editor */
        $editor = $this->objFromFixture(Member::class, "editor");

        $this->logInAs($editor);

        $report = new PagesWithoutReviewScheduleReport();

        $report->parameterFields();
        $report->columns();
        $report->title();

        $results = $report->sourceRecords();

        $this->assertListEquals([
            ['Title' => 'Home'],
            ['Title' => 'About Us'],
            ['Title' => 'Page without review date'],
            ['Title' => 'Page owned by group'],
        ], $results);
    }
}
