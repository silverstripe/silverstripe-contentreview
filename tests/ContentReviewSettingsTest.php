<?php

/**
 * This class tests that settings are inherited correctly based on the inherited,
 * custom or disabled settings.
 *
 * @mixin PHPUnit_Framework_TestCase
 */
class ContentReviewSettingsTest extends SapphireTest
{
    /**
     * @var string
     */
    public static $fixture_file = "contentreview/tests/ContentReviewSettingsTest.yml";

    /**
     * @var array
     */
    protected $requiredExtensions = array(
        "SiteTree"              => array("SiteTreeContentReview"),
        "Group"                 => array("ContentReviewOwner"),
        "Member"                => array("ContentReviewOwner"),
        "CMSPageEditController" => array("ContentReviewCMSExtension"),
        "SiteConfig"            => array("ContentReviewDefaultSettings"),
    );

    public function testAdvanceReviewDate10Days()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = new Page();

        $page->ContentReviewType = "Custom";
        $page->ReviewPeriodDays = 10;

        $this->assertTrue($page->advanceReviewDate());

        $page->write();

        $this->assertEquals(date("Y-m-d", strtotime("now + 10 days")), $page->NextReviewDate);
    }

    public function testAdvanceReviewDateNull()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = new Page();

        $page->ContentReviewType = "Custom";
        $page->ReviewPeriodDays = 0;

        $this->assertFalse($page->advanceReviewDate());

        $page->write();

        $this->assertEquals(null, $page->NextReviewDate);
    }

    public function testAdvanceReviewFromCustomSettings()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "custom");

        $this->assertTrue($page->advanceReviewDate());

        $page->write();

        $this->assertEquals(date("Y-m-d", strtotime("now + " . $page->ReviewPeriodDays . " days")), $page->NextReviewDate);
    }

    public function testAdvanceReviewFromInheritedSettings()
    {
		// When a parent page is advanced, the next review date of the child is not automatically advanced
        $parentPage = $this->objFromFixture("Page", "page-1");
		$this->assertTrue($parentPage->advanceReviewDate());
		$parentPage->write();
		
        $page = $this->objFromFixture("Page", "page-1-1");
		$this->assertEquals(date("Y-m-d", strtotime("now + 5 days")), $parentPage->NextReviewDate);
		$this->assertEquals('2011-04-12', $page->NextReviewDate);

		// When a sub page is advanced, the next review date is advanced by the number of days in the parent
        $this->assertTrue($page->advanceReviewDate());
        $page->write();
        $this->assertEquals(date("Y-m-d", strtotime("now + 5 days")), $page->NextReviewDate);
    }

    public function testAdvanceReviewFromInheritedSiteConfigSettings()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "inherit");

        /** @var SiteConfig|ContentReviewDefaultSettings $siteConfig */
        $siteConfig = $this->objFromFixture("SiteConfig", "default");

        $this->assertTrue($page->advanceReviewDate());

        $page->write();

        $this->assertEquals(date("Y-m-d", strtotime("now + " . $siteConfig->ReviewPeriodDays . " days")), $page->NextReviewDate);
    }

    public function testGetSettingsObjectFromCustom()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "custom");

        $this->assertEquals("Custom", $page->ContentReviewType);
        $this->assertEquals($page, $page->getOptions());
    }

    public function testGetSettingsObjectFromDisabled()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "disabled");

        $this->assertEquals("Disabled", $page->ContentReviewType);
        $this->assertFalse($page->getOptions());
    }

    public function testGetOptionObjectFromInheritedDisabled()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "page-2-1-1");

        $this->assertEquals("Inherit", $page->ContentReviewType);
        $this->assertFalse($page->getOptions());
    }

    public function testGetOptionObjectFromDeeplyInheritedPage()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "page-3-1-1-1");

        $this->assertEquals("Inherit", $page->ContentReviewType);
        $this->assertInstanceOf("SiteConfig", $page->getOptions());
    }

    public function testGetSettingsObjectFromInheritPage()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "page-1-1");

        /** @var Page|SiteTreeContentReview $parentPage */
        $parentPage = $this->objFromFixture("Page", "page-1");

        $this->assertEquals("Inherit", $page->ContentReviewType);
        $this->assertEquals(get_class($parentPage), get_class($page->getOptions()));
        $this->assertEquals($parentPage->ID, $page->getOptions()->ID);
    }

    public function testGetSettingsObjectFromInheritedRootPage()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "inherit");

        $this->assertEquals("Inherit", $page->ContentReviewType);
        $this->assertEquals($this->objFromFixture("SiteConfig", "default"), $page->getOptions());
    }

    public function testGetNextReviewDateFromCustomSettings()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture('Page', 'custom');

        $date = $page->getReviewDate();

        $this->assertEquals('2010-02-01', $date->format('Y-m-d'));
    }

    public function testGetNextReviewDateFromSiteConfigInheritedSetting()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "inherit");

        $nextReviewDate = $page->getReviewDate();

        $this->assertInstanceOf("Date", $nextReviewDate);

        /** @var SiteConfig|ContentReviewDefaultSettings $siteConfig */
        $siteConfig = $this->objFromFixture("SiteConfig", "default");

        $expected = $this->addDaysToDate(SS_Datetime::now(), $siteConfig->ReviewPeriodDays);

        $this->assertEquals($expected, $nextReviewDate->format("Y-m-d"));
    }

    public function testGetNextReviewDateFromPageInheritedSetting()
    {
		// Although page-1-1 inherits from page-1, it has an independent review date
        $page = $this->objFromFixture("Page", "page-1-1");
        $nextReviewDate = $page->getReviewDate();
        $this->assertInstanceOf("Date", $nextReviewDate);
        $this->assertEquals('2011-04-12', $nextReviewDate->format("Y-m-d"));
    }

    public function testUpdateNextReviewDateFromCustomToDisabled()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "custom");

        // before write()
        $this->assertEquals("2010-02-01", $page->NextReviewDate);

        $page->ContentReviewType = "Disabled";
        $page->write();

        DataObject::flush_and_destroy_cache();
        unset($page);

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "custom");

        $this->assertNull($page->NextReviewDate);
    }

    public function testUpdateNextReviewDateFromDisabledToCustom()
    {
        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "disabled");

        $this->assertNull($page->NextReviewDate);

        $page->ContentReviewType = "Custom";
        $page->ReviewPeriodDays = "7";
        $page->write();

        DataObject::flush_and_destroy_cache();
        unset($page);

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture("Page", "disabled");

        $expected = date("Y-m-d", strtotime("+ " . $page->ReviewPeriodDays . " days"));

        $this->assertEquals($expected, $page->NextReviewDate);
    }

    public function testParentChangedOptionsAndChildShouldToo()
    {
        /** @var Page|SiteTreeContentReview $parentPage */
        $parentPage = $this->objFromFixture("Page", "page-1");

        /** @var Page|SiteTreeContentReview $childPage */
        $childPage = $this->objFromFixture("Page", "page-1-1");

		// Parent and child pages have different review dates
        $this->assertNotEquals($parentPage->NextReviewDate, $childPage->NextReviewDate);

        // But if we change the parent page ReviewPeriodDays to 10, the childs stays the same
        $parentPage->ReviewPeriodDays = 10;
        $parentPage->write();

        // Flush all the caches!
        DataObject::flush_and_destroy_cache();

        /** @var Page|SiteTreeContentReview $page */
        $parentPage = $this->objFromFixture("Page", "page-1");

        /** @var Page|SiteTreeContentReview $page */
        $childPage = $this->objFromFixture("Page", "page-1-1");

        // The parent page's date advances, but not the child's
        $this->assertEquals('2011-04-12', $childPage->NextReviewDate);
        $this->assertEquals($this->addDaysToDate(date("Y-m-d"), 10), $parentPage->NextReviewDate);

		// Reviewing the child page should, however, advance its review by 10 days
		$childPage->advanceReviewDate();
		$childPage->write();
        $this->assertEquals($this->addDaysToDate(date("Y-m-d"), 10), $childPage->NextReviewDate);
    }

    /**
     * @param string|SS_DateTime|DateTime $date
     * @param int                         $days
     * @param string                      $format
     *
     * @return bool|string
     */
    private function addDaysToDate($date, $days, $format = "Y-m-d")
    {
        if (is_object($date)) {
            $sec = strtotime("+ " . $days . " days", $date->format("U"));
        } else {
            $sec = strtotime("+ " . $days . " days", strtotime($date));
        }

        return date($format, $sec);
    }
}
