<?php

namespace SilverStripe\ContentReview\Traits;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

trait ReviewPermission
{
    /**
     * Whether or not a user is allowed use content review
     */
    protected function canUseReviewContent(DataObject $record, ?Member $user = null): bool
    {
        // Whether or not the user is a reviewer. User must be allowed to view the page
        $isReviewer = $record->canView($user) &&
            $record->hasMethod('canBeReviewedBy') &&
            $record->canBeReviewedBy($user);
        // Whether or not the user is allowed to review the content of the page
        // Fallback to canEdit as it the original implementation
        $canEdit = $record->hasMethod('canReviewContent') ? $record->canReviewContent($user) : $record->canEdit();

        return $canEdit || $isReviewer;
    }
}
