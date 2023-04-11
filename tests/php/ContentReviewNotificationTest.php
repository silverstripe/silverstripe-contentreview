<?php

namespace SilverStripe\ContentReview\Tests;

use Page;
use ReflectionClass;
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
use SilverStripe\ContentReview\Models\ContentReviewLog;

class ContentReviewNotificationTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'ContentReviewTest.yml';

    protected function setUp(): void
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
        $this->assertStringContainsString(
            "<h1>$Subject</h1>".
            "<p>There are $PagesCount pages that are due for review today by you, $ToFirstName.</p>".
            "<p>This email was sent to $ToEmail</p>",
            $email['HtmlContent']
        );
        $this->assertStringContainsString('Staff', $email['HtmlContent']);
        $this->assertStringContainsString('Contact Us', $email['HtmlContent']);
        $this->assertStringContainsString('Contact Us Child', $email['HtmlContent']);

        DBDatetime::clear_mock_now();
    }

    /**
     * When a content review is left after a review date, we want to ensure that
     * overdue notifications aren't sent.
     */
    public function testContentReviewNeeded()
    {
        DBDatetime::set_mock_now('2018-08-10 12:00:00');

        /** @var Page|SiteTreeContentReview $childParentPage */
        $childParentPage = $this->objFromFixture(Page::class, 'no-review');
        $childParentPage->NextReviewDate = '2018-08-10';
        $childParentPage->write();

        // we need to ensure only our test page is being ran. If we don't do this
        // then it may notify for other pages which fails our test
        $this->deleteAllPagesExcept([$childParentPage->ID]);

        // Grabbing the 'author' from member class
        $member = $this->objFromFixture(Member::class, 'author');

        // Assigning member as contentreviewer to page
        $childParentPage->ContentReviewUsers()->add($member);

        // Assert that only one reviewer is assigned to page
        $this->assertCount(1, $childParentPage->ContentReviewUsers());

        // Create new log
        $log = new ContentReviewLog();

        // Assign log reviewer as current member
        $log->ReviewerID = $member->ID;

        // Assign log ID to page ID
        $log->SiteTreeID = $childParentPage->ID;

        // Write to DB
        $log->write();

        // assert that log was created day of review
        $this->assertEquals('2018-08-10 12:00:00', $log->Created);
        $this->assertCount(1, $childParentPage->ReviewLogs());

        $task = new ContentReviewEmails();
        $task->run(new HTTPRequest('GET', '/dev/tasks/ContentReviewEmails'));

        // Expecting to not send the email as content review for page is done
        $email = $this->findEmail($member->Email);
        $this->assertNull($email);

        DBDatetime::clear_mock_now();
    }

    /**
     * Test that provided email is valid
     */
    public function testIsValidEmail()
    {
        $class = new ReflectionClass(ContentReviewEmails::class);
        $method = $class->getMethod('isValidEmail');
        $method->setAccessible(true);

        $member = $this->objFromFixture(Member::class, 'author');
        $task = new ContentReviewEmails();

        $this->assertTrue($method->invokeArgs($task, [$member->Email]));
        $this->assertTrue($method->invokeArgs($task, ['correct.email@example.com']));

        $this->assertFalse($method->invokeArgs($task, [null]));
        $this->assertFalse($method->invokeArgs($task, ['broken.email']));
        $this->assertFalse($method->invokeArgs($task, ['broken@email']));
    }

    /**
     * Deletes all pages except those passes in to the $ids parameter
     *
     * @param  array  $ids Page IDs which will NOT be deleted
     */
    private function deleteAllPagesExcept(array $ids)
    {
        $pages = SiteTree::get()->exclude('ID', $ids);

        foreach ($pages as $page) {
            $page->delete();
        }
    }
}
