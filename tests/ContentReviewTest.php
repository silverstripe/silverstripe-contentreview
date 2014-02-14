<?php

class ContentReviewTest extends FunctionalTest {
	
	/**
	 *
	 * @var string
	 */
	public static $fixture_file = 'contentreview/tests/ContentReviewTest.yml';
	
	public function testPermissionsExists() {
		$perms = singleton('SiteTreeContentReview')->providePermissions();
		$this->assertTrue(isset($perms['EDIT_CONTENT_REVIEW_FIELDS']));
	}
	
	public function testUserWithPermissionCanEdit() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		$page = new Page();
		$fields = $page->getCMSFields();
		$this->assertNotNull($fields->fieldByName('Root.Review'));
	}
	
	public function testUserWithoutPermissionCannotEdit() {
		$author = $this->objFromFixture('Member', 'author');
		$this->logInAs($author);
		$page = new Page();
		$fields = $page->getCMSFields();
		$this->assertNull($fields->fieldByName('Root.Review'));
	}
	
	public function testContentReviewEmails() {
		SS_Datetime::set_mock_now('2010-02-14 12:00:00');
		
		$task = new ContentReviewEmails();
		$task->run(new SS_HTTPRequest('GET', '/dev/tasks/ContentReviewEmails'));
		
		$this->assertEmailSent('author@example.com', null, sprintf(_t('ContentReviewEmails.SUBJECT', 'Page %s due for content review'), 'Staff'));
		
		SS_Datetime::clear_mock_now();
	}
	
	public function testAutomaticallySettingReviewDate() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		
		$page = new Page();
		$page->ReviewPeriodDays = 10;
		$page->write();
		$this->assertTrue($page->doPublish());		
		$this->assertEquals(date('Y-m-d', strtotime('now + 10 days')), $page->NextReviewDate);
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
	
	public function testCanNotBeReviewBecauseNoReviewDate() {
		SS_Datetime::set_mock_now('2010-01-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'no-review');
		// page 'no-review' is owned by author, but there is no review date
		$this->assertFalse($page->canBeReviewedBy($author));
	}
	
	public function testCanNotBeReviewedBecauseInFuture() {
		SS_Datetime::set_mock_now('2010-01-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'staff');
		// page 'staff' is owned by author, but the review date is in the future
		$this->assertFalse($page->canBeReviewedBy($author));
	}
	
	public function testCanNotBeReviewedByUser() {
		SS_Datetime::set_mock_now('2010-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'home');
		// page 'home' doesnt have any owners
		$this->assertFalse($page->canBeReviewedBy($author));
	}
	
	public function testCanBeReviewedByUser() {
		SS_Datetime::set_mock_now('2010-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'staff');
		// page 'staff' is owned by author
		$this->assertTrue($page->canBeReviewedBy($author));
	}
	
	public function testCanNotBeReviewedByGroup() {
		SS_Datetime::set_mock_now('2010-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'editor');
		$page = $this->objFromFixture('Page', 'contact');
		// page 'contact' is owned by the authorgroup
		$this->assertFalse($page->canBeReviewedBy($author));
	}
	
	public function testCanBeReviewedByGroup() {
		SS_Datetime::set_mock_now('2010-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'contact');
		// page 'contact' is owned by the authorgroup
		$this->assertTrue($page->canBeReviewedBy($author));
	}
}
