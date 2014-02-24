<?php

/**
 * This class tests that settings are inherited correctly based on the inherited, custom or disabled settings
 */
class ContentReviewSettingsTest extends SapphireTest {
	
	public static $fixture_file = 'contentreview/tests/ContentReviewSettingsTest.yml';
	
	public function testGetSettingsObjectFromCustom() {
		$page = $this->objFromFixture('Page', 'custom');
		$this->assertEquals('Custom', $page->ContentReviewType);
		$setting = $page->getContentReviewSetting($page);
		$this->assertEquals($page, $setting);
	}
	
	public function testGetSettingsObjectFromDisabled() {
		$page = $this->objFromFixture('Page', 'disabled');
		$this->assertEquals('Disabled', $page->ContentReviewType);
		$setting = $page->getContentReviewSetting($page);
		$this->assertFalse($setting);
	}
	
	public function testGetSettingsObjectFromInheritPage() {
		$page = $this->objFromFixture('Page', 'page-1-1');
		$this->assertEquals('Inherit', $page->ContentReviewType);
		$settings = $page->getContentReviewSetting($page);
		$this->assertEquals($this->objFromFixture('Page', 'page-1'), $settings);
	}

	public function testGetSettingsObjectFromInheritedRootPage() {
		$page = $this->objFromFixture('Page', 'inherit');
		$this->assertEquals('Inherit', $page->ContentReviewType);
		$settings = $page->getContentReviewSetting($page);
		$this->assertEquals($this->objFromFixture('SiteConfig', 'default'), $settings);
	}
	
	public function testGetNextReviewDateFromCustomSettings() {
		$page = $this->objFromFixture('Page', 'custom');
		$settings = $page->getContentReviewSetting($page);
		$date = $page->getNextReviewDatePlease($settings, $page);
		$this->assertEquals('2010-02-01', $date->format('Y-m-d'));
	}
	
	public function testGetNextReviewDateFromSiteConfigInheritedSetting() {
		$page = $this->objFromFixture('Page', 'page-1-1');
		$settings = $page->getContentReviewSetting($page);
		$nextReviewDate = $page->getNextReviewDatePlease($settings, $page);
		$this->assertInstanceOf('Date', $nextReviewDate);
		
		$expected = strtotime('+ '.$settings->ReviewPeriodDays.' days', $page->obj('LastEdited')->format('U'));
		$this->assertEquals(date('Y-m-d', $expected), $nextReviewDate->format('Y-m-d'));
	}
	
	public function testGetNextReviewDateFromPageInheritedSetting() {
		$page = $this->objFromFixture('Page', 'inherit');
		$settings = $page->getContentReviewSetting($page);
		$nextReviewDate = $page->getNextReviewDatePlease($settings, $page);
		
		$this->assertInstanceOf('Date', $nextReviewDate);
		$expected = strtotime('+ '.$settings->ReviewPeriodDays.' days', $page->obj('LastEdited')->format('U'));
		$this->assertEquals(date('Y-m-d', $expected), $nextReviewDate->format('Y-m-d'));
	}
	
	
	
	
}