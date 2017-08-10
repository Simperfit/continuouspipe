Feature:
  In order to have endpoints created in a unified manner across the cluster
  As a DevOps engineer
  I want my endpoint policy to be the one used specifically when a user requires a public endpoint for a container

  Background:
    Given the team "my-team" exists
    And there is a user "samuel"
    And the user "samuel" is "ADMIN" of the team "my-team"
    And I have a flow with UUID "00000000-0000-0000-0000-000000000000" in the team "my-team"

  Scenario: Uses policy defaults
    Given the cluster "flex" of the team "my-team" have the following policies:
    """
    [
      {
        "name": "endpoint",
        "configuration": {
          "type": "ingress",
          "ingress-class": "nginx",
          "ingress-host-suffix": "-1234.continuouspipe.net"
        }
      }
    ]
    """
    Given the team "my-team" have the credentials of a cluster "foo"
    And I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        my_deployment:
            deploy:
                cluster: flex
                services:
                    app:
                        specification:
                            source:
                                image: foo/bar

                        endpoints:
                            - name: app
    """
    When a tide is started for the branch "master"
    Then the endpoint "app" of the component "app" should be deployed with an ingress with the host "master-app-1234.continuouspipe.net"

  Scenario: Default to the policy instead of deprecated accessibility
    Given the cluster "flex" of the team "my-team" have the following policies:
    """
    [
      {
        "name": "endpoint",
        "configuration": {
          "type": "ingress",
          "ingress-class": "nginx",
          "ingress-host-suffix": "-1234.continuouspipe.net"
        }
      }
    ]
    """
    Given the team "my-team" have the credentials of a cluster "foo"
    And I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        my_deployment:
            deploy:
                cluster: flex
                services:
                    app:
                        specification:
                            source:
                                image: foo/bar
                            accessibility:
                                from_external: true
    """
    When a tide is started for the branch "master"
    Then the endpoint "app" of the component "app" should be deployed with an ingress with the host "master-app-1234.continuouspipe.net"

  Scenario: The deployment fails if the type is different
    Given the cluster "flex" of the team "my-team" have the following policies:
    """
    [
      {
        "name": "endpoint",
        "configuration": {
          "type": "ingress",
          "ingress-class": "nginx",
          "ingress-host-suffix": "-1234.continuouspipe.net"
        }
      }
    ]
    """
    Given the team "my-team" have the credentials of a cluster "foo"
    And I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        my_deployment:
            deploy:
                cluster: flex
                services:
                    app:
                        specification:
                            source:
                                image: foo/bar

                        endpoints:
                            - name: app
                              type: NodePort
    """
    When a tide is started for the branch "master"
    Then the tide should be failed
    And a log containing 'Endpoint "app" has a type "NodePort" while type "ingress" is enforced by the cluster policy' should be created

  Scenario: The deployment fails if the ingress class is different
    Given the cluster "flex" of the team "my-team" have the following policies:
    """
    [
      {
        "name": "endpoint",
        "configuration": {
          "type": "ingress",
          "ingress-class": "nginx",
          "ingress-host-suffix": "-1234.continuouspipe.net"
        }
      }
    ]
    """
    Given the team "my-team" have the credentials of a cluster "foo"
    And I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        my_deployment:
            deploy:
                cluster: flex
                services:
                    app:
                        specification:
                            source:
                                image: foo/bar

                        endpoints:
                            - name: app
                              ingress:
                                  class: httplabs
                                  host_suffix: "foo.continuouspipe.net"
    """
    When a tide is started for the branch "master"
    Then the tide should be failed
    And a log containing 'Ingress class of component "app" is "httplabs" while class "nginx" is enforced by the cluster policy' should be created

  Scenario: The deployment fails if the host suffix is different
    Given the cluster "flex" of the team "my-team" have the following policies:
    """
    [
      {
        "name": "endpoint",
        "configuration": {
          "type": "ingress",
          "ingress-class": "nginx",
          "ingress-host-suffix": "-1234.continuouspipe.net"
        }
      }
    ]
    """
    Given the team "my-team" have the credentials of a cluster "foo"
    And I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        my_deployment:
            deploy:
                cluster: flex
                services:
                    app:
                        specification:
                            source:
                                image: foo/bar

                        endpoints:
                            - name: app
                              ingress:
                                  host_suffix: "-foo.continuouspipe.net"
    """
    When a tide is started for the branch "master"
    Then the tide should be failed
    And a log containing 'Ingress hostname of component "app" is "master-foo.continuouspipe.net" while the suffix "-1234.continuouspipe.net" is enforced by the cluster policy' should be created

  Scenario: It enables CloudFlare by default with the cluster policy
    Given the cluster "flex" of the team "my-team" have the following policies:
    """
    [
      {
        "name": "endpoint",
        "configuration": {
          "type": "ingress",
          "ingress-class": "nginx",
          "ingress-host-suffix": "-1234.continuouspipe.net",
          "cloud-flare-by-default": "true",
          "cloud-flare-proxied-by-default": "true"
        },
        "secrets": {
          "cloud-flare-zone-identifier": "123456",
          "cloud-flare-email": "samuel@continuouspipe.io",
          "cloud-flare-api-key": "qwertyuiopasdfghjklzxcvbnm"
        }
      }
    ]
    """
    Given the team "my-team" have the credentials of a cluster "foo"
    And I have a "continuous-pipe.yml" file in my repository that contains:
    """
    tasks:
        my_deployment:
            deploy:
                cluster: flex
                services:
                    app:
                        specification:
                            source:
                                image: foo/bar

                        endpoints:
                            - name: app
    """
    When a tide is started for the branch "master"
    Then the endpoint "app" of the component "app" should be deployed with an ingress with the host "master-app-1234.continuouspipe.net"
    And the endpoint "app" of the component "app" should be deployed with a CloudFlare DNS zone configuration with hostname "master-app-1234.continuouspipe.net"
    And the endpoint "app" of the component "app" should be deployed with a proxied CloudFlare DNS zone configuration
