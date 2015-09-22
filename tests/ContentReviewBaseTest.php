<?php 

/**
 * Extend this class when writing unit tests which are compatable with other modules. All
 * compatability code goes here.
 */
abstract class ContentReviewBaseTest extends FunctionalTest {
	
	protected $translatableEnabledBefore;
	
	public function setUp(){
		parent::setUp();
		/*
		 *  We set the locale for pages explicitly, because if we don't, then we get into a situation
		 *  where the page takes on the tester's (your) locale, and any calls to simulate subsequent requests
		 *  (e.g. $this->post()) do not seem to get passed the tester's locale, but instead fallback to the default locale.
		 *
		 *  So we set the pages locale to be the default locale, which will then match any subsequent requests.
		 *  
		 *  If creating pages in your unit tests (rather than reading from the fixtures file), you must explicitly call
		 *  self::compat() on the page, for the same reasons as above.
		*/
		if(class_exists('Translatable')){
			fwrite(STDOUT, 'TRANSLATABLE DISABLED FFS');
			$this->translatableEnabledBefore = Translatable::is_enabled();
			Translatable::disable();
		}
	}
	
	public function tearDown(){
		if(class_exists('Translatable')){
			if($this->translatableEnabledBefore) Translatable::enable();
		}
	}
	
}