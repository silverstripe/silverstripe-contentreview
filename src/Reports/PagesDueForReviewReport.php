<?php

namespace SilverStripe\ContentReview\Reports;

use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\ContentReview\Compatibility\ContentReviewCompatability;
use SilverStripe\ContentReview\Extensions\ContentReviewOwner;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\SS_List;
use SilverStripe\Reports\Report;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

/**
 * Show all pages that need to be reviewed.
 */
class PagesDueForReviewReport extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return _t(__CLASS__ . ".TITLE", "Pages due for review");
    }

    /**
     * @return FieldList
     */
    public function parameterFields()
    {
        $filtersList = FieldList::create();

        $filtersList->push(
            DateField::create(
                "ReviewDateAfter",
                _t(__CLASS__ . ".REVIEWDATEAFTER", "Review date after or on")
            )
        );

        $filtersList->push(
            DateField::create(
                "ReviewDateBefore",
                _t(__CLASS__ . ".REVIEWDATEBEFORE", "Review date before or on"),
                date("d/m/Y", strtotime("midnight"))
            )
        );

        $filtersList->push(
            CheckboxField::create(
                "ShowVirtualPages",
                _t(__CLASS__ . ".SHOWVIRTUALPAGES", "Show Virtual Pages")
            )
        );

        $filtersList->push(
            CheckboxField::create(
                "OnlyMyPages",
                _t(__CLASS__ . ".ONLYMYPAGES", "Only Show pages assigned to me")
            )
        );

        return $filtersList;
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
            "Title" => [
                "title" => "Page name",
                "formatting" => "<a href='{$linkPath}/\$ID?{$linkQuery}' title='Edit page'>\$value</a>"
            ],
            "NextReviewDate" => [
                "title" => "Review Date",
                "casting" => "Date->Full",
                "formatting" => function ($value, $item) {
                    if ($item->ContentReviewType == "Disabled") {
                        return "disabled";
                    }
                    if ($item->ContentReviewType == "Inherit") {
                        $setting = $item->getOptions();
                        if (!$setting) {
                            return "disabled";
                        }
                        return $item->obj("NextReviewDate")->Full();
                    }
                    return $value;
                }
            ],
            "OwnerNames" => [
                "title" => "Owner"
            ],
            "LastEditedByName" => "Last edited by",
            "AbsoluteLink" => [
                "title" => "URL",
                "formatting" => function ($value, $item) {
                    $liveLink = $item->AbsoluteLiveLink;
                    $stageLink = $item->AbsoluteLink();

                    return sprintf(
                        "%s <a href='%s'>%s</a>",
                        $stageLink,
                        $liveLink ? $liveLink : $stageLink . "?stage=Stage",
                        $liveLink ? "(live)" : "(draft)"
                    );
                }
            ],
            "ContentReviewType" => [
                "title" => "Settings are",
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
                }
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

        // Apply sort and limit if appropriate.
        if ($sort !== null) {
            $records = $records->sort($sort);
        }
        if ($limit !== null) {
            $records = $records->limit($limit);
        }

        if (empty($params['ReviewDateBefore']) && empty($params['ReviewDateAfter'])) {
            // If there's no review dates set, default to all pages due for review now
            $records = $records->where(
                sprintf(
                    '"NextReviewDate" < \'%s\'',
                    DBDatetime::now()->Format('y-MM-dd')
                )
            );
        } else {
            // Review date before
            if (!empty($params['ReviewDateBefore'])) {
                $nextReviewUnixSec = strtotime(
                    ' + 1 day',
                    strtotime($params['ReviewDateBefore'] ?? '')
                );
                $records = $records->where(
                    sprintf(
                        "\"NextReviewDate\" < '%s'",
                        DBDatetime::create()->setValue($nextReviewUnixSec)->Format('y-MM-dd')
                    )
                );
            }

            // Review date after
            if (!empty($params['ReviewDateAfter'])) {
                $records = $records->where(
                    sprintf(
                        "\"NextReviewDate\" >= '%s'",
                        DBDatetime::create()->setValue(strtotime($params['ReviewDateAfter']))->Format('y-MM-dd')
                    )
                );
            }
        }

        // Show virtual pages?
        if (empty($params["ShowVirtualPages"])) {
            $virtualPageClasses = ClassInfo::subclassesFor(VirtualPage::class);
            $records = $records->where(sprintf(
                "\"SiteTree\".\"ClassName\" NOT IN ('%s')",
                implode("','", array_values($virtualPageClasses ?? []))
            ));
        }

        // Owner dropdown
        if (!empty($params[ContentReviewOwner::class])) {
            $ownerNames = Convert::raw2sql($params[ContentReviewOwner::class]);
            $records = $records->filter("OwnerNames:PartialMatch", $ownerNames);
        }

        // Only show pages assigned to the current user?
        // This come last because it transforms $records to an ArrayList.
        if (!empty($params["OnlyMyPages"])) {
            $currentUser = Security::getCurrentUser();

            $records = $records->filterByCallback(function ($page) use ($currentUser) {
                $options = $page->getOptions();

                if ($options) {
                    foreach ($options->ContentReviewOwners() as $owner) {
                        if ($currentUser->ID == $owner->ID) {
                            return true;
                        }
                    }
                }

                return false;
            });
        }

        ContentReviewCompatability::done($compatibility);

        return $records;
    }
}
