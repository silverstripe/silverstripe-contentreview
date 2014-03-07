# Content Review module

[![Build Status](https://travis-ci.org/silverstripe-labs/silverstripe-contentreview.png?branch=feature_improvements)](https://travis-ci.org/silverstripe-labs/silverstripe-contentreview)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-contentreview/badges/quality-score.png?s=e68f2c583f03c7eab0326781f6219f0ed58c9ad8)](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-contentreview/)
[![Code Coverage](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-contentreview/badges/coverage.png?s=42151d66ef5121363face01c03c94dc479baa408)](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-contentreview/)

This module helps with ensuring that a websites content are correct and up-to-date so that visitors 
can rely on the information provided by the client.

For a reviewer this often includes checking links, grammar, factual information and look and feel.


## Roles

There are two types of roles with this module. 

 * Content Owner; are responsible to periodically review a page.
 * Website Responsible; ensures that pages have an scheduled content review with an Content Owner.

## Features

 * Content owner will receive a notification when a pages review date is due with links to the page and the CMS edit form 
 * Content owners can mark a page as 'reviewed' via the CMS Page edit view.
 * Website Responsible can assign a content owner (members or groups) to a page (and optionally all sub-pages) and a schedule of how often the content should be reviewed.
 * Website Responsible can see a “pages due for review” report
 * Website Responsible can see a “pages without content owner” report
 * Website Responsible can set a default Content Owner and schedule for all pages without a review schedule.

## Wished features:

 * Reminder emails that notifies Content Owner and Website responsible that a review is over due.
 * Emails are customisable in the CMS


## Requirements

 * SilverStripe framework and CMS 3.1
 * Database: MySQL, PostgreSQL, SQLite or MSSQL
 * PHP 5.3 or newer

## Manual installation

Download or clone the source code into the SilverStripe root folder. Rename the module folder
to `contentreview`.

Run dev/build either via the webserver by opening the url `http://<your-host>/dev/build` or 
by running the dev/build via a CLI.

## Composer installation

	composer require silverstripe/contentreview dev-feature_improvements

## Setup

If you wish to have emails sent when a page comes up for review, you
new to have the DailyTask cron job set up. See ScheduledTask.php

## Usage

To set up a content review schedule the Website responsible needs the permission first. It can be 
set up by and administrator in the Security Admin under the 'Content Permission' for a group.

![](docs/en/images/content-review-permission.png)

To set a schedule for a page you need to open the `Settings > Content Review` setting for that page.

![](docs/en/images/content-review-settings.png)

CMS users without the permission to change the content review schedule can still see the settings 
and previous reviews in the same view, but cannot change anything.

![](docs/en/images/content-review-settings-ro.png)


## Migration

 * Todo, make a migration script from latest master
