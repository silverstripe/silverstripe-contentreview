<?php

/**
 * This is a helper class which lets us do things with content review data without subsites
 * and translatable messing our SQL queries up.
 *
 * Make sure any DataQuery instances you are building are BOTH created & executed between start()
 * and done() because augmentDataQueryCreate and augmentSQL happens there.
 */
class ContentReviewCompatability
{
    const SUBSITES = 0;

    const TRANSLATABLE = 1;

    /**
     * Returns the state of other modules before compatibility mode is started.
     *
     * @return array
     */
    public static function start()
    {
        $compatibility = array(
            self::SUBSITES     => null,
            self::TRANSLATABLE => null,
        );

        if (ClassInfo::exists("Subsite")) {
            $compatibility[self::SUBSITES] = Subsite::$disable_subsite_filter;
            Subsite::disable_subsite_filter(true);
        }

        if (ClassInfo::exists("Translatable")) {
            $compatibility[self::TRANSLATABLE] = Translatable::locale_filter_enabled();
            Translatable::disable_locale_filter();
        }

        return $compatibility;
    }

    /**
     * @param array $compatibility
     */
    public static function done(array $compatibility)
    {
        if (class_exists("Subsite")) {
            Subsite::$disable_subsite_filter = $compatibility[self::SUBSITES];
        }

        if (class_exists("Translatable")) {
            Translatable::enable_locale_filter($compatibility[self::TRANSLATABLE]);
        }
    }
}
