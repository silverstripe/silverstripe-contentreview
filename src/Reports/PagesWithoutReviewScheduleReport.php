<?php

namespace SilverStripe\ContentReview\Reports;

use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\ContentReview\Compatibility\ContentReviewCompatability;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;
use SilverStripe\Reports\Report;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

/**
 * Show all pages that need to be reviewed.
 */
class PagesWithoutReviewScheduleReport extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return _t(__CLASS__ . ".TITLE", "Pages without a scheduled review.");
    }

    /**
     * @return FieldList
     */
    public function parameterFields()
    {
        $params = FieldList::create();
        $params->push(CheckboxField::create("ShowVirtualPages", "Show Virtual Pages"));

        return $params;
    }

    /**
     * @return array
     */
    public function columns()
    {
        $linkBase = singleton(CMSPageEditController::class)->Link("show");
        $linkPath = parse_url($linkBase ?? '', PHP_URL_PATH);
        $linkQuery = parse_url($linkBase ?? '', PHP_URL_QUERY);

        $fields = [
            "Title"             => [
                "title"      => "Page name",
                "formatting" => "<a href='{$linkPath}/\$ID?{$linkQuery}' title='Edit page'>\$value</a>",
            ],
            "NextReviewDate"    => [
                "title"   => "Review Date",
                "casting" => "Date->Full",
            ],
            "OwnerNames"        => [
                "title" => "Owner",
            ],
            "LastEditedByName"  => "Last edited by",
            "AbsoluteLink"      => [
                "title"      => "URL",
                "formatting" => function ($value, $item) {
                    $liveLink = $item->AbsoluteLiveLink;
                    $stageLink = $item->AbsoluteLink();

                    return sprintf(
                        "%s <a href='%s'>%s</a>",
                        $stageLink,
                        $liveLink ? $liveLink : $stageLink . "?stage=Stage",
                        $liveLink ? "(live)" : "(draft)"
                    );
                },
            ],
            "ContentReviewType" => [
                "title"      => "Settings are",
                "formatting" => function ($value, $item) use ($linkPath, $linkQuery) {
                    if ($item->ContentReviewType == "Inherit") {
                        $options = $item->getOptions();
                        if ($options && $options instanceof SiteConfig) {
                            return "Inherited from <a href='admin/settings'>Settings</a>";
                        } elseif ($options) {
                            return sprintf(
                                "Inherited from <a href='%s/%d?%s'>%s</a>",
                                $linkPath,
                                $options->ID,
                                $linkQuery,
                                $options->Title
                            );
                        }
                    }

                    return $value;
                },
            ],
        ];

        return $fields;
    }

    /**
     * @param array $params
     * @param array|string|null $sort
     * @param int|null $limit
     *
     * @return SS_List
     */
    public function sourceRecords($params = [], $sort = null, $limit = null)
    {
        Versioned::set_stage(Versioned::DRAFT);

        $records = SiteTree::get();
        $compatibility = ContentReviewCompatability::start();

        // If there's no review dates set, default to all pages due for review now.

        // Show virtual pages?
        if (empty($params["ShowVirtualPages"])) {
            $virtualPageClasses = ClassInfo::subclassesFor(VirtualPage::class);
            $records = $records->where(sprintf(
                "\"SiteTree\".\"ClassName\" NOT IN ('%s')",
                implode("','", array_values($virtualPageClasses ?? []))
            ));
        }

        // Apply sort and limit if appropriate.
        if ($sort !== null) {
            $records = $records->sort($sort);
        }
        if ($limit !== null) {
            $records = $records->limit($limit);
        }

        // Trim out calculated values
        $list = $records->filterByCallback(function ($record) {
            return !$this->hasReviewSchedule($record);
        });

        ContentReviewCompatability::done($compatibility);

        return $list;
    }

    /**
     * @param DataObject $record
     *
     * @return bool
     */
    protected function hasReviewSchedule(DataObject $record)
    {
        if (!$record->obj("NextReviewDate")->exists()) {
            return false;
        }

        $options = $record->getOptions();

        if ($options && $options->OwnerGroups()->count() == 0 && $options->OwnerUsers()->count() == 0) {
            return false;
        }

        return true;
    }
}
