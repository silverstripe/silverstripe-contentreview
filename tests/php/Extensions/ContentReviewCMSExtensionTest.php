<?php

namespace SilverStripe\ContentReview\Tests\Extensions;

use SilverStripe\ContentReview\Extensions\ContentReviewCMSExtension;
use SilverStripe\ContentReview\Forms\ReviewContentHandler;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\Form;
use SilverStripe\Security\Member;

class ContentReviewCMSExtensionTest extends SapphireTest
{
    /**
     * Test that ReviewContentForm finds an ID parameter then returns the result of getReviewContentForm
     * with the passed ID
     */
    public function testReviewContentForm()
    {
        $mock = $this->getMockBuilder(ContentReviewCMSExtension::class)
            ->setMethods(['getReviewContentForm'])
            ->getMock();

        $mock->expects($this->once())->method('getReviewContentForm')->with(123)->willReturn(true);

        $request = new HTTPRequest('GET', '/', [], ['ID' => 123]);
        $result = $mock->ReviewContentForm($request);
        $this->assertTrue($result);
    }

    public function testGetReviewContentFormThrowsExceptionWhenPageNotFound()
    {
        $this->expectException(HTTPResponse_Exception::class);
        $this->expectExceptionMessage('Bad record ID #1234');
        (new ContentReviewCMSExtension)->getReviewContentForm(1234);
    }

    public function testGetReviewContentFormThrowsExceptionWhenObjectCannotBeReviewed()
    {
        $this->expectException(HTTPResponse_Exception::class);
        $this->expectExceptionMessage('It seems you don\'t have the necessary permissions to review this content');
        $this->logOut();

        $mock = $this->getMockBuilder(ContentReviewCMSExtension::class)
            ->setMethods(['findRecord'])
            ->getMock();

        $mock->setOwner(new Controller);

        // Return a DataObject without the content review extension applied
        $mock->expects($this->once())->method('findRecord')->with(['ID' => 123])->willReturn(new Member);

        $mock->getReviewContentForm(123);
    }

    /**
     * Ensure that savereview() calls the ReviewContentHandler and passes the data to it
     */
    public function testSaveReviewCallsHandler()
    {
        $mock = $this->getMockBuilder(ContentReviewCMSExtension::class)
            ->setMethods(['findRecord', 'getReviewContentHandler'])
            ->getMock();

        $mock->setOwner(new Controller);

        $mockPage = (object) ['ID' => 123];
        $mock->expects($this->once())->method('findRecord')->willReturn($mockPage);

        $mockHandler = $this->getMockBuilder(ReviewContentHandler::class)
            ->setMethods(['submitReview'])
            ->getMock();

        $mockHandler->expects($this->once())
            ->method('submitReview')
            ->with($mockPage, ['foo'])
            ->willReturn('Success');

        $mock->expects($this->once())->method('getReviewContentHandler')->willReturn($mockHandler);

        $form = $this->getMockBuilder(Form::class)
            ->disableOriginalConstructor()
            ->getMock();

        $result = $mock->savereview(['foo'], $form);
        $this->assertSame('Success', $result);
    }
}
