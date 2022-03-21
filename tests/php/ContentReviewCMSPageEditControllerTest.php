<?php

namespace SilverStripe\ContentReview\Tests;

use Page;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ContentReview\Extensions\SiteTreeContentReview;
use SilverStripe\ContentReview\Extensions\ContentReviewOwner;
use SilverStripe\ContentReview\Extensions\ContentReviewCMSExtension;
use SilverStripe\ContentReview\Extensions\ContentReviewDefaultSettings;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;

class ContentReviewCMSPageEditControllerTest extends ContentReviewBaseTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'ContentReviewTest.yml';

    /**
     * @var array
     */
    protected static $required_extensions = [
        SiteTree::class              => [SiteTreeContentReview::class],
        Group::class                 => [ContentReviewOwner::class],
        Member::class                => [ContentReviewOwner::class],
        CMSPageEditController::class => [ContentReviewCMSExtension::class],
        SiteConfig::class            => [ContentReviewDefaultSettings::class],
    ];

    public function testReviewedThrowsExceptionWithNoRecordID()
    {
        $this->expectException(HTTPResponse_Exception::class);

        /** @var CMSPageEditController|ContentReviewCMSExtension $controller */
        $controller = new CMSPageEditController();

        $dummyForm = new Form($controller, "EditForm", new FieldList(), new FieldList());

        $controller->savereview(array(
            "ID"      => null,
            "Message" => null,
        ), $dummyForm);
    }

    public function testReviewedThrowsExceptionWithWrongRecordID()
    {
        $this->expectException(HTTPResponse_Exception::class);

        /** @var CMSPageEditController|ContentReviewCMSExtension $controller */
        $controller = new CMSPageEditController();

        $dummyForm = new Form($controller, "EditForm", new FieldList(), new FieldList());

        $controller->savereview(array(
            "ID"      => "FAIL",
            "Message" => null,
        ), $dummyForm);
    }

    public function testReviewedWithAuthor()
    {
        /** @var Member $author */
        $author = $this->objFromFixture(Member::class, "author");

        $this->logInAs($author);

        /** @var Page|SiteTreeContentReview $page */
        $page = $this->objFromFixture(Page::class, "home");

        $data = array(
            "action_savereview" => 1,
            "ID" => $page->ID,
        );

        $this->get('admin/pages/edit/show/' . $page->ID);
        $response = $this->post($this->getFormAction($page), $data);

        $this->assertEquals("OK", $response->getStatusDescription());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Return a CMS page edit form action via using a dummy request and session
     *
     * @param Page $page
     * @return string
     */
    protected function getFormAction(Page $page)
    {
        $controller = singleton(CMSPageEditController::class);
        $controller->setRequest(new HTTPRequest('GET', '/'));
        $controller->getRequest()->setSession($this->session());

        return $controller->getEditForm($page->ID)->FormAction();
    }
}
