<?php
require_once 'Zend/Date.php';

/**
 * Show all pages that need to be reviewed
 *
 * @package contentreview
 */
class PagesWithoutReviewScheduleReport extends SS_Report {

	/**
	 * 
	 * @return string
	 */
	public function title() {
		return _t('PagesWithoutReviewScheduleReport.TITLE', 'Pages without a scheduled review.');
	}

	/**
	 * 
	 * @return \FieldList
	 */
	public function parameterFields() {
		$params = new FieldList();

		// We need to be a bit fancier when subsites is enabled
		if(class_exists('Subsite') && Subsite::get()->count()) {
			die('No subsite support yet!');
		}
		$params->push(new CheckboxField('ShowVirtualPages', 'Show Virtual Pages'));
		return $params;
	}

	/**
	 * 
	 * @return array
	 */
	public function columns() {
		$linkBase = singleton('CMSPageEditController')->Link('show') . '/';
		$fields = array(
			'Title' => array(
				'title' => 'Page name',
				'formatting' => '<a href=\"' . $linkBase . '/$ID\" title=\"Edit page\">$value</a>'
			),
			'NextReviewDate' => array(
				'title' => 'Review Date',
				'casting' => 'Date->Full'
			),
			'OwnerNames' => array(
				'title' => 'Owner'
			),
			'LastEditedByName' => 'Last edited by',
			'AbsoluteLink' => array(
				'title' => 'URL',
				'formatting' => function($value, $item) {
					$liveLink = $item->AbsoluteLiveLink;
					$stageLink = $item->AbsoluteLink();
					return sprintf('%s <a href="%s">%s</a>',
						$stageLink,
						$liveLink ? $liveLink : $stageLink . '?stage=Stage',
						$liveLink ? '(live)' : '(draft)'
					);
				}
			)
		);

		return $fields;
	}

	/**
	 * 
	 * @param array $params
	 * @param string $sort
	 * @param array $limit
	 * @return DataList
	 */
	public function sourceRecords($params, $sort, $limit) {
		$records = SiteTree::get();

		// If there's no review dates set, default to all pages due for review now
		$records = $records->where('"NextReviewDate" IS NULL OR "OwnerNames" IS NULL OR "OwnerNames" = \'\'');

		// Show virtual pages?
		if(empty($params['ShowVirtualPages'])) {
			$virtualPageClasses = ClassInfo::subclassesFor('VirtualPage');
			$records = $records->where(sprintf(
				'"SiteTree"."ClassName" NOT IN (\'%s\')',
				implode("','", array_values($virtualPageClasses))
			));
		}

		// Turn a query into records
		if($sort) {
			$parts = explode(' ', $sort);
			$field = $parts[0];
			$direction = $parts[1];

			if($field == 'AbsoluteLink') {
				$sort = '"URLSegment" ' . $direction;
			} elseif($field == 'Subsite.Title') {
				$records = $records->leftJoin("Subsite", '"Subsite"."ID" = "SiteTree"."SubsiteID"');
			}

			if($field != "LastEditedByName") {
				$records = $records->sort($sort);
			}

			if($limit) $records = $records->limit($limit['limit'], $limit['start']);
		}

		return $records;
	}
}