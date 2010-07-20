# Content Review module

## Maintainer Contact
* Tom Rix (Nickname: trix)
  <tom (at) silverstripe (dot) com>

## Requirements
 * SilverStripe 2.4 or newer
 * Database: MySQL, Postgres, SQLite or MSSQL
 * PHP 5.2 or newer (because of Zend_Date usage)
 * module legacydatetimefields (http://svn.silverstripe.com/open/modules/legacydatetimefields/trunk)

## Installation

Drop it into your installation folder, and refresh your database schema
through `http://<your-host>/dev/build`.

If you wish to have emails sent when a page comes up for review, you
new to have the DailyTask cron job set up. See ScheduledTask.php

## Usage

When you open a page in the CMS, there will now be a Review tab.
