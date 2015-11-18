<?php

require_once "Zend/Date.php";

/**
 * Show all pages that need to be reviewed.
 */
class PagesDueForReviewReport extends SS_Report
{
    /**
     * @return string
     */
    public function title()
    {
        return _t("PagesDueForReviewReport.TITLE", "Pages due for review");
    }

    /**
     * @return FieldList
     */
    public function parameterFields()
    {
        $filtersList = new FieldList();

        $filtersList->push(
            DateField::create("ReviewDateAfter", _t("PagesDueForReviewReport.REVIEWDATEAFTER", "Review date after or on"))
                ->setConfig("showcalendar", true)
        );

        $filtersList->push(
            DateField::create("ReviewDateBefore", _t("PagesDueForReviewReport.REVIEWDATEBEFORE", "Review date before or on"), date("d/m/Y", strtotime("midnight")))
                ->setConfig("showcalendar", true)
        );

        $filtersList->push(new CheckboxField("ShowVirtualPages", _t("PagesDueForReviewReport.SHOWVIRTUALPAGES", "Show Virtual Pages")));

        $filtersList->push(new CheckboxField("OnlyMyPages", _t("PagesDueForReviewReport.ONLYMYPAGES", "Only Show pages assigned to me")));

        return $filtersList;
    }

    /**
     * @return array
     */
    public function columns()
    {
        $linkBase = singleton("CMSPageEditController")->Link("show");
        $linkPath = parse_url($linkBase, PHP_URL_PATH);
        $linkQuery = parse_url($linkBase, PHP_URL_QUERY);

        $fields = array(
            "Title" => array(
                "title" => "Page name",
                "formatting" => "<a href='{$linkPath}/\$ID?{$linkQuery}' title='Edit page'>\$value</a>"
            ),
            "NextReviewDate" => array(
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
            ),
            "OwnerNames" => array(
                "title" => "Owner"
            ),
            "LastEditedByName" => "Last edited by",
            "AbsoluteLink" => array(
                "title" => "URL",
                "formatting" => function ($value, $item) {
                    $liveLink = $item->AbsoluteLiveLink;
                    $stageLink = $item->AbsoluteLink();

                    return sprintf("%s <a href='%s'>%s</a>",
                        $stageLink,
                        $liveLink ? $liveLink : $stageLink . "?stage=Stage",
                        $liveLink ? "(live)" : "(draft)"
                    );
                }
            ),
            "ContentReviewType" => array(
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
            ),
        );

        return $fields;
    }

    /**
     * @param array $params
     *
     * @return SS_List
     */
    public function sourceRecords($params = array())
    {
        Versioned::reading_stage("Stage");

        $records = SiteTree::get();
        $compatibility = ContentReviewCompatability::start();

        if (empty($params["ReviewDateBefore"]) && empty($params["ReviewDateAfter"])) {
            // If there's no review dates set, default to all pages due for review now
            $reviewDate = new Zend_Date(SS_Datetime::now()->Format("U"));
            $reviewDate->add(1, Zend_Date::DAY);
            $records = $records->where(sprintf('"NextReviewDate" < \'%s\'', $reviewDate->toString("YYYY-MM-dd")));
        } else {
            // Review date before
            if (!empty($params['ReviewDateBefore'])) {
                // TODO Get value from DateField->dataValue() once we have access to form elements here
                $reviewDate = new Zend_Date($params["ReviewDateBefore"], Config::inst()->get("i18n", "date_format"));
                $reviewDate->add(1, Zend_Date::DAY);
                $records = $records->where(sprintf("\"NextReviewDate\" < '%s'", $reviewDate->toString("YYYY-MM-dd")));
            }

            // Review date after
            if (!empty($params["ReviewDateAfter"])) {
                // TODO Get value from DateField->dataValue() once we have access to form elements here
                $reviewDate = new Zend_Date($params["ReviewDateAfter"], Config::inst()->get("i18n", "date_format"));
                $records = $records->where(sprintf("\"NextReviewDate\" >= '%s'", $reviewDate->toString("YYYY-MM-dd")));
            }
        }

        // Show virtual pages?
        if (empty($params["ShowVirtualPages"])) {
            $virtualPageClasses = ClassInfo::subclassesFor("VirtualPage");
            $records = $records->where(sprintf(
                "\"SiteTree\".\"ClassName\" NOT IN ('%s')",
                implode("','", array_values($virtualPageClasses))
            ));
        }

        // Owner dropdown
        if (!empty($params["ContentReviewOwner"])) {
            $ownerNames = Convert::raw2sql($params["ContentReviewOwner"]);
            $records = $records->filter("OwnerNames:PartialMatch", $ownerNames);
        }

        // Only show pages assigned to the current user?
        // This come last because it transforms $records to an ArrayList.
        if (!empty($params["OnlyMyPages"])) {
            $currentUser = Member::currentUser();

            $records = $records->filterByCallback(function($page) use ($currentUser) {
                $options = $page->getOptions();

                foreach ($options->ContentReviewOwners() as $owner) {
                    if ($currentUser->ID == $owner->ID) {
                        return true;
                    }
                }

                return false;
            });
        }

        ContentReviewCompatability::done($compatibility);

        return $records;
    }
}
