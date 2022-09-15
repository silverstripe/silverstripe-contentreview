<?php

namespace SilverStripe\ContentReview\Extensions;

use Exception;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ContentReview\Jobs\ContentReviewNotificationJob;
use SilverStripe\ContentReview\Models\ContentReviewLog;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DateTimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Set dates at which content needs to be reviewed and provide a report and emails to alert
 * to content needing review.
 *
 * @property string $ContentReviewType
 * @property int $ReviewPeriodDays
 * @property Date $NextReviewDate
 * @property string $LastEditedByName
 * @property string $OwnerNames
 *
 * @method DataList ReviewLogs()
 * @method DataList ContentReviewGroups()
 * @method DataList ContentReviewUsers()
 */
class SiteTreeContentReview extends DataExtension implements PermissionProvider
{
    /**
     * @var array
     */
    private static $db = [
        "ContentReviewType" => "Enum('Inherit, Disabled, Custom', 'Inherit')",
        "ReviewPeriodDays" => "Int",
        "NextReviewDate" => "Date",
        "LastEditedByName" => "Varchar(255)",
        "OwnerNames" => "Varchar(255)",
    ];

    /**
     * @var array
     */
    private static $defaults = [
        "ContentReviewType" => "Inherit",
    ];

    /**
     * @var array
     */
    private static $has_many = [
        "ReviewLogs" => ContentReviewLog::class,
    ];

    /**
     * @var array
     */
    private static $belongs_many_many = [
        "ContentReviewGroups" => Group::class,
        "ContentReviewUsers" => Member::class,
    ];

    /**
     * @var array
     */
    private static $schedule = [
        0 => "No automatic review date",
        1 => "1 day",
        7 => "1 week",
        30 => "1 month",
        60 => "2 months",
        91 => "3 months",
        121 => "4 months",
        152 => "5 months",
        183 => "6 months",
        365 => "12 months",
    ];

    /**
     * @return array
     */
    public static function get_schedule()
    {
        return Config::inst()->get(static::class, 'schedule');
    }

    /**
     * Takes a list of groups and members and return a list of unique member.
     *
     * @param SS_List $groups
     * @param SS_List $members
     *
     * @return ArrayList
     */
    public static function merge_owners(SS_List $groups, SS_List $members)
    {
        $contentReviewOwners = new ArrayList();

        if ($groups->count()) {
            $groupIDs = [];

            foreach ($groups as $group) {
                $familyIDs = $group->collateFamilyIDs();

                if (is_array($familyIDs)) {
                    $groupIDs = array_merge($groupIDs, array_values($familyIDs ?? []));
                }
            }

            array_unique($groupIDs ?? []);

            if (count($groupIDs ?? [])) {
                $groupMembers = DataObject::get(Member::class)
                    ->where("\"Group\".\"ID\" IN (" . implode(",", $groupIDs) . ")")
                    ->leftJoin("Group_Members", "\"Member\".\"ID\" = \"Group_Members\".\"MemberID\"")
                    /** @skipUpgrade */
                    ->leftJoin('Group', "\"Group_Members\".\"GroupID\" = \"Group\".\"ID\"");

                $contentReviewOwners->merge($groupMembers);
            }
        }

        $contentReviewOwners->merge($members);
        $contentReviewOwners->removeDuplicates();

        return $contentReviewOwners;
    }

    /**
     * @param FieldList $actions
     */
    public function updateCMSActions(FieldList $actions)
    {
        if (!$this->canBeReviewedBy(Security::getCurrentUser())) {
            return;
        }

        Requirements::css('silverstripe/contentreview:client/dist/styles/contentreview.css');
        Requirements::javascript('silverstripe/contentreview:client/dist/js/contentreview.js');

        $reviewTab = LiteralField::create('ContentReviewButton', $this->owner->renderWith(__CLASS__ . '_button'));
        $actions->insertAfter('MajorActions', $reviewTab);
    }

