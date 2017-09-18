<?php

namespace SilverStripe\ContentReview\Extensions;

use SilverStripe\Admin\LeftAndMainExtension;

class ContentReviewLeftAndMainExtension extends LeftAndMainExtension
{
    /**
     * Append content review schema configuration
     *
     * @param array &$clientConfig
     */
    public function updateClientConfig(&$clientConfig)
    {
        $clientConfig['form']['ReviewContentForm'] = [
            'schemaUrl' => $this->owner->Link('schema/ReviewContentForm')
        ];
    }
}
