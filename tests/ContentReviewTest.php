<?php

class ContentReviewTest extends FunctionalTest {

	public static $fixture_file = 'contentreview/tests/ContentReviewTest.yml';
	
	public function testContentReviewEmails() {
		SS_Datetime::set_mock_now('2010-02-14 12:00:00');
		
		$task = new ContentReviewEmails();
		$task->run(new SS_HTTPRequest('GET', '/dev/tasks/ContentReviewEmails'));
		
		$this->assertEmailSent('author@example.com', null, sprintf(_t('ContentReviewEmails.SUBJECT', 'Page %s due for content review'), 'Staff'));
		
		SS_Datetime::clear_mock_now();
	}
	
	public function testReportContent() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		$report = new PagesDueForReviewReport();
		
		$report->parameterFields();
		$report->columns();
		$report->title();
		
		$results = $report->sourceRecords(array(
			'ReviewDateAfter' => '01/01/2010',
			'ReviewDateBefore' => '12/12/2010'
		), 'NextReviewDate ASC', false);
		
		$this->assertEquals($results->column('Title'), array(
			'Home',
			'About Us',
			'Staff',
			'Contact Us'
		));
		
		SS_Datetime::set_mock_now('2010-02-13 00:00:00');
		$results = $report->sourceRecords(array(
		), 'NextReviewDate ASC', false);
		$this->assertEquals($results->column('Title'), array(
			'Home',
			'About Us'
		));
		
		SS_Datetime::clear_mock_now();
	}
	
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
