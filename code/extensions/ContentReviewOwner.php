<?php

/**
 * Description of GroupContentReview
 *
 * @codeCoverageIgnore
 */
class ContentReviewOwner extends DataExtension {
	
	/**
	 *
	 * @var array
	 */
	private static $many_many = array(
		"SiteTreeContentReview" => "SiteTree"
	);
}
