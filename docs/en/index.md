# Content Review module developer documentation 

## Configuration

### Global settings

The module is set up in the `Settings` section of the CMS, see the [User guide](userguide/index.md).

### Reminder emails

In order for the contentreview module to send emails, you need to *either*:

 * Setup the DailyTask script to run daily via cron. See framework/tasks/ScheduledTask.php for more information on setup.
 * Install the queuedjobs module, and follow the configuration steps to create a cron job for that module. Once installed, you can just run dev/build to have a job created, which will run at 9am every day by default.

## Using
See [User guide](userguide/index.md)

## Testing

cd to the site root, and run:

```sh
$ php vendor/bin/behat
```

or to test this module when used on a website:

```sh
$ php vendor/bin/behat contentreview/tests
```

## Migration

If you are upgrading from an older version, you may need to run the `ContentReviewOwnerMigrationTask`