    /**
     * Returns false if the content review have disabled.
     *
     * @param SiteTree $page
     *
     * @return bool|DBDate
     */
    public function getReviewDate(SiteTree $page = null)
    {
        if ($page === null) {
            $page = $this->owner;
        }

        if ($page->obj('NextReviewDate')->exists()) {
            return $page->obj('NextReviewDate');
        }

        $options = $this->owner->getOptions();

        if (!$options) {
            return false;
        }

        if (!$options->ReviewPeriodDays) {
            return false;
        }

        // Failover to check on ReviewPeriodDays + LastEdited
        $nextReviewUnixSec = strtotime(' + ' . $options->ReviewPeriodDays . ' days', DBDatetime::now()->getTimestamp());
        $date = DBDate::create('NextReviewDate');
        $date->setValue($nextReviewUnixSec);
        return $date;
    }

    /**
     * Get the object that have the information about the content review settings. Either:
     *
     *  - a SiteTreeContentReview decorated object
     *  - the default SiteTree config
     *  - false if this page have it's content review disabled
     *
     * Will go through parents and root pages will use the site config if their setting is Inherit.
     *
     * @return bool|DataObject
     *
     * @throws Exception
     */
    public function getOptions()
    {
        if ($this->owner->ContentReviewType == "Custom") {
            return $this->owner;
        }

        if ($this->owner->ContentReviewType == "Disabled") {
            return false;
        }

        $page = $this->owner;

        // $page is inheriting it's settings from it's parent, find
        // the first valid parent with a valid setting
        while ($parent = $page->Parent()) {
            // Root page, use site config
            if (!$parent->exists()) {
                return SiteConfig::current_site_config();
            }

            if ($parent->ContentReviewType == "Custom") {
                return $parent;
            }

            if ($parent->ContentReviewType == "Disabled") {
                return false;
            }

            $page = $parent;
        }

        throw new Exception("This shouldn't really happen, as per usual developer logic.");
    }

    /**
     * @return string
     */
    public function getOwnerNames()
    {
        $options = $this->getOptions();

        $names = [];

        if (!$options) {
            return "";
        }

        foreach ($options->OwnerGroups() as $group) {
            $names[] = $group->getBreadcrumbs(" > ");
        }

        foreach ($options->OwnerUsers() as $group) {
            $names[] = $group->getName();
        }

        return implode(", ", $names);
    }

    /**
     * @return null|string
     */
    public function getEditorName()
    {
        $member = Security::getCurrentUser();

        if ($member) {
            return $member->getTitle();
        }

        return null;
    }

    /**
     * Get all Members that are Content Owners to this page. This includes checking group
     * hierarchy and adding any direct users.
     *
     * @return ArrayList
     */
    public function ContentReviewOwners()
    {
        return SiteTreeContentReview::merge_owners(
            $this->OwnerGroups(),
            $this->OwnerUsers()
        );
    }

    /**
     * @return ManyManyList
     */
    public function OwnerGroups()
    {
        return $this->owner->getManyManyComponents("ContentReviewGroups");
    }

    /**
     * @return ManyManyList
     */
    public function OwnerUsers()
    {
        return $this->owner->getManyManyComponents("ContentReviewUsers");
    }

