<?php

class ContentReviewReportTest extends FunctionalTest {
	
	public static $fixture_file = 'contentreview/tests/ContentReviewTest.yml';
	
	protected $requiredExtensions = array(
		"SiteTree" => array("SiteTreeContentReview"),
		"Group" => array("ContentReviewOwner"),
		"Member" => array("ContentReviewOwner"),
		"CMSPageEditController" => array("ContentReviewCMSExtension"),
		"SiteConfig" => array("ContentReviewDefaultSettings"),
	);
	
	public function testPagesDueForReviewReport() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		$report = new PagesDueForReviewReport();
		
		$report->parameterFields();
		$report->columns();
		$report->title();
		
		$results = $report->sourceRecords(array(
			'ReviewDateAfter' => '01/01/2010',
			'ReviewDateBefore' => '12/12/2010'
		));

		$this->assertEquals(array(
			'Contact Us',
			'Contact Us Child',
			'Staff',
			'About Us',
			'Home'
		), $results->column('Title'));
		
		SS_Datetime::set_mock_now('2010-02-13 00:00:00');
		$results = $report->sourceRecords(array());
		$this->assertEquals(array(
			'About Us',
			'Home'
		), $results->column('Title'));
		
		SS_Datetime::clear_mock_now();
	}
	
	public function testPagesWithoutReviewScheduleReport() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		$report = new PagesWithoutReviewScheduleReport();
	
		$report->parameterFields();
		$report->columns();
		$report->title();
	
		$results = $report->sourceRecords();
	
		$this->assertEquals(array(
			'Home',
			'About Us',
			'Page without review date',
			'Page owned by group',
		), $results->column('Title'));
	}
	
}

