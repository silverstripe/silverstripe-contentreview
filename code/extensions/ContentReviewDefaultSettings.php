<?php

/**
 * This extensions add a default schema for new pages and pages without a content
 * review setting.
 *
 * @property int $ReviewPeriodDays
 */
class ContentReviewDefaultSettings extends DataExtension
{
    /**
     * @config
     *
     * @var array
     */
    private static $db = array(
        'ReviewPeriodDays' => 'Int',
        'ReviewFrom' => 'Varchar(255)',
        'ReviewSubject' => 'Varchar(255)',
        'ReviewBody' => 'HTMLText',
        'FirstReviewDaysBefore' => 'Int',
        'SecondReviewDaysBefore' => 'Int'
    );

    /**
     * @config
     *
     * @var array
     */
    private static $defaults = array(
        'ReviewSubject' => 'Page(s) are due for content review',
        'ReviewBody' => '<h2>Page(s) due for review</h2><p>There are $PagesCount pages that are due for review today by you.</p>',
        'FirstReviewDaysBefore' => 'Int'
    );

    /**
     * @config
     *
     * @var array
     */
    private static $many_many = array(
        'ContentReviewGroups' => 'Group',
        'ContentReviewUsers' => 'Member',
    );

    /**
     * Template to use for content review emails.
     *
     * This should contain an $EmailBody variable as a placeholder for the user-defined copy
     *
     * @config
     *
     * @var string
     */
    private static $content_review_template = 'ContentReviewEmail';

    /**
     * @return string
     */
    public function getOwnerNames()
    {
        $names = array();

        foreach ($this->OwnerGroups() as $group) {
            $names[] = $group->getBreadcrumbs(' > ');
        }

        foreach ($this->OwnerUsers() as $group) {
            $names[] = $group->getName();
        }

        return implode(', ', $names);
    }

    /**
     * @return ManyManyList
     */
    public function OwnerGroups()
    {
        return $this->owner->getManyManyComponents('ContentReviewGroups');
    }

    /**
     * @return ManyManyList
     */
    public function OwnerUsers()
    {
        return $this->owner->getManyManyComponents('ContentReviewUsers');
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $helpText = LiteralField::create(
            'ContentReviewHelp',
            _t(
                'ContentReview.DEFAULTSETTINGSHELP',
                'These content review settings will apply to all pages that does not have specific Content Review schedule.'
            )
        );

        $fields->addFieldToTab('Root.ContentReview', $helpText);

        $reviewFrequency = DropdownField::create(
            'ReviewPeriodDays',
            _t('ContentReview.REVIEWFREQUENCY', 'Review frequency'),
            SiteTreeContentReview::get_schedule()
        )
            ->setDescription(_t(
                'ContentReview.REVIEWFREQUENCYDESCRIPTION',
                'The review date will be set to this far in the future whenever the page is published'
            ));

        $fields->addFieldToTab('Root.ContentReview', $reviewFrequency);

        $FirstReviewDaysBefore = NumericField::create(
            'FirstReviewDaysBefore',
            _t('ContentReview.FIRSTREVIEWDAYSBEFORE', 'First review reminder # days before final review')
        );

        $fields->addFieldToTab('Root.ContentReview', $FirstReviewDaysBefore);

        $SecondReviewDaysBefore = NumericField::create(
            'SecondReviewDaysBefore',
            _t('ContentReview.SECONDREVIEWDAYSBEFORE', 'Second review reminder # days before final review')
        );

        $fields->addFieldToTab('Root.ContentReview', $SecondReviewDaysBefore);

        $users = Permission::get_members_by_permission(array(
            'CMS_ACCESS_CMSMain',
            'ADMIN',
        ));

        $usersMap = $users->map('ID', 'Title')->toArray();
        asort($usersMap);

        $userField = ListboxField::create('OwnerUsers', _t('ContentReview.PAGEOWNERUSERS', 'Users'), $usersMap)
            ->setMultiple(true)
            ->setAttribute('data-placeholder', _t('ContentReview.ADDUSERS', 'Add users'))
            ->setDescription(_t('ContentReview.OWNERUSERSDESCRIPTION', 'Page owners that are responsible for reviews'));

        $fields->addFieldToTab('Root.ContentReview', $userField);

        $groupsMap = array();

        foreach (Group::get() as $group) {
            // Listboxfield values are escaped, use ASCII char instead of &raquo;
            $groupsMap[$group->ID] = $group->getBreadcrumbs(' > ');
        }

        asort($groupsMap);

        $groupField = ListboxField::create('OwnerGroups', _t('ContentReview.PAGEOWNERGROUPS', 'Groups'), $groupsMap)
            ->setMultiple(true)
            ->setAttribute('data-placeholder', _t('ContentReview.ADDGROUP', 'Add groups'))
            ->setDescription(_t('ContentReview.OWNERGROUPSDESCRIPTION', 'Page owners that are responsible for reviews'));

        $fields->addFieldToTab('Root.ContentReview', $groupField);

        // Email content
        $fields->addFieldsToTab(
            'Root.ContentReview',
            array(
                TextField::create('ReviewFrom', _t('ContentReview.EMAILFROM', 'From email address'))
                    ->setRightTitle(_t('Review.EMAILFROM_RIGHTTITLE', 'e.g: do-not-reply@site.com')),
                TextField::create('ReviewSubject', _t('ContentReview.EMAILSUBJECT', 'Subject line')),
                TextAreaField::create('ReviewBody', _t('ContentReview.EMAILTEMPLATE', 'Email template')),
                LiteralField::create('TemplateHelp', $this->owner->renderWith('ContentReviewAdminHelp')),
            )
        );
    }

    /**
     * Get all Members that are default Content Owners. This includes checking group hierarchy
     * and adding any direct users.
     *
     * @return ArrayList
     */
    public function ContentReviewOwners()
    {
        return SiteTreeContentReview::merge_owners($this->OwnerGroups(), $this->OwnerUsers());
    }

    /**
     * Get the review body, falling back to the default if left blank.
     *
     * @return string HTML text
     */
    public function getReviewBody()
    {
        return $this->getWithDefault('ReviewBody');
    }

    /**
     * Get the review subject line, falling back to the default if left blank.
     *
     * @return string plain text value
     */
    public function getReviewSubject()
    {
        return $this->getWithDefault('ReviewSubject');
    }

    /**
     * Get the "from" field for review emails.
     *
     * @return string
     */
    public function getReviewFrom()
    {
        $from = $this->owner->getField('ReviewFrom');
        if ($from) {
            return $from;
        }

        // Fall back to admin email
        return Config::inst()->get('Email', 'admin_email');
    }

    /**
     * Get the value of a user-configured field, falling back to the default if left blank.
     *
     * @param string $field
     *
     * @return string
     */
    protected function getWithDefault($field)
    {
        $value = $this->owner->getField($field);
        if ($value) {
            return $value;
        }
        // fallback to default value
        $defaults = $this->owner->config()->defaults;
        if (isset($defaults[$field])) {
            return $defaults[$field];
        }
    }
}
