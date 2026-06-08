@local @local_demo
Feature: Posting memos on the demo memo board
  In order to share quick notices
  As a site administrator
  I need to post memos and see them in the list

  Scenario: Administrator posts a memo and sees it listed
    Given I log in as "admin"
    And I visit "/local/demo/index.php"
    When I set the field "Title" to "Exam next week"
    And I set the field "Content" to "Bring your calculator"
    And I press "Add a memo"
    Then I should see "Exam next week"
    And I should see "Bring your calculator"
