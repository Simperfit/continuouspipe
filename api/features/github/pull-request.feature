Feature:
  In order to have a feedback on deployed environments
  As a developer
  I want to have the environment addresses commented on my pull-requests

  Scenario: The addresses are commented when the deployment is successful
    Given I have a flow with a deploy task
    And there is 1 application images in the repository
    And a tide is started based on that workflow
    And a pull-request contains the tide-related commit
    When the deployment succeed
    Then the addresses of the environment should be commented on the pull-request

  Scenario: The addresses are automatically commented if the deployment is already done
    Given a deployment for a commit "123" is successful
    When a pull-request is created with head commit "123"
    Then the addresses of the environment should be commented on the pull-request
