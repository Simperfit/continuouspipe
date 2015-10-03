<?php

use Behat\Behat\Context\Context;
use ContinuousPipe\River\Tests\CodeRepository\Status\FakeCodeStatusUpdater;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use ContinuousPipe\River\Tests\CodeRepository\GitHub\FakePullRequestDeploymentNotifier;
use ContinuousPipe\River\Tests\CodeRepository\GitHub\FakePullRequestResolver;
use ContinuousPipe\River\Tests\Pipe\TraceableClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GitHubContext implements Context
{
    /**
     * @var TideContext
     */
    private $tideContext;

    /**
     * @var FlowContext
     */
    private $flowContext;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var FakeCodeStatusUpdater
     */
    private $fakeCodeStatusUpdater;

    /**
     * @var FakePullRequestDeploymentNotifier
     */
    private $fakePullRequestDeploymentNotifier;

    /**
     * @var FakePullRequestResolver
     */
    private $fakePullRequestResolver;

    /**
     * @var Response
     */
    private $response;
    /**
     * @var TraceableClient
     */
    private $traceableClient;

    /**
     * @param Kernel $kernel
     * @param FakeCodeStatusUpdater $fakeCodeStatusUpdater
     * @param FakePullRequestDeploymentNotifier $fakePullRequestDeploymentNotifier
     * @param FakePullRequestResolver $fakePullRequestResolver
     * @param TraceableClient $traceableClient
     */
    public function __construct(Kernel $kernel, FakeCodeStatusUpdater $fakeCodeStatusUpdater, FakePullRequestDeploymentNotifier $fakePullRequestDeploymentNotifier, FakePullRequestResolver $fakePullRequestResolver, TraceableClient $traceableClient)
    {
        $this->fakeCodeStatusUpdater = $fakeCodeStatusUpdater;
        $this->kernel = $kernel;
        $this->fakePullRequestDeploymentNotifier = $fakePullRequestDeploymentNotifier;
        $this->fakePullRequestResolver = $fakePullRequestResolver;
        $this->traceableClient = $traceableClient;
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $this->tideContext = $scope->getEnvironment()->getContext('TideContext');
        $this->flowContext = $scope->getEnvironment()->getContext('FlowContext');
    }

    /**
     * @Then the GitHub commit status should be :status
     */
    public function theGitHubCommitStatusShouldBe($status)
    {
        $foundStatus = $this->fakeCodeStatusUpdater->getStatusForTideUuid(
            $this->tideContext->getCurrentTideUuid()
        );

        if ($status !== $foundStatus) {
            throw new \RuntimeException(sprintf(
                'Found status "%s" instead of expected "%s"',
                $foundStatus,
                $status
            ));
        }
    }

    /**
     * @When a push webhook is received
     */
    public function aPushWebhookIsReceived()
    {
        $contents = file_get_contents(__DIR__.'/../fixtures/push-master.json');
        $flowUuid = $this->flowContext->getCurrentUuid();
        $this->response = $this->kernel->handle(Request::create('/web-hook/github/'.$flowUuid, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => '1234',
        ], $contents));
    }

    /**
     * @Then the created tide UUID should be returned
     */
    public function theCreatedTideUuidShouldBeReturned()
    {
        if (200 !== $this->response->getStatusCode()) {
            echo $this->response->getContent();
            throw new \RuntimeException(sprintf(
                'Expected status code 200 but got %d',
                $this->response->getStatusCode()
            ));
        }

        $json = json_decode($this->response->getContent(), true);
        $uuid = $json['uuid'];
        $this->tideContext->setCurrentTideUuid(\Rhumsaa\Uuid\Uuid::fromString($uuid));
    }

    /**
     * @Given a pull-request contains the tide-related commit
     */
    public function aPullRequestContainsTheTideRelatedCommit()
    {
        $this->fakePullRequestResolver->willResolve([
            new \GitHub\WebHook\Model\PullRequest(),
        ]);
    }

    /**
     * @Then the addresses of the environment should be commented on the pull-request
     */
    public function theAddressesOfTheEnvironmentShouldBeCommentedOnThePullRequest()
    {
        $notifications = $this->fakePullRequestDeploymentNotifier->getNotifications();

        if (count($notifications) == 0) {
            throw new \LogicException('Expected at least 1 notification, found 0');
        }
    }

    /**
     * @When a pull-request is created with head commit :sha
     */
    public function aPullRequestIsCreatedWithHeadCommit($sha)
    {
        $contents = file_get_contents(__DIR__.'/../fixtures/pull_request-created.json');
        $decoded = json_decode($contents, true);
        $decoded['pull_request']['head']['sha'] = $sha;
        $contents = json_encode($decoded);

        $flowUuid = $this->flowContext->getCurrentUuid();
        $response = $this->kernel->handle(Request::create('/web-hook/github/'.$flowUuid, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_GITHUB_DELIVERY' => '1234',
        ], $contents));

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'Expected response code to be bellow 300, got %d',
                $response->getStatusCode()
            ));
        }
    }

    /**
     * @When a pull-request is closed with head commit :sha
     */
    public function aPullRequestIsClosedWithHeadCommit($sha)
    {
        $contents = file_get_contents(__DIR__.'/../fixtures/pull_request-closed.json');
        $decoded = json_decode($contents, true);
        $decoded['pull_request']['head']['sha'] = $sha;
        $contents = json_encode($decoded);

        $flowUuid = $this->flowContext->getCurrentUuid();
        $this->response = $this->kernel->handle(Request::create('/web-hook/github/'.$flowUuid, 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_GITHUB_DELIVERY' => '1234',
        ], $contents));
    }

    /**
     * @Then the environment should be deleted
     */
    public function theEnvironmentShouldBeDeleted()
    {
        $deletions = $this->traceableClient->getDeletions();

        if (0 == count($deletions)) {
            throw new \RuntimeException('No deleted environment found');
        }
    }
}
