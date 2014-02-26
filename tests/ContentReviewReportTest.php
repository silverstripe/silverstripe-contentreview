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
		
		$this->assertEquals(array(
			'Contact Us',
			'Contact Us Child',
			'Staff',
			'About Us',
			'Home'
		), $results->column('Title'));
		
		SS_Datetime::set_mock_now('2010-02-13 00:00:00');
		$results = $report->sourceRecords(array(
		), 'NextReviewDate ASC', false);
		$this->assertEquals(array(
			'About Us',
			'Home'
		), $results->column('Title'));
		
		SS_Datetime::clear_mock_now();
	}
	
}

