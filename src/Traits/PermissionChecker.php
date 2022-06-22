<?php

namespace SilverStripe\ContentReview\Traits;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

trait PermissionChecker
{
    /**
     * Checks the user has been granted special permission to review the content of the page
     * if not fallback to canEdit() permission.
     */
    protected function isContentReviewable(DataObject $record, ?Member $user = null): bool
    {
        return $record->hasMethod('canReviewContent')
            ? $record->canReviewContent($user)
            : $record->canEdit();
    }
}
