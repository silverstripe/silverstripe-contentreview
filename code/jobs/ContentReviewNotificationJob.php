<?php

if (!class_exists("AbstractQueuedJob")) {
    return;
}

/**
 * Allows the content review module to use the optional queued jobs module to automatically
 * process content review emails. If the module isn't installed, nothing is done - SilverStripe
 * will never include this class declaration.
 *
 * If the module is installed, it will create a new job to be processed once every day by default.
 *
 * @see https://github.com/silverstripe-australia/silverstripe-queuedjobs
 */
class ContentReviewNotificationJob extends AbstractQueuedJob implements QueuedJob
{
    /**
     * The hour that the first job will be created at (for the next day). All other jobs should
     * be triggered around this time too, as the next generation is queued when this job is run.
     *
     * @var int
     *
     * @config
     */
    private static $first_run_hour = 9;

    /**
     * The hour at which to run these jobs.
     *
     * @var int
     *
     * @config
     */
    private static $next_run_hour = 9;

    /**
     * The minutes past the hour (see above) at which to run these jobs.
     *
     * @var int
     *
     * @config
     */
    private static $next_run_minute = 0;

    /**
     * The number of days to skip between job runs (1 means run this job every day,
     * 2 means run it every second day etc).
     *
     * @var int
     *
     * @config
     */
    private static $next_run_in_days = 1;

    /**
     * @return string
     */
    public function getTitle()
    {
        return "Content Review Notification Job";
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        $this->totalSteps = 1;

        return QueuedJob::QUEUED;
    }

    public function setup() {
        parent::setup();

        // Recommended for long running jobs that don't increment 'currentStep'
        // https://github.com/silverstripe-australia/silverstripe-queuedjobs
        $this->currentStep = -1;
    }

    public function process()
    {
        $this->queueNextRun();

        $task = new ContentReviewEmails();
        $task->run(new SS_HTTPRequest("GET", "/dev/tasks/ContentReviewEmails"));

        $this->currentStep = 1;
        $this->isComplete = true;
    }

    /**
     * Queue up the next job to run.
     */
    protected function queueNextRun()
    {
        $nextRun = new ContentReviewNotificationJob();

        $nextRunTime = mktime(
            Config::inst()->get(__CLASS__, 'next_run_hour'),
            Config::inst()->get(__CLASS__, 'next_run_minute'),
            0,
            date("m"),
            date("d") + Config::inst()->get(__CLASS__, 'next_run_in_days'),
            date("Y")
        );

        singleton("QueuedJobService")->queueJob(
            $nextRun,
            date("Y-m-d H:i:s", $nextRunTime)
        );
    }
}
