<?php
require_once 'Zend/Date.php';

/**
 * Show all pages that need to be reviewed
 *
 * @package contentreview
 */
class PagesDueForReviewReport extends SS_Report {

	/**
	 * 
	 * @return string
	 */
	public function title() {
		return _t('PagesDueForReviewReport.TITLE', 'Pages due for review');
	}

	/**
	 * 
	 * @return \FieldList
	 */
	public function parameterFields() {
		$filtersList = new FieldList();

		$filtersList->push(
			DateField::create('ReviewDateAfter', 'Review date after or on')
				->setConfig('showcalendar', true)
		);
		$filtersList->push(
			DateField::create('ReviewDateBefore', 'Review date before or on', date('d/m/Y', strtotime('midnight')))
				->setConfig('showcalendar', true)
		);

		$filtersList->push(new CheckboxField('ShowVirtualPages', 'Show Virtual Pages'));

		return $filtersList;
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
				'casting' => 'Date->Full',
				'formatting' => function($value, $item) {
					if($item->ContentReviewType == 'Disabled') {
						return 'disabled';
					}
					if($item->ContentReviewType == 'Inherit') {
						$setting = $item->getOptions();
						if(!$setting) {
							return 'disabled';
						}
						return $item->obj('NextReviewDate')->Full();
					}
					return $value;
				}
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
			),
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

		if(empty($params['ReviewDateBefore']) && empty($params['ReviewDateAfter'])) {
			// If there's no review dates set, default to all pages due for review now
			$reviewDate = new Zend_Date(SS_Datetime::now()->Format('U'));
			$reviewDate->add(1, Zend_Date::DAY);
			$records = $records->where(sprintf('"NextReviewDate" < \'%s\'', $reviewDate->toString('YYYY-MM-dd')));
		} else {
			// Review date before
			if(!empty($params['ReviewDateBefore'])) {
				// TODO Get value from DateField->dataValue() once we have access to form elements here
				$reviewDate = new Zend_Date($params['ReviewDateBefore'], i18n::get_date_format());
				$reviewDate->add(1, Zend_Date::DAY);
				$records = $records->where(sprintf('"NextReviewDate" < \'%s\'', $reviewDate->toString('YYYY-MM-dd')));
			}

			// Review date after
			if(!empty($params['ReviewDateAfter'])) {
				// TODO Get value from DateField->dataValue() once we have access to form elements here
				$reviewDate = new Zend_Date($params['ReviewDateAfter'], i18n::get_date_format());
				$records = $records->where(sprintf('"NextReviewDate" >= \'%s\'', $reviewDate->toString('YYYY-MM-dd')));
			}
		}

		// Show virtual pages?
		if(empty($params['ShowVirtualPages'])) {
			$virtualPageClasses = ClassInfo::subclassesFor('VirtualPage');
			$records = $records->where(sprintf(
				'"SiteTree"."ClassName" NOT IN (\'%s\')',
				implode("','", array_values($virtualPageClasses))
			));
		}

		// Owner dropdown
		if(!empty($params['ContentReviewOwner'])) {
			$ownerNames = Convert::raw2sql($params['ContentReviewOwner']);
			$records = $records->filter('OwnerNames:PartialMatch', $ownerNames);
		}
		
		return $records->sort('NextReviewDate', 'DESC');
	}
}