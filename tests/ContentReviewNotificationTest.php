<?php

/**
 * @mixin PHPUnit_Framework_TestCase
 */
class ContentReviewNotificationTest extends SapphireTest
{
    /**
     * @var string
     */
    public static $fixture_file = 'contentreview/tests/ContentReviewTest.yml';

    public function setUp()
    {
        parent::setUp();

        // Hack to ensure only desired siteconfig is scaffolded
        $desiredID = $this->idFromFixture('SiteConfig', 'mysiteconfig');
        foreach (SiteConfig::get()->exclude('ID', $desiredID) as $config) {
            $config->delete();
        }
    }

    /**
     * @var array
     */
    protected $requiredExtensions = array(
        'SiteTree' => array('SiteTreeContentReview'),
        'Group' => array('ContentReviewOwner'),
        'Member' => array('ContentReviewOwner'),
        'CMSPageEditController' => array('ContentReviewCMSExtension'),
        'SiteConfig' => array('ContentReviewDefaultSettings'),
    );

    public function testContentReviewEmails()
    {
        SS_Datetime::set_mock_now('2010-02-24 12:00:00');

        /** @var Page|SiteTreeContentReview $childParentPage */
        $childParentPage = $this->objFromFixture('Page', 'contact');
        $childParentPage->NextReviewDate = '2010-02-23';
        $childParentPage->write();

        $task = new ContentReviewEmails();
        $task->run(new SS_HTTPRequest('GET', '/dev/tasks/ContentReviewEmails'));

        // Set template variables (as per variable case)
        $ToEmail = 'author@example.com';
        $Subject = 'Please log in to review some content!';
        $PagesCount = 3;
        $ToFirstName = 'Test';

        $email = $this->findEmail($ToEmail, null, $Subject);
        $this->assertNotNull($email, "Email haven't been sent.");
        $this->assertContains(
            "<h1>$Subject</h1><p>There are $PagesCount pages that are due for review today by you, $ToFirstName.</p><p>This email was sent to $ToEmail</p>",
            $email['htmlContent']
        );
        $this->assertContains('Staff', $email['htmlContent']);
        $this->assertContains('Contact Us', $email['htmlContent']);
        $this->assertContains('Contact Us Child', $email['htmlContent']);

        SS_Datetime::clear_mock_now();
    }
}
