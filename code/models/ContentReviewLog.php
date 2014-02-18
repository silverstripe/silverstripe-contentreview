<?php

class ContentReviewLog extends DataObject {
	
	/**
	 *
	 * @var array
	 */
	private static $db = array(
		'Note' => 'Text'
	);
	
	/**
	 *
	 * @var array
	 */
	private static $has_one = array(
		'Reviewer' => 'Member',
		'SiteTree' => 'SiteTree'
	);
	
	/**
	 *
	 * @var array
	 */
	private static $summary_fields = array(
		'Note',
		'Created'
	);
}
