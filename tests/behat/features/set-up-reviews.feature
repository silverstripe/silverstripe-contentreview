Feature: Set up reviews
  As a CMS user
  I can set up content reviews for my content
  In order to ensure my content gets reviewed regularly

  Background:
    # Note: the review date is deliberately in the past
    Given a "page" "Home" with "Content"="<p>Welcome</p>", "NextReviewDate"="01/01/2017", "ReviewPeriodDays"="1"
    And the "group" "EDITOR" has permissions "CMS_ACCESS_CMSMain" and "EDIT_CONTENT_REVIEW_FIELDS"
    And I am logged in as a member of "EDITOR" group
    And I go to "admin/pages"

  @javascript
  Scenario: I can set content review options
    When I click on "Home" in the tree
    And I click the "Settings" CMS tab
    Then I should see a "Content review" CMS tab

    When I click the "Content review" CMS tab
    And I select "Custom settings" from "Options" input group
    And I wait for 1 second
    And I select "EDITOR" from "Groups"
    And I press "Save"
    Then I should see a "Content due for review" button

  @javascript
  Scenario: I can enter a review in the modal
    When I click on "Home" in the tree
    And I click the "Settings" CMS tab
    And I click the "Content review" CMS tab
    And I select "Custom settings" from "Options" input group
    And I wait for 1 seconds
    And I select "EDITOR" from "Groups"
    And I press "Save"
    And I follow "Content due for review"
    And I wait for 3 seconds
    Then I should see a "Mark as reviewed" button

    When I fill in "Review" with "LGTM"
    And I press "Mark as reviewed"
    And I wait for 3 seconds
    Then I should see "Review successfully added"
