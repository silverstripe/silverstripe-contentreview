<?php

if(!class_exists('AbstractQueuedJob')) {
	return;
}

/**
 * Class ContentReviewNotificationJob
 *
 * Allows the contentreview module to use the optional queuedjobs module to automatically process content review emails.
 * If the module isn't installed, nothing is done - SilverStripe will never include this class declaration.
 *
 * If the module is installed, it will create a new job to be processed once every day by default.
 *
 * @see https://github.com/silverstripe-australia/silverstripe-queuedjobs
 */
class ContentReviewNotificationJob extends AbstractQueuedJob implements QueuedJob {
	/**
	 * @var int The hour that the first job will be created at (for the next day). All other jobs should be triggered
	 * around this time too, as the next generation is queued when this job is run.
	 * @config
	 */
	private static $first_run_hour = 9;

	/**
	 * @var int The hour at which to run these jobs
	 * @config
	 */
	private static $next_run_hour = 9;

	/**
	 * @var int The minutes past the hour (see above) at which to run these jobs
	 * @config
	 */
	private static $next_run_minute = 0;

	/**
	 * @var int The number of days to skip between job runs (e.g. 1 means run this job every day, 2 means run it every
	 * second day etc.)
	 * @config
	 */
	private static $next_run_in_days = 1;

	public function getTitle() {
		return 'Content Review Notification Job';
	}

	public function getJobType() {
		$this->totalSteps = 1;
		return QueuedJob::QUEUED;
	}

	public function process() {
		$this->queueNextRun();

		$task = new ContentReviewEmails();
		$task->run(new SS_HTTPRequest('GET', '/dev/tasks/ContentReviewEmails'));

		$this->currentStep = 1;
		$this->isComplete = true;
	}

	/**
	 * Queue up the next job to run.
	 */
	protected function queueNextRun() {
		$nextRun = new ContentReviewNotificationJob();
		$nextRunTime = mktime(
			self::$next_run_hour,
			self::$next_run_minute,
			0,
			date('m'),
			date('d') + self::$next_run_in_days,
			date('Y')
		);

		singleton('QueuedJobService')->queueJob(
			$nextRun,
			date('Y-m-d H:i:s', $nextRunTime)
		);
	}
}