# Content Review module developer documentation

## Configuration

### Global settings

The module is set up in the `Settings` section of the CMS, see the [User guide](userguide/index.md).

### Reminder emails

In order for the contentreview module to send emails, you need to *either*:

 * Setup the `ContentReviewEmails` script to run daily via a system cron job.
 * Install the [queuedjobs](https://github.com/symbiote/silverstripe-queuedjobs) module and follow the configuration steps to create a cron job for that module. Once installed, you can just run `dev/build` to have a job created, which will run at 9am every day by default.

## Using

See the [user guide](userguide/index.md).

## Testing

cd to the site root, and run:

```sh
$ php vendor/bin/behat @contentreview
```

or to run the unit test suite:

```sh
$ php vendor/bin/phpunit contentreview/tests
```

## Migration

If you are upgrading from an older version, you may need to run the `ContentReviewOwnerMigrationTask`
