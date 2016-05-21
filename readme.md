# Content Review module

[![Build Status](http://img.shields.io/travis/silverstripe/silverstripe-contentreview.svg?style=flat-square)](https://travis-ci.org/silverstripe/silverstripe-contentreview)
[![Code Quality](http://img.shields.io/scrutinizer/g/silverstripe/silverstripe-contentreview.svg?style=flat-square)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-contentreview)
[![Version](http://img.shields.io/packagist/v/silverstripe/contentreview.svg?style=flat-square)](https://packagist.org/packages/silverstripe/contentreview)
[![License](http://img.shields.io/packagist/l/silverstripe/contentreview.svg?style=flat-square)](license.md)
![helpfulrobot](https://helpfulrobot.io/silverstripe/contentreview/badge)

This module helps keep your website content accurate and up-to-date, which keeps your users happy. 

It does so by sending reviewers reminder emails to go in and check the content. For a reviewer this 
often includes checking links, grammar, factual information and look and feel.

There are two types of roles with this module. 

 * Website owner; (typically assigned to the Administrator group) ensures that a website is accurate and up-to-date, by delegating responsibility to content reviewers.
 * Content reviewer; responsible for keeping a website or part of a website accurate and up-to-date.

## Requirements

 * SilverStripe ^3.1

## Features

 * Content reviewer will receive an email notification when a page is due for review. 
 * Content reviewer can mark a page as 'reviewed', and provide review notes.
 * Website owner can assign content reviewers to a page and set when the content should be reviewed.
 * Website owner can see a report of pages and their reviewed status.
 * Content reviewers can be assigned to a page, a page and all sub-pages, or globally.
 * The content review schedule can be automatic, e.g. every month, and/or a specific date. 

## Wishlist features

 * Overdue review reminder emails.
 * Customisable reminder emails.

## Composer installation

```sh
$ composer require silverstripe/contentreview
```

You'll also need to run `dev/build`.

### Manual installation

Download or clone the source code into the SilverStripe root folder. Rename the module folder
to `contentreview`.

Run dev/build either via the webserver by opening the url `http://<your-host>/dev/build` or
by running the dev/build via a CLI.

## Documentation

See the [docs/en](docs/en/index.md) folder.

## Versioning

This library follows [Semver](http://semver.org). According to Semver, you will be able to upgrade to any minor or patch version of this library without any breaking changes to the public API. Semver also requires that we clearly define the public API for this library.

All methods, with `public` visibility, are part of the public API. All other methods are not part of the public API. Where possible, we'll try to keep `protected` methods backwards-compatible in minor/patch versions, but if you're overriding methods then please test your work before upgrading.

## Reporting Issues

Please [create an issue](https://github.com/silverstripe/silverstripe-contentreview/issues) for any bugs you've found, or features you're missing.
