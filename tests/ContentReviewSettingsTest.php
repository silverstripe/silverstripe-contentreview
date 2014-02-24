<?php

/**
 * This class tests that settings are inherited correctly based on the inherited, custom or disabled settings
 */
class ContentReviewSettingsTest extends SapphireTest {

	public static $fixture_file = 'contentreview/tests/ContentReviewSettingsTest.yml';
	
	public function testAdvanceReviewFromCustomSettings() {
		$page = $this->objFromFixture('Page', 'custom');
		$this->assertTrue($page->advanceReviewDate());
		$page->write();
		$this->assertEquals(date('Y-m-d', strtotime('now + '.$page->ReviewPeriodDays.' days')), $page->NextReviewDate);
	}
	
	public function testAdvanceReviewFromInheritedSettings() {
		$page = $this->objFromFixture('Page', 'page-1-1');
		$parentPage = $this->objFromFixture('Page', 'page-1');
		$this->assertTrue($page->advanceReviewDate());
		$page->write();
		$this->assertEquals(date('Y-m-d', strtotime('now + '.$parentPage->ReviewPeriodDays.' days')), $page->NextReviewDate);
	}
	
	public function testAdvanceReviewFromInheritedSiteConfigSettings() {
		$page = $this->objFromFixture('Page', 'inherit');
		$siteConfig = $this->objFromFixture('SiteConfig', 'default');
		$this->assertTrue($page->advanceReviewDate());
		$page->write();
		$this->assertEquals(date('Y-m-d', strtotime('now + '.$siteConfig->ReviewPeriodDays.' days')), $page->NextReviewDate);
	}
	
	public function testGetSettingsObjectFromCustom() {
		$page = $this->objFromFixture('Page', 'custom');
		$this->assertEquals('Custom', $page->ContentReviewType);
		$this->assertEquals($page, $page->getOptions());
	}
	
	public function testGetSettingsObjectFromDisabled() {
		$page = $this->objFromFixture('Page', 'disabled');
		$this->assertEquals('Disabled', $page->ContentReviewType);
		$this->assertFalse($page->getOptions());
	}
	
	public function testGetSettingsObjectFromInheritPage() {
		$page = $this->objFromFixture('Page', 'page-1-1');
		$this->assertEquals('Inherit', $page->ContentReviewType);
		$this->assertEquals($this->objFromFixture('Page', 'page-1'), $page->getOptions());
	}

	public function testGetSettingsObjectFromInheritedRootPage() {
		$page = $this->objFromFixture('Page', 'inherit');
		$this->assertEquals('Inherit', $page->ContentReviewType);
		$this->assertEquals($this->objFromFixture('SiteConfig', 'default'), $page->getOptions());
	}
	
	public function testGetNextReviewDateFromCustomSettings() {
		$page = $this->objFromFixture('Page', 'custom');
		$date = $page->getReviewDate($page->getOptions(), $page);
		$this->assertEquals('2010-02-01', $date->format('Y-m-d'));
	}
	
	public function testGetNextReviewDateFromSiteConfigInheritedSetting() {
		$page = $this->objFromFixture('Page', 'inherit');
		$nextReviewDate = $page->getReviewDate($page->getOptions(), $page);
		
		$this->assertInstanceOf('Date', $nextReviewDate);
		$expected = $this->addDaysToDate(SS_Datetime::now(), $this->objFromFixture('SiteConfig', 'default')->ReviewPeriodDays);
		$this->assertEquals($expected , $nextReviewDate->format('Y-m-d'));
	}
	
	public function testGetNextReviewDateFromPageInheritedSetting() {
		$page = $this->objFromFixture('Page', 'page-1-1');
		$nextReviewDate = $page->getReviewDate($page->getOptions(), $page);
		
		$this->assertInstanceOf('Date', $nextReviewDate);
		// It should be the same as the parents reviewdate
		$expected = $this->objFromFixture('Page', 'page-1')->NextReviewDate;
		$this->assertEquals($expected, $nextReviewDate->format('Y-m-d'));
	}
	
	public function testUpdateNextReviewDateFromCustomToDisabled() {
		$page = $this->objFromFixture('Page', 'custom');
		// before write()
		$this->assertEquals('2010-02-01', $page->NextReviewDate);
		
		// Change and write
		$page->ContentReviewType = 'Disabled';
		$page->write();
		
		// clear cache
		DataObject::flush_and_destroy_cache();
		unset($page);
		
		// After write()
		$page = $this->objFromFixture('Page', 'custom');
		$this->assertNull($page->NextReviewDate);
	}
	
	public function testUpdateNextReviewDateFromDisabledToCustom() {
		$page = $this->objFromFixture('Page', 'disabled');
		// before
		$this->assertNull($page->NextReviewDate);
		
		// Change and write
		$page->ContentReviewType = 'Custom';
		$page->ReviewPeriodDays = '7';
		$page->write();
		// clear cache
		DataObject::flush_and_destroy_cache();
		unset($page);
		
		// After write()
		$page = $this->objFromFixture('Page', 'disabled');
		$expected = date('Y-m-d', strtotime('+ '.$page->ReviewPeriodDays.' days'));
		$this->assertEquals($expected, $page->NextReviewDate);
	}
	
	public function testParentChangedOptionsAndChildShouldToo() {
		$parentPage = $this->objFromFixture('Page', 'page-1');
		$childPage = $this->objFromFixture('Page', 'page-1-1');
		
		// BEFORE: parent page have a period of five days, so childPage should have a 
		// review date LastEdited + 5 days
		$expected = $this->addDaysToDate($childPage->obj('LastEdited'), $parentPage->ReviewPeriodDays);
		$this->assertEquals($parentPage->NextReviewDate, $childPage->NextReviewDate);
		
		$oldChildDate = $childPage->NextReviewDate;
		// But if we change the parent page ReviewPeriodDays to 10, the childs should 
		// change as well
		$parentPage->ReviewPeriodDays = 10;
		$parentPage->write();
		
		// Flush all the caches!
		DataObject::flush_and_destroy_cache();
		
		$parentPage = $this->objFromFixture('Page', 'page-1');
		$childPage = $this->objFromFixture('Page', 'page-1-1');
		
		// AFTER: parent page have a period of five days, so childPage should have a 
		// review date LastEdited + 5 days
		$this->assertNotEquals($oldChildDate, $childPage->NextReviewDate);
		$this->assertEquals($parentPage->NextReviewDate, $childPage->NextReviewDate);
	}
	
	// helper method for this test class
	private function addDaysToDate($date, $days, $format='Y-m-d') {
		$sec = strtotime('+ '. $days .' days', $date->format('U'));
		return date($format, $sec);
	}
}