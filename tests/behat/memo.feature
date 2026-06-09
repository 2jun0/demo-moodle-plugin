@local @local_demo
Feature: 데모 메모판에 메모 올리기
  빠른 공지를 공유하기 위해
  사이트 관리자로서
  메모를 올리고 목록에서 확인할 수 있어야 한다

  Scenario: 관리자가 메모를 올리면 목록에 보인다
    Given I log in as "admin"
    And I visit "/local/demo/index.php"
    When I set the field "Title" to "다음 주 시험"
    And I set the field "Content" to "계산기를 꼭 가져오세요"
    And I press "Add a memo"
    Then I should see "다음 주 시험"
    And I should see "계산기를 꼭 가져오세요"
