Feature: Set up reviews
  As a CMS user
  I can set up content reviews for my content
  In order to ensure my content gets reviewed regularly

  Background:
    # Note: the review date is deliberately in the past
    Given a "page" "My page" with "Content"="<p>Welcome</p>", "NextReviewDate"="01/01/2017", "ReviewPeriodDays"="1"
    And the "group" "EDITOR group" has permissions "CMS_ACCESS_LeftAndMain" and "FILE_EDIT_ALL"
    And the "group" "FILEONLY group" has permissions "FILE_EDIT_ALL"
    And a "member" "Ed" belonging to "EDITOR group" with "Email"="ed@example.com"
    And a "member" "Phil" belonging to "FILEONLY group" with "Email"="phil@example.com"
    # Login in as EDITOR once https://github.com/silverstripe/silverstripe-contentreview/pull/155 is merged
    # And I am logged in with "EDITOR" permissions
    And I am logged in with "ADMIN" permissions
    And I go to "admin/pages"
    And I click on "My page" in the tree
    And I click the "Settings" CMS tab
    And I click the "Content review" CMS tab

  Scenario: I can set content reviewers to users and groups who can edit pages
    When I select "Custom settings" from "Options" input group
    And I wait for 1 second

    # Test adding individual member based on them having access to the Pages section fo the CMS
    Then the "#Form_EditForm_OwnerUsers" select element should have an option with an "Ed" label
    And the "#Form_EditForm_OwnerUsers" select element should not have an option with a "Phil" label

    # Test adding groups
    Then the "#Form_EditForm_OwnerGroups" select element should have an option with an "EDITOR group" label

    # Required to avoid "unsaved changed" browser dialog
    Then I press the "Save" button

  Scenario: There is an alert icon when a content review is overdue
    When I select "Custom settings" from "Options" input group
    And I wait for 1 second
    Then I should not see the ".content-review__button" element
    When I press the "Save" button
    And I select "ADMIN group" from "Groups"
    And I press the "Save" button
    Then I should see the ".content-review__button" element
    When I click on the ".content-review__button" element
    Then I should see the ".modal" element
    And I should see "Mark as reviewed"

    # Fill in a review
    When I fill in "Form_EditForm_ReviewContent_Review" with "My review"
    And I press "Mark as reviewed"
    And I wait for 1 second
    Then I should see "Review successfully added"
    When I click on the ".close" element
    And I press the "Save" button
    Then I should see "My review"
