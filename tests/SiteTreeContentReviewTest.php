<?php

class SiteTreeContentReviewTest extends ContentReviewBaseTest {
	
	public static $fixture_file = 'contentreview/tests/ContentReviewTest.yml';
	
	protected $requiredExtensions = array(
		"SiteTree" => array("SiteTreeContentReview"),
		"Group" => array("ContentReviewOwner"),
		"Member" => array("ContentReviewOwner"),
		"CMSPageEditController" => array("ContentReviewCMSExtension"),
		"SiteConfig" => array("ContentReviewDefaultSettings"),
	);
	
	public function testOwnerNames() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		
		$page = new Page();		
		$page->ReviewPeriodDays = 10;
		$page->ContentReviewType ='Custom';
		$page->ContentReviewUsers()->push($editor);
		$page->write();

		$this->assertTrue($page->canPublish());
		$this->assertTrue($page->doPublish());
		$this->assertEquals($page->OwnerNames, "Test Editor", 'Test Editor should be the owner');
		
		$page = $this->objFromFixture('Page', 'about');
		$page->OwnerUsers()->removeAll();
		$page->write();
		
		$this->assertTrue($page->canPublish());
		$this->assertTrue($page->doPublish());
		$this->assertEquals('', $page->OwnerNames);
	}
	
	public function testPermissionsExists() {
		$perms = singleton('SiteTreeContentReview')->providePermissions();
		$this->assertTrue(isset($perms['EDIT_CONTENT_REVIEW_FIELDS']));
	}
	
	public function testUserWithPermissionCanEdit() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		$page = new Page();
		$fields = $page->getSettingsFields();
		$this->assertNotNull($fields->dataFieldByName('NextReviewDate'));
	}
	
	public function testUserWithoutPermissionCannotEdit() {
		$author = $this->objFromFixture('Member', 'author');
		$this->logInAs($author);
		$page = new Page();
		$fields = $page->getSettingsFields();
		$this->assertNull($fields->dataFieldByName('NextReviewDate'));
	}
	
	public function testAutomaticallyToNotSetReviewDate() {
		$editor = $this->objFromFixture('Member', 'editor');
		$this->logInAs($editor);
		
		$page = new Page();
		$page->ReviewPeriodDays = 10;
		$page->write();
		$this->assertTrue($page->doPublish());		
		$this->assertEquals(null, $page->NextReviewDate);
	}
	
	public function testAddReviewNote() {
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'home');
		$page->addReviewNote($author, 'This is a message');
		
		// Get the page again to make sure it's not only cached in memory
		$homepage = $this->objFromFixture('Page', 'home');
		$this->assertEquals(1, $homepage->ReviewLogs()->count());
		$this->assertEquals('This is a message', $homepage->ReviewLogs()->first()->Note);
	}
	
	public function testGetContentReviewOwners() {
		$page = $this->objFromFixture('Page', 'group-owned');
		$owners = $page->ContentReviewOwners();
		$this->assertEquals(1, $owners->count());
		$this->assertEquals('author@example.com', $owners->first()->Email);
	}
	
	public function testCanNotBeReviewBecauseNoReviewDate() {
		SS_Datetime::set_mock_now('2010-01-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'no-review');
		// page 'no-review' is owned by author, but there is no review date
		$this->assertFalse($page->canBeReviewedBy($author));
		SS_Datetime::clear_mock_now();
	}
	
	public function testCanNotBeReviewedBecauseInFuture() {
		SS_Datetime::set_mock_now('2010-01-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'staff');
		// page 'staff' is owned by author, but the review date is in the future
		$this->assertFalse($page->canBeReviewedBy($author));
		SS_Datetime::clear_mock_now();
	}
	
	public function testCanNotBeReviewedByUser() {
		SS_Datetime::set_mock_now('2010-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'home');
		// page 'home' doesnt have any owners
		$this->assertFalse($page->canBeReviewedBy($author));
		SS_Datetime::clear_mock_now();
	}
	
	public function testCanBeReviewedByUser() {
		SS_Datetime::set_mock_now('2010-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'staff');
		// page 'staff' is owned by author
		$this->assertTrue($page->canBeReviewedBy($author));
		SS_Datetime::clear_mock_now();
	}
	
	public function testCanNotBeReviewedByGroup() {
		SS_Datetime::set_mock_now('2010-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'editor');
		$page = $this->objFromFixture('Page', 'contact');
		// page 'contact' is owned by the authorgroup
		$this->assertFalse($page->canBeReviewedBy($author));
		SS_Datetime::clear_mock_now();
	}
	
	public function testCanBeReviewedByGroup() {
		SS_Datetime::set_mock_now('2010-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$page = $this->objFromFixture('Page', 'contact');
		// page 'contact' is owned by the authorgroup
		$this->assertTrue($page->canBeReviewedBy($author));
		SS_Datetime::clear_mock_now();
	}
	
	public function testCanBeReviewedFromInheritedSetting() {
		SS_Datetime::set_mock_now('2013-03-01 12:00:00');
		$author = $this->objFromFixture('Member', 'author');
		$parentPage = $this->objFromFixture('Page', 'contact');
		// This saves the parentPages.NextReview date to the child page
		$parentPage->NextReviewDate = '2013-01-01';
		$parentPage->write();
		
		$page = $this->objFromFixture('Page', 'contact-child');
		$this->assertTrue($page->canBeReviewedBy($author));
		SS_Datetime::clear_mock_now();
	}
	
	public function testReviewActionVisibleForAuthor() {
		SS_Datetime::set_mock_now('2020-03-01 12:00:00');
		$page = $this->objFromFixture('Page', 'contact');
		$author = $this->objFromFixture('Member', 'author');
		$this->logInAs($author);

		$fields = $page->getCMSActions();
		$this->assertNotNull($fields->fieldByName('action_reviewed'));
		SS_Datetime::clear_mock_now();
	}
	
	public function testReviewActionNotVisibleForEditor() {
		SS_Datetime::set_mock_now('2020-03-01 12:00:00');
		$page = $this->objFromFixture('Page', 'contact');
		$author = $this->objFromFixture('Member', 'editor');
		$this->logInAs($author);
		
		$fields = $page->getCMSActions();
		$this->assertNull($fields->fieldByName('action_reviewed'));
		SS_Datetime::clear_mock_now();
	}
	
}
