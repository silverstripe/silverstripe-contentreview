<?php

namespace SilverStripe\ContentReview\Tests\Forms;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ContentReview\Forms\ReviewContentHandler;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Security\Member;

class ReviewContentHandlerTest extends SapphireTest
{
    public function testForm()
    {
        $page = Page::create();
        $page->Title = 'Test';
        $page->write();

        $form = ReviewContentHandler::create()->Form($page);

        $this->assertInstanceOf(Form::class, $form);
        $this->assertSame('ReviewContentForm', $form->getName());

        $this->assertInstanceOf(HiddenField::class, $form->Fields()->fieldByName('ID'));
        $this->assertInstanceOf(HiddenField::class, $form->Fields()->fieldByName('ClassName'));
        $this->assertInstanceOf(TextareaField::class, $form->Fields()->fieldByName('Review'));

        $saveAction = $form->Actions()->first();
        $this->assertNotNull($saveAction);
        $this->assertTrue($saveAction->hasClass('review-content__action'));
    }

    /**
     * @expectedException SilverStripe\ORM\ValidationException
     * @expectedExceptionMessage It seems you don't have the necessary permissions to submit a content review
     */
    public function testExceptionThrownWhenSubmittingReviewForInvalidObject()
    {
        ReviewContentHandler::create()->submitReview(new Member, ['foo' => 'bar']);
    }

    public function testAddReviewNoteCalledWhenSubmittingReview()
    {
        $this->logInWithPermission('ADMIN');

        $controller = new Controller;
        $request = new HTTPRequest('GET', '/');
        $controller->setRequest($request);
        Injector::inst()->registerservice($request);

        $mock = $this->getMockBuilder(ReviewContentHandler::class)
            ->setConstructorArgs([$controller])
            ->setMethods(['canSubmitReview'])
            ->getMock();

        $mock->expects($this->exactly(3))->method('canSubmitReview')->willReturn(true);

        // Via CMS
        $request->addHeader('X-Formschema-Request', true);
        $result = $mock->submitReview(new SiteTree, ['Review' => 'testing']);
        $this->assertSame('Review successfully added', $result);
        $request->removeHeader('X-Formschema-Request');

        // Via AJAX
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');
        $result = $mock->submitReview(new SiteTree, ['Review' => 'testing']);
        $this->assertInstanceOf(HTTPResponse::class, $result);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('Review successfully added', $result->getBody());
        $request->removeHeader('X-Requested-With');

        // Default
        $result = $mock->submitReview(new SiteTree, ['Review' => 'testing']);
        $this->assertInstanceOf(HTTPResponse::class, $result);
        $this->assertSame(302, $result->getStatusCode());
    }
}
