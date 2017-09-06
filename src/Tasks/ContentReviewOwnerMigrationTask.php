<?php

namespace SilverStripe\ContentReview\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Task which migrates the ContentReview Module's SiteTree->OwnerID column to a new column name.
 */
class ContentReviewOwnerMigrationTask extends BuildTask
{
    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $results = DB::query("SHOW columns from \"SiteTree\" WHERE \"field\" = 'OwnerID'");

        if ($results->numRecords() == 0) {
            echo "<h1>No need to run task. SiteTree->OwnerID doesn't exist</h1>";
        } else {
            DB::query("UPDATE \"SiteTree\" SET \"ContentReviewOwnerID\" = \"OwnerID\"");
            DB::query("UPDATE \"SiteTree_Live\" SET \"ContentReviewOwnerID\" = \"OwnerID\"");
            DB::query("UPDATE \"SiteTree_versions\" SET \"ContentReviewOwnerID\" = \"OwnerID\"");
            DB::query("ALTER TABLE \"SiteTree\" DROP COLUMN \"OwnerID\"");
            DB::query("ALTER TABLE \"SiteTree_Live\" DROP COLUMN \"OwnerID\"");
            DB::query("ALTER TABLE \"SiteTree_Versions\" DROP COLUMN \"OwnerID\"");
            echo "<h1>Migrated 3 tables. Dropped obsolete OwnerID column</h1>";
        }
    }
}
