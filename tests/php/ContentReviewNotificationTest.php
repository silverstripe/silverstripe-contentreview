<?php

namespace SilverStripe\ContentReview\Tests;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\ContentReview\Extensions\ContentReviewCMSExtension;
use SilverStripe\ContentReview\Extensions\ContentReviewDefaultSettings;
use SilverStripe\ContentReview\Extensions\ContentReviewOwner;
use SilverStripe\ContentReview\Extensions\SiteTreeContentReview;
use SilverStripe\ContentReview\Tasks\ContentReviewEmails;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * @mixin PHPUnit_Framework_TestCase
 */
class ContentReviewNotificationTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'ContentReviewTest.yml';

    protected function setUp()
    {
        parent::setUp();

        // Hack to ensure only desired siteconfig is scaffolded
        $desiredID = $this->idFromFixture(SiteConfig::class, 'mysiteconfig');
        foreach (SiteConfig::get()->exclude('ID', $desiredID) as $config) {
            $config->delete();
        }
    }

    /**
     * @var array
     */
    protected static $required_extensions = [
        SiteTree::class => [SiteTreeContentReview::class],
        Group::class => [ContentReviewOwner::class],
        Member::class => [ContentReviewOwner::class],
        CMSPageEditController::class => [ContentReviewCMSExtension::class],
        SiteConfig::class => [ContentReviewDefaultSettings::class],
    ];

    public function testContentReviewEmails()
    {
        DBDatetime::set_mock_now('2010-02-24 12:00:00');

        /** @var Page|SiteTreeContentReview $childParentPage */
        $childParentPage = $this->objFromFixture(Page::class, 'contact');
        $childParentPage->NextReviewDate = '2010-02-23';
        $childParentPage->write();

        $task = new ContentReviewEmails();
        $task->run(new HTTPRequest('GET', '/dev/tasks/ContentReviewEmails'));

        // Set template variables (as per variable case)
        $ToEmail = 'author@example.com';
        $Subject = 'Please log in to review some content!';
        $PagesCount = 3;
        $ToFirstName = 'Test';

        $email = $this->findEmail($ToEmail, null, $Subject);
        $this->assertNotNull($email, "Email haven't been sent.");
        $this->assertContains(
            "<h1>$Subject</h1><p>There are $PagesCount pages that are due for review today by you, "
            . "$ToFirstName.</p><p>This email was sent to $ToEmail</p>",
            $email['HtmlContent']
        );
        $this->assertContains('Staff', $email['HtmlContent']);
        $this->assertContains('Contact Us', $email['HtmlContent']);
        $this->assertContains('Contact Us Child', $email['HtmlContent']);

        DBDatetime::clear_mock_now();
    }
}
