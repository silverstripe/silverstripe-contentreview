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
		$params->push(new CheckboxField('ShowVirtualPages', 'Show Virtual Pages'));
		return $params;
	}

	/**
	 * 
	 * @return array
	 */
	public function columns() {
		$linkBase = singleton('CMSPageEditController')->Link('show');
		$linkPath = parse_url($linkBase, PHP_URL_PATH);
		$linkQuery = parse_url($linkBase, PHP_URL_QUERY);

		$fields = array(
			'Title' => array(
				'title' => 'Page name',
				'formatting' => sprintf('<a href=\"%s/$ID?%s\" title=\"Edit page\">$value</a>', $linkPath, $linkQuery)
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
			),
			'ContentReviewType' => array(
				'title' => 'Settings are',
				'formatting' => function($value, $item) use($linkPath, $linkQuery) {
					if($item->ContentReviewType == 'Inherit')  {
						$options = $item->getOptions();
						if($options && $options instanceof SiteConfig) {
							return 'Inherited from <a href="admin/settings">Settings</a>';
						} elseif($options) {
							return sprintf(
								'Inherited from <a href="%s/%d?%s">%s</a>',
								$linkPath,
								$options->ID,
								$linkQuery,
								$options->Title
							);
						}
					}
					return $value;
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
		Versioned::reading_stage('Stage');
		$records = SiteTree::get();

		// If there's no review dates set, default to all pages due for review now
		// $records = $records->where('"NextReviewDate" IS NULL OR "OwnerNames" IS NULL OR "OwnerNames" = \'\'');

		// Show virtual pages?
		if(empty($params['ShowVirtualPages'])) {
			$virtualPageClasses = ClassInfo::subclassesFor('VirtualPage');
			$records = $records->where(sprintf(
				'"SiteTree"."ClassName" NOT IN (\'%s\')',
				implode("','", array_values($virtualPageClasses))
			));
		}
		
		
		$records->sort('ParentID');
		// Trim out calculated values
		$list = new ArrayList();
		foreach($records as $record) {
			if(!$this->hasReviewSchedule($record)) {
				$list->push($record);
			}
		}
		return $list;
	}
	
	/**
	 * 
	 * @param DataObject $record
	 * @return boolean
	 */
	protected function hasReviewSchedule(DataObject $record) {
		if(!$record->obj('NextReviewDate')->exists()) {
			return false;
		}

		$options = $record->getOptions();
		if($options->OwnerGroups()->count() == 0 && $options->OwnerUsers()->count() == 0) {
			return false;
		}

		return true;
	}
}