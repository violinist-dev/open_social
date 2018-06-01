@api @like @stability @DS-2969 @stability-4
Feature: Create topic like
  Benefit: In order to like a topic
  Role: As a LU
  Goal/desire: I want to be able to like a topic

  Scenario: Successfully like a topic
   Given users:
     | name     | mail               | status | field_profile_first_name | field_profile_last_name |
     | user_1   | mail_1@example.com | 1      | Marie                    | Curie                   |
     | user_2   | mail_2@example.com | 1      | Charles                  | Darwin                  |
   Given topics:
    | title           | description            | author | type        | language |
    | Topic for likes | Body description text. | user_1 | Discussion  | en       |
   Given I am logged in as "user_2"
     And I am at "all-topics"
    Then I should see "Topic for likes"
     And I should see "Marie Curie"
    When I click "Topic for likes"
    Then I should see "Topic for likes"
     And I click the xth "0" element with the css ".vote-like a"
     And I wait for AJAX to finish

    Given I am logged in as "user_1"
      And I click the xth "0" element with the css ".notification-bell a"
     Then I should see "Notification centre"
      And I should see "Charles Darwin likes your topic"
