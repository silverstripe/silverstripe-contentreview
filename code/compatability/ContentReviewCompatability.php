<?php 

/**
 * This is a helper class which lets us do things with content review data without subsites 
 * and translatable messing our SQL queries up.
 * 
 * Make sure any DataQuery-ies you are building are BOTH created & executed between start() 
 * and done() because augmentDataQueryCreate and augmentSQL happens there.
 */
class ContentReviewCompatability {

	const SUBSITES     = 0;
	const TRANSLATABLE = 1;
	
	/**
	 * @return array - state before compatability mode started, to be passed into done().
	 */
	public static function start(){
		$compat = array(
			self::SUBSITES     => null,
			self::TRANSLATABLE => null
		);
		if(ClassInfo::exists('Subsite')){
			$compat[self::SUBSITES] = Subsite::$disable_subsite_filter;
			Subsite::disable_subsite_filter(true);
		}
		if(ClassInfo::exists('Translatable')){
			$compat[self::TRANSLATABLE] = Translatable::locale_filter_enabled();
			Translatable::disable_locale_filter();
		}
		return $compat;
	}
	
	/**
	 * @param array $compat - see start()
	 */
	public static function done(array $compat){
		if(class_exists('Subsite')){
			Subsite::$disable_subsite_filter = $compat[self::SUBSITES];
		}
		if(class_exists('Translatable')){
			Translatable::enable_locale_filter($compat[self::TRANSLATABLE]);
		}
	}
	
}