@api @topic @comment @notifications @stability @PAC-392
Feature: Create Comments
  Benefit: Participate in discussions on the platform
  Role: As a LU
  Goal/desire: I want to create and see a comment

  @LU
  Scenario: Successfully create and see a comment for topic
    Given users:
      | name             | mail                         | status |
      | First Test User  | first-test-user@example.com  | 1      |
      | Second Test User | second-test-user@example.com | 1      |
      | Third Test User  | third-test-user@example.com  | 1      |

    Given I am logged in as "First Test User"
    And I am on "user"
    And I click "Topics"
    And I click "Create Topic"
    When I fill in "Title" with "Test Topic"
    And I fill in the "edit-body-0-value" WYSIWYG editor with "Body description text"
    And I click radio button "Discussion"
    And I press "Save"
    Then I should see the success message "Topic Test Topic has been created."

    Given I am logged in as "Second Test User"
    And I am on "/all-topics"
    And I click "Test Topic"
    Then I should see the link "Follow content"
    When I fill in "Add a comment" with "Test comment of Second Test User"
    And I press "Comment"
    Then I should see the success message "You were followed to a content of this topic."
    And I should see the link "Unfollow content"

    Given I am logged in as "Third Test User"
    And I am on "/all-topics"
    And I click "Test Topic"
    Then I should see the link "Follow content"
    When I fill in "Add a comment" with "Test comment of Third Test User"
    And I press "Comment"
    Then I should see the success message "You were followed to a content of this topic."
    And I should see the link "Unfollow content"

    Given I am logged in as "Second Test User"
    And I run cron
    And I am on the homepage
    And I click "Notification Centre"
    Then I should see "Third Test User commented on First Test User's topic you are following"