    /**
     * @param FieldList $fields
     */
    public function updateSettingsFields(FieldList $fields)
    {
        if ($this->owner->hasMethod('displayContentReview') && !$this->owner->displayContentReview()) {
            return;
        }

        Requirements::javascript('silverstripe/contentreview:client/dist/js/contentreview.js');
        // Display read-only version only
        if (!Permission::check("EDIT_CONTENT_REVIEW_FIELDS")) {
            $schedule = self::get_schedule();
            $contentOwners = ReadonlyField::create(
                "ROContentOwners",
                _t(__CLASS__ . ".CONTENTOWNERS", "Content Owners"),
                $this->getOwnerNames()
            );
            $nextReviewAt = DateField::create(
                'RONextReviewDate',
                _t(__CLASS__ . ".NEXTREVIEWDATE", "Next review date"),
                $this->owner->NextReviewDate
            );

            if (!isset($schedule[$this->owner->ReviewPeriodDays])) {
                $reviewFreq = ReadonlyField::create(
                    "ROReviewPeriodDays",
                    _t(__CLASS__ . ".REVIEWFREQUENCY", "Review frequency"),
                    $schedule[0]
                );
            } else {
                $reviewFreq = ReadonlyField::create(
                    "ROReviewPeriodDays",
                    _t(__CLASS__ . ".REVIEWFREQUENCY", "Review frequency"),
                    $schedule[$this->owner->ReviewPeriodDays]
                );
            }

            $logConfig = GridFieldConfig::create()
                ->addComponent(Injector::inst()->create(GridFieldSortableHeader::class))
                ->addComponent($logColumns = Injector::inst()->create(GridFieldDataColumns::class));

            // Cast the value to the users preferred date format
            $logColumns->setFieldCasting([
                'Created' => DateTimeField::class . '->value',
            ]);

            $logs = GridField::create("ROReviewNotes", "Review Notes", $this->owner->ReviewLogs(), $logConfig);

            $optionsFrom = ReadonlyField::create(
                "ROType",
                _t(__CLASS__ . ".SETTINGSFROM", "Options are"),
                $this->owner->ContentReviewType
            );

            $fields->addFieldsToTab("Root.ContentReview", [
                $contentOwners,
                $nextReviewAt->performReadonlyTransformation(),
                $reviewFreq,
                $optionsFrom,
                $logs,
            ]);

            return;
        }

        $options = [];
        $options["Disabled"] = _t(__CLASS__ . ".DISABLE", "Disable content review");
        $options["Inherit"] = _t(__CLASS__ . ".INHERIT", "Inherit from parent page");
        $options["Custom"] = _t(__CLASS__ . ".CUSTOM", "Custom settings");

        $viewersOptionsField = OptionsetField::create(
            "ContentReviewType",
            _t(__CLASS__ . ".OPTIONS", "Options"),
            $options
        );

        $users = Permission::get_members_by_permission(['CMS_ACCESS_CMSMain', 'CMS_ACCESS_LeftAndMain', 'ADMIN']);

        $usersMap = $users->map("ID", "Title")->toArray();

        asort($usersMap);

        $userField = ListboxField::create("OwnerUsers", _t(__CLASS__ . ".PAGEOWNERUSERS", "Users"), $usersMap)
            ->addExtraClass('custom-setting')
            ->setAttribute("data-placeholder", _t(__CLASS__ . ".ADDUSERS", "Add users"))
            ->setDescription(_t(__CLASS__ . '.OWNERUSERSDESCRIPTION', 'Page owners that are responsible for reviews'));

        $groupsMap = [];

        foreach (Group::get() as $group) {
            $groupsMap[$group->ID] = $group->getBreadcrumbs(" > ");
        }
        asort($groupsMap);

        $groupField = ListboxField::create("OwnerGroups", _t(__CLASS__ . ".PAGEOWNERGROUPS", "Groups"), $groupsMap)
            ->addExtraClass('custom-setting')
            ->setAttribute("data-placeholder", _t(__CLASS__ . ".ADDGROUP", "Add groups"))
            ->setDescription(_t(__CLASS__ . ".OWNERGROUPSDESCRIPTION", "Page owners that are responsible for reviews"));

        $reviewDate = DateField::create("NextReviewDate", _t(__CLASS__ . ".NEXTREVIEWDATE", "Next review date"))
            ->setDescription(_t(__CLASS__ . ".NEXTREVIEWDATADESCRIPTION", "Leave blank for no review"));

        $reviewFrequency = DropdownField::create(
            "ReviewPeriodDays",
            _t(__CLASS__ . ".REVIEWFREQUENCY", "Review frequency"),
            self::get_schedule()
        )
            ->addExtraClass('custom-setting')
            ->setDescription(_t(
                __CLASS__ . ".REVIEWFREQUENCYDESCRIPTION",
                "The review date will be set to this far in the future whenever the page is published"
            ));

        $notesField = GridField::create(
            "ReviewNotes",
            "Review Notes",
            $this->owner->ReviewLogs(),
            GridFieldConfig_RecordEditor::create()
        );

        $fields->addFieldsToTab("Root.ContentReview", [
            HeaderField::create('ContentReviewHeader', _t(__CLASS__ . ".REVIEWHEADER", "Content review"), 2),
            $viewersOptionsField,
            CompositeField::create(
                $userField,
                $groupField,
                $reviewDate,
                $reviewFrequency
            )->addExtraClass("review-settings"),
            ReadonlyField::create(
                "ROContentOwners",
                _t(__CLASS__ . ".CONTENTOWNERS", "Content Owners"),
                $this->getOwnerNames()
            ),
            $notesField,
        ]);
    }

