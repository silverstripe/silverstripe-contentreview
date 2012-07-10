<?php
require_once 'Zend/Date.php';

/**
 * Show all pages that need to be reviewed
 *
 * @package contentreview
 */
class PagesDueForReviewReport extends SS_Report {
	function title() {
		return _t('PagesDueForReviewReport.TITLE', 'Pages due for review');
	}

	function parameterFields() {
		$params = new FieldList();

		// We need to be a bit fancier when subsites is enabled
		if(class_exists('Subsite') && $subsites = DataObject::get('Subsite')) {

			// javascript for subsite specific owner dropdown
			Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
			Requirements::javascript('contentreview/javascript/PagesDueForReview.js');

			// Remember current subsite
			$existingSubsite = Subsite::currentSubsiteID();

			$map = array();

			// Create a map of all potential owners from all applicable sites
			$sites = Subsite::accessible_sites('CMS_ACCESS_CMSMain');
			foreach($sites as $site) {
				Subsite::changeSubsite($site);

				$cmsUsers = Permission::get_members_by_permission(array("CMS_ACCESS_CMSMain", "ADMIN"));
				// Key-preserving merge
				foreach($cmsUsers->map('ID', 'Title') as $k => $v) {
					$map[$k] = $v;
				}
			}

			$map = $map + array('' => 'Any', '-1' => '(no owner)');

			$params->push(new DropdownField("OwnerID", 'Page owner', $map));

			// Restore current subsite
			Subsite::changeSubsite($existingSubsite);
		} else {
			$cmsUsers = Permission::get_members_by_permission(array("CMS_ACCESS_CMSMain", "ADMIN"));
			$map = $cmsUsers->map('ID', 'Title', '(no owner)')->toArray();
			unset($map['']);
			$map = array('' => 'Any', '-1' => '(no owner)') + $map;
			$params->push(new DropdownField("OwnerID", 'Page owner', $map));
		}

		$params->push(
			DateField::create('ReviewDateAfter', 'Review date after or on')
				->setConfig('showcalendar', true)
		);
		$params->push(
			DateField::create('ReviewDateBefore', 'Review date before or on', date('d/m/Y', strtotime('midnight')))
				->setConfig('showcalendar', true)	
		);

		$params->push(new CheckboxField('ShowVirtualPages', 'Show Virtual Pages'));

		return $params;
	}

	function columns() {
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

	function sourceRecords($params, $sort, $limit) {
		$records = SiteTree::get();

		$wheres = array();

		if(empty($params['ReviewDateBefore']) && empty($params['ReviewDateAfter'])) {
			// If there's no review dates set, default to all pages due for review now
			$reviewDate = new Zend_Date(SS_Datetime::now()->Format('U'));
			$reviewDate->add(1, Zend_Date::DAY);
			$records->where(sprintf('"NextReviewDate" < \'%s\'', $reviewDate->toString('YYYY-MM-dd')));
		} else {
			// Review date before
			if(!empty($params['ReviewDateBefore'])) {
				// TODO Get value from DateField->dataValue() once we have access to form elements here
				$reviewDate = new Zend_Date($params['ReviewDateBefore'], i18n::get_date_format());
				$reviewDate->add(1, Zend_Date::DAY);
				$records->where(sprintf('"NextReviewDate" < \'%s\'', $reviewDate->toString('YYYY-MM-dd')));
			}

			// Review date after
			if(!empty($params['ReviewDateAfter'])) {
				// TODO Get value from DateField->dataValue() once we have access to form elements here
				$reviewDate = new Zend_Date($params['ReviewDateAfter'], i18n::get_date_format());
				$records->where(sprintf('"NextReviewDate" >= \'%s\'', $reviewDate->toString('YYYY-MM-dd')));
			}
		}

		// Show virtual pages?
		if(empty($params['ShowVirtualPages'])) {
			$virtualPageClasses = ClassInfo::subclassesFor('VirtualPage');
			$records->where(sprintf(
				'"SiteTree"."ClassName" NOT IN (\'%s\')',
				implode("','", array_values($virtualPageClasses))
			));
		}

		// Owner dropdown
		if(!empty($params['OwnerID'])) {
			$ownerID = (int)$params['OwnerID'];
			// We use -1 here to distinguish between No Owner and Any
			if($ownerID == -1) $ownerID = 0;
			$records->addFilter(array('OwnerID' => $ownerID));
		}

		// Turn a query into records
		if($sort) {
			$parts = explode(' ', $sort);
			$field = $parts[0];
			$direction = $parts[1];

			if($field == 'AbsoluteLink') {
				$sort = '"URLSegment" ' . $direction;
			} elseif($field == 'Subsite.Title') {
				$records->leftJoin("Subsite", '"Subsite"."ID" = "SiteTree"."SubsiteID"');
			}

			if($field != "LastEditedByName") {
				$records->sort($sort);
			}

			if($limit) $records->limit($limit['start'], $limit['limit']);
		}

		return $records;
	}
}