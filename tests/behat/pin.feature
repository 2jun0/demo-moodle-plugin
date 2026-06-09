@local @local_demo
Feature: 데모 메모판에서 메모 고정하기
  중요한 공지를 위로 띄우기 위해
  사이트 관리자로서
  메모를 상단에 고정하고 해제할 수 있어야 한다

  Background:
    Given I log in as "admin"
    And I visit "/local/demo/index.php"
    And I set the field "Title" to "지난 공지"
    And I set the field "Content" to "지난주 안내입니다"
    And I press "Add a memo"
    And I set the field "Title" to "새 공지"
    And I set the field "Content" to "이번주 안내입니다"
    And I press "Add a memo"

  Scenario: 고정한 메모가 최신 메모보다 위로 올라온다
    Then I should see memo "새 공지" above memo "지난 공지"
    When I pin the memo "지난 공지"
    Then I should see memo "지난 공지" above memo "새 공지"

  Scenario: 고정을 해제하면 다시 최신순으로 돌아온다
    When I pin the memo "지난 공지"
    And I unpin the memo "지난 공지"
    Then I should see memo "새 공지" above memo "지난 공지"
