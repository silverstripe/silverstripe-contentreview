<?php

namespace SilverStripe\ContentReview\Compatibility;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Subsites\Model\Subsite;

/**
 * This is a helper class which lets us do things with content review data without subsites
 * messing our SQL queries up.
 *
 * Make sure any DataQuery instances you are building are BOTH created & executed between start()
 * and done() because augmentDataQueryCreate and augmentSQL happens there.
 */
class ContentReviewCompatability
{
    const SUBSITES = 0;

    /**
     * Returns the state of other modules before compatibility mode is started.
     *
     * @return array
     */
    public static function start()
    {
        $compatibility = [
            self::SUBSITES => null,
        ];

        if (ClassInfo::exists(Subsite::class)) {
            $compatibility[self::SUBSITES] = Subsite::$disable_subsite_filter;
            Subsite::disable_subsite_filter(true);
        }

        return $compatibility;
    }

    /**
     * @param array $compatibility
     */
    public static function done(array $compatibility)
    {
        if (class_exists(Subsite::class)) {
            Subsite::$disable_subsite_filter = $compatibility[self::SUBSITES];
        }
    }
}
