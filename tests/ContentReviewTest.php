<?php

class ContentReviewTest extends FunctionalTest {

	public static $fixture_file = 'contentreview/tests/ContentReviewTest.yml';
	
	public function testOwnerNames() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		
		$page = new Page();		
		$page->ReviewPeriodDays = 10;
		$page->ContentReviewUsers()->push($editor);
		$page->write();

		$this->assertTrue($page->doPublish());
		$this->assertEquals($page->OwnerNames, "Test Editor", 'Test Editor should be the owner');
		
		$page = $this->objFromFixture('Page', 'about');
		$page->ContentReviewOwnerID = 0;
		$page->write();
		
		$this->assertTrue($page->doPublish());
		$this->assertEquals('', $page->OwnerNames);
	}
}
