<?php

/**
 * @mixin PHPUnit_Framework_TestCase
 */
class ContentReviewNotificationTest extends SapphireTest
{
    /**
     * @var string
     */
    public static $fixture_file = "contentreview/tests/ContentReviewTest.yml";

    /**
     * @var array
     */
    protected $requiredExtensions = array(
        "SiteTree"              => array("SiteTreeContentReview"),
        "Group"                 => array("ContentReviewOwner"),
        "Member"                => array("ContentReviewOwner"),
        "CMSPageEditController" => array("ContentReviewCMSExtension"),
        "SiteConfig"            => array("ContentReviewDefaultSettings"),
    );

    public function testContentReviewEmails()
    {
        SS_Datetime::set_mock_now("2010-02-24 12:00:00");

        /** @var Page|SiteTreeContentReview $childParentPage */
        $childParentPage = $this->objFromFixture("Page", "contact");
        $childParentPage->NextReviewDate = "2010-02-23";
        $childParentPage->write();

        $task = new ContentReviewEmails();
        $task->run(new SS_HTTPRequest("GET", "/dev/tasks/ContentReviewEmails"));

        $expectedSubject = _t("ContentReviewEmails.SUBJECT", "Page(s) are due for content review");
        $email = $this->findEmail("author@example.com", null, $expectedSubject);

        $this->assertNotNull($email, "Email haven't been sent.");
        $this->assertContains("There are 3 pages that are due for review today by you.", $email["htmlContent"]);
        $this->assertContains("Staff", $email["htmlContent"]);
        $this->assertContains("Contact Us", $email["htmlContent"]);
        $this->assertContains("Contact Us Child", $email["htmlContent"]);

        SS_Datetime::clear_mock_now();
    }
}