    /**
     * Creates a ContentReviewLog and connects it to this Page.
     *
     * @param Member $reviewer
     * @param string $message
     */
    public function addReviewNote(Member $reviewer, $message)
    {
        $reviewLog = ContentReviewLog::create();
        $reviewLog->Note = $message;
        $reviewLog->ReviewerID = $reviewer->ID;
        $this->owner->ReviewLogs()->add($reviewLog);
    }

    /**
     * Advance review date to the next date based on review period or set it to null
     * if there is no schedule. Returns true if date was required and false is content
     * review is 'off'.
     *
     * @return bool
     */
    public function advanceReviewDate()
    {
        $nextDateTimestamp = false;
        $options = $this->getOptions();

        if ($options && $options->ReviewPeriodDays) {
            $nextDateTimestamp = strtotime(
                ' + ' . $options->ReviewPeriodDays . ' days',
                DBDatetime::now()->getTimestamp()
            );

            $this->owner->NextReviewDate = DBDate::create()->setValue($nextDateTimestamp)->Format(DBDate::ISO_DATE);
            $this->owner->write();
        }

        if ($options && $options->ReviewPeriodDays == 0) {
            $this->owner->NextReviewDate = null;
            $this->owner->write();
        }

        return (bool)$nextDateTimestamp;
    }

    /**
     * Check if a review is due by a member for this owner.
     *
     * @param Member $member
     *
     * @return bool
     */
    public function canBeReviewedBy(Member $member = null)
    {
        if (!$this->owner->obj('NextReviewDate')->exists()) {
            return false;
        }

        if ($this->owner->obj('NextReviewDate')->InFuture()) {
            return false;
        }

        $options = $this->getOptions();

        if (!$options) {
            return false;
        }

        if (!$options
            // Options can be a SiteConfig with different extension applied
            || (!$options->hasExtension(__CLASS__)
                && !$options->hasExtension(ContentReviewDefaultSettings::class))
        ) {
            return false;
        }

        if ($options->OwnerGroups()->count() == 0 && $options->OwnerUsers()->count() == 0) {
            return false;
        }

        if (!$member) {
            return true;
        }

        // Check whether this user is allowed to review the content of the page.
        if ($this->owner->hasMethod("canReviewContent") && !$this->owner->canReviewContent($member)) {
            return false;
        }

        if ($member->inGroups($options->OwnerGroups())) {
            return true;
        }

        if ($options->OwnerUsers()->find("ID", $member->ID)) {
            return true;
        }

        return false;
    }

