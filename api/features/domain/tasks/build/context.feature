Feature:
  In order to build complex applications
  As a developer
  I want the docker-compose build context to be used

  Scenario: It loads the Dockefile configuration from the docker-compose file
    Given there is an application image in the repository with Dockerfile path "./sub-directory/my-Dockerfile"
    When a tide is started with a build and deploy task
    Then the build should be started with Dockerfile path "./sub-directory/my-Dockerfile" in the context

  Scenario: It loads the build directory from the docker-compose file
    Given there is 1 application images in the repository
    When a tide is started with a build task
    Then the build should be started with the sub-directory "./0"

  Scenario: It loads the build configuration from the task configuration
    Given I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        images:
            build:
                services:
                    first:
                        image: sroze/image
                        build_directory: ./sub-directory
                        docker_file_path: ./foo/Dockerfile-bar
    """
    When a tide is started
    Then the build should be started with the sub-directory "./sub-directory"
    And the build should be started with Dockerfile path "./foo/Dockerfile-bar" in the context
    And the build should be started with the image name "sroze/image"

  Scenario: Build environment variables
    Given there is 1 application images in the repository
    When a tide is started with a build task that have the following environment variables:
      | name | value |
      | FOO  | BAR   |
    Then the build should be started with the following environment variables:
      | name | value |
      | FOO  | BAR   |

  Scenario: Build environment per service
    Given I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        images:
            build:
                services:
                    first:
                        image: sroze/image
                        build_directory: ./sub-directory
                        docker_file_path: ./foo/Dockerfile-bar
                        environment:
                            - name: FOO
                              value: BAR
                    second:
                        image: sroze/image
                        build_directory: ./sub-directory
                        docker_file_path: ./foo/Dockerfile-bar
                        environment:
                            - name: FOO
                              value: BAZ
                            - name: BAR
                              value: FOO

    """
    When a tide is started
    Then the first build should be started with the following environment variables:
      | name | value |
      | FOO  | BAR   |
    And the second build should be started with the following environment variables:
      | name | value |
      | FOO  | BAZ   |
      | BAR  | FOO   |

  Scenario: Build with multiple steps
    Given I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        images:
            build:
                services:
                    first:
                        steps:
                            - docker_file_path: ./Buildfile
                            - docker_file_path: ./Dockerfile
                              image: sroze/image
    """
    When a tide is started with the UUID "00000000-0000-0000-0000-000000000000"
    Then the build should be started with 2 steps
    And the step #0 of the build should be started with the Dockerfile path "./Buildfile"
    And the step #1 of the build should be started with the Dockerfile path "./Dockerfile"
    And the step #1 of the build should be started with the image name "sroze/image"
