<?php

namespace SilverStripe\ContentReview\Tests;

use Page;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ContentReview\Extensions\ContentReviewCMSExtension;
use SilverStripe\ContentReview\Extensions\ContentReviewDefaultSettings;
use SilverStripe\ContentReview\Extensions\ContentReviewOwner;
use SilverStripe\ContentReview\Extensions\SiteTreeContentReview;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\ArrayList;

class SiteTreeContentReviewTest extends ContentReviewBaseTest
{
    protected $usesTransactions = false;

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

    public function testOwnerNames()
    {
        /** @var Member $editor */
        $editor = $this->objFromFixture(Member::class, "editor");

        $this->logInAs($editor);

        /** @var Page|SiteTreeContentReview $page */
        $page = new Page();
        $page->ReviewPeriodDays = 10;
        $page->ContentReviewType = "Custom";

        $page->ContentReviewUsers()->push($editor);
        $page->write();

        $this->assertTrue($page->canPublish());
        $this->assertTrue($page->publishRecursive());
        $this->assertEquals($page->OwnerNames, "Test Editor", "Test Editor should be the owner");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "about");

        $page->OwnerUsers()->removeAll();
        $page->write();

        $this->assertTrue($page->canPublish());
        $this->assertTrue($page->publishRecursive());
        $this->assertEquals("", $page->OwnerNames);
    }

    public function testPermissionsExists()
    {
        $perms = singleton(SiteTreeContentReview::class)->providePermissions();

        $this->assertTrue(isset($perms["EDIT_CONTENT_REVIEW_FIELDS"]));
    }

    public function testUserWithPermissionCanEdit()
    {
        /** @var Member $editor */
        $editor = $this->objFromFixture(Member::class, "editor");

        $this->logInAs($editor);

        /** @var Page|SiteTreeContentReview $page */
        $page = new Page();

        $fields = $page->getSettingsFields();

        $this->assertNotNull($fields->dataFieldByName("NextReviewDate"));
    }

    public function testUserWithoutPermissionCannotEdit()
    {
        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        $this->logInAs($author);

        /** @var Page|SiteTreeContentReview $page */
        $page = new Page();

        $fields = $page->getSettingsFields();

        $this->assertNull($fields->dataFieldByName("NextReviewDate"));
    }

    public function testAutomaticallyToNotSetReviewDate()
    {
        /** @var Member $editor */
        $editor = $this->objFromFixture(Member::class, "editor");

        $this->logInAs($editor);

        /** @var Page|SiteTreeContentReview $page */
        $page = new Page();

        $page->ReviewPeriodDays = 10;
        $page->write();

        $this->assertTrue($page->publishRecursive());
        $this->assertEquals(null, $page->NextReviewDate);
    }

    public function testAdvanceReviewDate()
    {
        $page = new Page();
        $page->Title = 'Test page';
        $page->ReviewPeriodDays = 0;
        // Set timestamp to a time in the past
        $timestamp = DBDatetime::now()->getTimestamp() - 100000;
        $page->NextReviewDate = DBDate::create()->setValue($timestamp)->Format(DBDate::ISO_DATE);
        $page->write();
        $page->advanceReviewDate();
        $this->assertNull(Page::get()->find('Title', 'Test page')->NextReviewDate);
    }


    public function testAddReviewNote()
    {
        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "home");

        $page->addReviewNote($author, "This is a message");

        /** @var Page|SiteTreeContentReview $page */
        $homepage = $this->objFromFixture(Page::class, "home");

        $this->assertEquals(1, $homepage->ReviewLogs()->count());
        $this->assertEquals("This is a message", $homepage->ReviewLogs()->first()->Note);
    }

    public function testGetContentReviewOwners()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "group-owned");

        $owners = $page->ContentReviewOwners();

        $this->assertEquals(1, $owners->count());
        $this->assertEquals("author@example.com", $owners->first()->Email);
    }

    public function testCanNotBeReviewBecauseNoReviewDate()
    {
        DBDatetime::set_mock_now("2010-01-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "no-review");

        $this->assertFalse($page->canBeReviewedBy($author));

        DBDatetime::clear_mock_now();
    }

    public function testCanNotBeReviewedBecauseInFuture()
    {
        DBDatetime::set_mock_now("2010-01-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "staff");

        $this->assertFalse($page->canBeReviewedBy($author));

        DBDatetime::clear_mock_now();
    }

    public function testCanNotBeReviewedByUser()
    {
        DBDatetime::set_mock_now("2010-03-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "home");

        $this->assertFalse($page->canBeReviewedBy($author));

        DBDatetime::clear_mock_now();
    }

    public function testCanBeReviewedByUser()
    {
        DBDatetime::set_mock_now("2010-03-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "staff");

        $this->assertTrue($page->canBeReviewedBy($author));

        DBDatetime::clear_mock_now();
    }

    public function testCanNotBeReviewedByGroup()
    {
        DBDatetime::set_mock_now("2010-03-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "editor");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "contact");

        $this->assertFalse($page->canBeReviewedBy($author));

        DBDatetime::clear_mock_now();
    }

    public function testCanBeReviewedByGroup()
    {
        DBDatetime::set_mock_now("2010-03-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "contact");

        $this->assertTrue($page->canBeReviewedBy($author));

        DBDatetime::clear_mock_now();
    }

    public function testCanBeReviewedFromInheritedSetting()
    {
        DBDatetime::set_mock_now("2013-03-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        /** @var Page|SiteTreeContentReview $parentPage */
        $parentPage = $this->objFromFixture(Page::class, "contact");

        $parentPage->NextReviewDate = "2013-01-01";
        $parentPage->write();

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "contact-child");

        $this->assertTrue($page->canBeReviewedBy($author));

        DBDatetime::clear_mock_now();
    }

    public function testUnModifiedPagesDontChangeEditor()
    {
        DBDatetime::set_mock_now("2013-03-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");
        $this->logInAs($author);

        // Page which is un-modified doesn't advance version of have an editor assigned
        $contactPage = $this->objFromFixture(Page::class, "contact");
        $contactPageVersion = $contactPage->Version;
        $contactPage->write();
        $this->assertEmpty($contactPage->LastEditedByName);
        $this->assertEquals(
            $contactPageVersion,
            Versioned::get_versionnumber_by_stage(SiteTree::class, 'Stage', $contactPage->ID, false)
        );

        // Page with modifications gets marked
        $homePage = $this->objFromFixture(Page::class, "home");
        $homePageVersion = $homePage->Version;
        $homePage->Content = '<p>Welcome!</p>';
        $homePage->write();
        $this->assertNotEmpty($homePage->LastEditedByName);
        $this->assertEquals($author->getTitle(), $homePage->LastEditedByName);
        $this->assertGreaterThan(
            $homePageVersion,
            Versioned::get_versionnumber_by_stage(SiteTree::class, 'Stage', $homePage->ID, false)
        );

        DBDatetime::clear_mock_now();
    }

    public function testReviewActionVisibleForAuthor()
    {
        DBDatetime::set_mock_now('2020-03-01 12:00:00');

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, 'contact');

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, 'author');

        $this->logInAs($author);

        $fields = $page->getCMSActions();

        $this->assertInstanceOf(LiteralField::class, $fields->fieldByName('ContentReviewButton'));

        DBDatetime::clear_mock_now();
    }

    public function testReviewActionNotVisibleForEditor()
    {
        DBDatetime::set_mock_now("2020-03-01 12:00:00");

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "contact");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "editor");

        $this->logInAs($author);

        $fields = $page->getCMSActions();

        $this->assertNull($fields->fieldByName("ActionMenus.ReviewContent"));

        DBDatetime::clear_mock_now();
    }

    public function testSiteConfigSettingsAreUsedAsDefaults()
    {
        DBDatetime::set_mock_now("2020-03-01 12:00:00");

        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, 'editor');

        /** @var SiteConfig $siteConfig */
        $siteConfig = SiteConfig::current_site_config();

        // Set the author to a default user for reviewing
        $siteConfig->OwnerUsers()->add($author);

        $emptyPage = new Page;
        $emptyPage->NextReviewDate = '2020-02-20 12:00:00';

        $this->assertTrue($emptyPage->canBeReviewedBy($author));

        DBDatetime::clear_mock_now();
    }

    public function testPermissionCheckByOnDataObject()
    {
        $reviewer = $this->objFromFixture(Member::class, 'editor');

        // Mock Page class with canReviewContent method to return true on first call and false on second call
        $mock = $this->getMockBuilder(Page::class)
            ->setMethods(['canReviewContent', 'NextReviewDate', 'OwnerUsers'])
            ->getMock();
        $mock->expects($this->exactly(2))->method('canReviewContent')->willReturnOnConsecutiveCalls(false, true);
        $mock->method('NextReviewDate')->willReturn('2020-02-20 12:00:00');
        $mock->method('OwnerUsers')->willReturn(ArrayList::create([$reviewer]));
        $mock->ContentReviewType = 'Custom';

        /** @var SiteTreeContentReview $extension */
        $extension = Injector::inst()->get(SiteTreeContentReview::class);
        $extension->setOwner($mock);

        // Assert that the user is not allowed to review content
        $author = $this->objFromFixture(Member::class, 'author');
        $this->assertFalse($extension->canBeReviewedBy($author));

        DBDatetime::set_mock_now("2020-03-01 12:00:00");

        // Assert that the user is allowed to review content
        $this->assertTrue($extension->canBeReviewedBy($reviewer));

        // Assert tht canBeReviewedBy return true if no user logged in
        // This is for CLI execution for ContentReviewEmails task
        $this->logOut();
        $this->assertTrue($extension->canBeReviewedBy());

        DBDatetime::clear_mock_now();
    }
}
