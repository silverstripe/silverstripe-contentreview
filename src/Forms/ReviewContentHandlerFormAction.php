<?php

namespace SilverStripe\ContentReview\Forms;

use SilverStripe\Forms\FormAction;

class ReviewContentHandlerFormAction extends FormAction
{
    public function __construct()
    {
        parent::__construct(
            'submitReview',
            _t('SilverStripe\\ContentReview\\Forms\\ReviewContentHandler.MarkAsReviewedAction', 'Mark as reviewed')
        );

        $this->setUseButtonTag(false)
            ->addExtraClass('review-content-action btn btn-primary');
    }
}
