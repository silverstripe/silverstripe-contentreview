<?php

class ContentReviewNotificationTest extends SapphireTest {
	
	public function testContentReviewEmails() {
		$this->markTestIncomplete();
		SS_Datetime::set_mock_now('2010-02-14 12:00:00');
		
		$task = new ContentReviewEmails();
		$task->run(new SS_HTTPRequest('GET', '/dev/tasks/ContentReviewEmails'));
		
		$this->assertEmailSent('author@example.com', null, sprintf(_t('ContentReviewEmails.SUBJECT', 'Page %s due for content review'), 'Staff'));
		
		SS_Datetime::clear_mock_now();
	}
}
