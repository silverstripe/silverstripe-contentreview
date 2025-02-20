<?php

namespace SilverStripe\ContentReview\Extensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * This extensions add a default schema for new pages and pages without a content
 * review setting.
 *
 * @property int $ReviewPeriodDays
 * @method ManyManyList<Group> ContentReviewGroups()
 * @method ManyManyList<Member> ContentReviewUsers()
 *
 * @extends DataExtension<SiteConfig>
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
    );

    /**
     * @config
     *
     * @var array
     */
    private static $defaults = array(
        'ReviewSubject' => 'Page(s) are due for content review',
        'ReviewBody' => '<h2>Page(s) due for review</h2>'
            . '<p>There are $PagesCount pages that are due for review today by you.</p>',
    );

    /**
     * @config
     *
     * @var array
     */
    private static $many_many = [
        'ContentReviewGroups' => Group::class,
        'ContentReviewUsers' => Member::class,
    ];

    /**
     * Template to use for content review emails.
     *
     * This should contain an $EmailBody variable as a placeholder for the user-defined copy
     *
     * @config
     *
     * @var string
     */
    private static $content_review_template = 'SilverStripe\\ContentReview\\ContentReviewEmail';

    /**
     * @return string
     */
    public function getOwnerNames()
    {
        $names = [];

        foreach ($this->OwnerGroups() as $group) {
            $names[] = $group->getBreadcrumbs(' > ');
        }

        foreach ($this->OwnerUsers() as $group) {
            $names[] = $group->getName();
        }

        return implode(', ', $names);
    }

    /**
     * @return ManyManyList<Group>
     */
    public function OwnerGroups()
    {
        return $this->owner->getManyManyComponents('ContentReviewGroups');
    }

    /**
     * @return ManyManyList<Member>
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
                __CLASS__ . '.DEFAULTSETTINGSHELP',
                'These settings will apply to all pages that do not have a specific Content Review schedule.'
            )
        );

        $fields->addFieldToTab('Root.ContentReview', $helpText);

        $reviewFrequency = DropdownField::create(
            'ReviewPeriodDays',
            _t(__CLASS__ . '.REVIEWFREQUENCY', 'Review frequency'),
            SiteTreeContentReview::get_schedule()
        )
            ->setDescription(_t(
                __CLASS__ . '.REVIEWFREQUENCYDESCRIPTION',
                'The review date will be set to this far in the future, whenever the page is published.'
            ));

        $fields->addFieldToTab('Root.ContentReview', $reviewFrequency);

        $users = Permission::get_members_by_permission([
            'CMS_ACCESS_CMSMain',
            'CMS_ACCESS_LeftAndMain',
            'ADMIN',
        ]);

        $usersMap = $users->map('ID', 'Title')->toArray();
        asort($usersMap);

        $userField = ListboxField::create('OwnerUsers', _t(__CLASS__ . '.PAGEOWNERUSERS', 'Users'), $usersMap)
            ->setAttribute('data-placeholder', _t(__CLASS__ . '.ADDUSERS', 'Add users'))
            ->setDescription(_t(__CLASS__ . '.OWNERUSERSDESCRIPTION', 'Page owners that are responsible for reviews'));

        $fields->addFieldToTab('Root.ContentReview', $userField);

        $groupsMap = [];

        foreach (Group::get() as $group) {
            // Listboxfield values are escaped, use ASCII char instead of &raquo;
            $groupsMap[$group->ID] = $group->getBreadcrumbs(' > ');
        }

        asort($groupsMap);

        $groupField = ListboxField::create('OwnerGroups', _t(__CLASS__ . '.PAGEOWNERGROUPS', 'Groups'), $groupsMap)
            ->setAttribute('data-placeholder', _t(__CLASS__ . '.ADDGROUP', 'Add groups'))
            ->setDescription(_t(__CLASS__ . '.OWNERGROUPSDESCRIPTION', 'Page owners that are responsible for reviews'));

        $fields->addFieldToTab('Root.ContentReview', $groupField);

        // Email content
        $fields->addFieldsToTab(
            'Root.ContentReview',
            [
                TextField::create('ReviewFrom', _t(__CLASS__ . '.EMAILFROM', 'From email address'))
                    ->setDescription(_t(__CLASS__ . '.EMAILFROM_RIGHTTITLE', 'e.g: do-not-reply@site.com')),
                TextField::create('ReviewSubject', _t(__CLASS__ . '.EMAILSUBJECT', 'Subject line')),
                TextAreaField::create('ReviewBody', _t(__CLASS__ . '.EMAILTEMPLATE', 'Email template')),
                LiteralField::create(
                    'TemplateHelp',
                    $this->owner->renderWith('SilverStripe\\ContentReview\\ContentReviewAdminHelp')
                ),
            ]
        );
    }

    /**
     * Get all Members that are default Content Owners. This includes checking group hierarchy
     * and adding any direct users.
     *
     * @return ArrayList<Group|Member>
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
        $adminEmail = Config::inst()->get(Email::class, 'admin_email');
        if (is_array($adminEmail)) {
            // May be configured using an array ['admin-email@mysite.text' => 'Admin email label']
            // https://docs.silverstripe.org/en/developer_guides/email/#administrator-emails
            return array_values(array_keys($adminEmail))[0];
        }
        return $adminEmail;
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
        $defaults = $this->owner->config()->get('defaults');
        if (isset($defaults[$field])) {
            return $defaults[$field];
        }
    }
}
