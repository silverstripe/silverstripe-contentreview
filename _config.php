<?php

Object::add_extension('SiteTree', 'SiteTreeContentReview');


if(class_exists('Subsite')) {
	SS_Report::register('ReportAdmin', 'SubsiteReportWrapper("PagesDueForReviewReport")',20);
} else {
	SS_Report::register('ReportAdmin', 'PagesDueForReviewReport',20);
}