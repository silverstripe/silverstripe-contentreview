<?php

class ContentReviewReportTest extends FunctionalTest {
	
	public static $fixture_file = 'contentreview/tests/ContentReviewTest.yml';
	
	public function testReportContent() {
		$this->markTestIncomplete();
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
	
}