    /**
     * Set the review data from the review period, if set.
     */
    public function onBeforeWrite()
    {
        // Only update if DB fields have been changed
        $changedFields = $this->owner->getChangedFields(true, 2);
        if ($changedFields) {
            $this->owner->LastEditedByName = $this->owner->getEditorName();
            $this->owner->OwnerNames = $this->owner->getOwnerNames();
        }

        // If the user changed the type, we need to recalculate the review date.
        if ($this->owner->isChanged("ContentReviewType", 2)) {
            if ($this->owner->ContentReviewType == "Disabled") {
                $this->setDefaultReviewDateForDisabled();
            } elseif ($this->owner->ContentReviewType == "Custom") {
                $this->setDefaultReviewDateForCustom();
            } else {
                $this->setDefaultReviewDateForInherited();
            }
        }

        // Ensure that a inherited page always have a next review date
        if ($this->owner->ContentReviewType == "Inherit" && !$this->owner->NextReviewDate) {
            $this->setDefaultReviewDateForInherited();
        }

        // We need to update all the child pages that inherit this setting. We can only
        // change children after this record has been created, otherwise the stageChildren
        // method will grab all pages in the DB (this messes up unit testing)
        if (!$this->owner->exists()) {
            return;
        }

        // parent page change its review period
        // && !$this->owner->isChanged('ContentReviewType', 2)
        if ($this->owner->isChanged('ReviewPeriodDays', 2)) {
            $nextReviewUnixSec = strtotime(
                ' + ' . $this->owner->ReviewPeriodDays . ' days',
                DBDatetime::now()->getTimestamp()
            );
            $this->owner->NextReviewDate = DBDate::create()->setValue($nextReviewUnixSec)->Format('y-MM-dd');
        }
    }

    private function setDefaultReviewDateForDisabled()
    {
        $this->owner->NextReviewDate = null;
    }

    protected function setDefaultReviewDateForCustom()
    {
        // Don't overwrite existing value
        if ($this->owner->NextReviewDate) {
            return;
        }

        $this->owner->NextReviewDate = null;
        $nextDate = $this->getReviewDate();

        if (is_object($nextDate)) {
            $this->owner->NextReviewDate = $nextDate->getValue();
        } else {
            $this->owner->NextReviewDate = $nextDate;
        }
    }

    protected function setDefaultReviewDateForInherited()
    {
        // Don't overwrite existing value
        if ($this->owner->NextReviewDate) {
            return;
        }

        $options = $this->getOptions();
        $nextDate = null;

        if ($options instanceof SiteTree) {
            $nextDate = $this->getReviewDate($options);
        } elseif ($options instanceof SiteConfig) {
            $nextDate = $this->getReviewDate();
        }

        if (is_object($nextDate)) {
            $this->owner->NextReviewDate = $nextDate->getValue();
        } else {
            $this->owner->NextReviewDate = $nextDate;
        }
    }

    /**
     * Provide permissions to the CMS.
     *
     * @return array
     */
    public function providePermissions()
    {
        return [
            "EDIT_CONTENT_REVIEW_FIELDS" => [
                "name"     => "Set content owners and review dates",
                "category" => _t("SilverStripe\\Security\\Permission.CONTENT_CATEGORY", "Content permissions"),
                "sort"     => 50,
            ],
        ];
    }

    /**
     * If the queued jobs module is installed, queue up the first job for 9am tomorrow morning
     * (by default).
     */
    public function requireDefaultRecords()
    {
        if (class_exists(ContentReviewNotificationJob::class)) {
            // Ensure there is not already a job queued
            if (QueuedJobDescriptor::get()->filter("Implementation", ContentReviewNotificationJob::class)->first()) {
                return;
            }

            $nextRun = Injector::inst()->create(ContentReviewNotificationJob::class);
            $runHour = Config::inst()->get(ContentReviewNotificationJob::class, "first_run_hour");
            $firstRunTime = date(
                "Y-m-d H:i:s",
                mktime($runHour ?? 0, 0, 0, date("m"), date("d") + 1, date("y"))
            );

            singleton(QueuedJobService::class)->queueJob(
                $nextRun,
                $firstRunTime
            );

            DB::alteration_message(sprintf("Added ContentReviewNotificationJob to run at %s", $firstRunTime));
        }
    }
}
