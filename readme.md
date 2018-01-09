# Content Review module

[![Build status](https://travis-ci.org/silverstripe/silverstripe-contentreview.png?branch=master)](https://travis-ci.org/silverstripe/silverstripe-contentreview)
[![Code quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-contentreview/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-contentreview/?branch=master)
[![Code coverage](https://codecov.io/gh/silverstripe/silverstripe-contentreview/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-contentreview)

This module helps keep your website content accurate and up-to-date, which keeps your users happy.

It does so by sending reviewers reminder emails to go in and check the content. For a reviewer this
often includes checking links, grammar, factual information and look and feel.

There are two types of roles with this module.

 * Website owner; (typically assigned to the Administrator group) ensures that a website is accurate and up-to-date, by delegating responsibility to content reviewers.
 * Content reviewer; responsible for keeping a website or part of a website accurate and up-to-date.

## Requirements

 * SilverStripe ^4.0
 
 **Note:** For SilverStripe 3.x, please use the [3.x release line](https://github.com/silverstripe/silverstripe-contentreview/tree/3).

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

Run dev/build either via the web server by opening the URL `http://<your-host>/dev/build?flush` or
by running the dev/build via a CLI: `sake dev/build flush=1`

## Documentation

See the [docs/en](docs/en/index.md) folder.

## Versioning

This library follows [Semver](http://semver.org). According to Semver, you will be able to upgrade to any minor or patch version of this library without any breaking changes to the public API. Semver also requires that we clearly define the public API for this library.

All methods, with `public` visibility, are part of the public API. All other methods are not part of the public API. Where possible, we'll try to keep `protected` methods backwards-compatible in minor/patch versions, but if you're overriding methods then please test your work before upgrading.

## Reporting Issues

Please [create an issue](https://github.com/silverstripe/silverstripe-contentreview/issues) for any bugs you've found, or features you're missing.
