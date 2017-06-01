<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Exception\PendingException;
use ContinuousPipe\River\CodeReference;
use ContinuousPipe\River\CodeRepository\CodeRepositoryUser;
use ContinuousPipe\River\CodeRepository\Event\CodePushed;
use ContinuousPipe\River\CodeRepository\InMemoryBranchQuery;
use ContinuousPipe\River\CodeRepository\PullRequest;
use ContinuousPipe\River\Event\GitHub\CommentedTideFeedback;
use ContinuousPipe\River\EventBus\EventStore;
use ContinuousPipe\River\Notifications\Events\CommentedPullRequest;
use ContinuousPipe\River\Tests\CodeRepository\PredictableCommitResolver;
use SimpleBus\Message\Bus\MessageBus;

class CodeRepositoriesContext implements Context
{
    /**
     * @var \TideContext
     */
    private $tideContext;

    /**
     * @var \FlowContext
     */
    private $flowContext;

    /**
     * @var PredictableCommitResolver
     */
    private $predictableCommitResolver;

    /**
     * @var MessageBus
     */
    private $eventBus;

    /**
     * @var EventStore
     */
    private $eventStore;
    /**
     * @var InMemoryBranchQuery
     */
    private $branchQuery;

    public function __construct(PredictableCommitResolver $predictableCommitResolver, MessageBus $eventBus, EventStore $eventStore, InMemoryBranchQuery $branchQuery)
    {
        $this->predictableCommitResolver = $predictableCommitResolver;
        $this->eventBus = $eventBus;
        $this->eventStore = $eventStore;
        $this->branchQuery = $branchQuery;
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
     * @Given the head commit of branch :branch is :sha1
     */
    public function theHeadCommitOfBranchIs($branch, $sha1)
    {
        $this->predictableCommitResolver->headOfBranchIs($branch, $sha1);
    }

    /**
     * @When the commit :sha1 is pushed to the branch :branch
     * @When the commit :sha1 was pushed to the branch :branch
     */
    public function theCommitIsPushedToTheBranch($sha1, $branch)
    {
        $flow = $this->flowContext->getCurrentFlow();

        $this->eventBus->handle(
            new CodePushed(
                $flow->getUuid(),
                new CodeReference(
                    $flow->getCodeRepository(),
                    $sha1,
                    $branch
                ),
                [
                    new CodeRepositoryUser(
                        $flow->getUser()->getUsername(),
                        $flow->getUser()->getEmail()
                    ),
                ]
            )
        );
    }

    /**
     * @Given a comment identified :commentId was already added
     */
    public function aCommentIdentifiedWasAlreadyAdded($commentId)
    {
        $this->eventStore->add(new CommentedPullRequest(
            $this->tideContext->getCurrentTideUuid(),
            new PullRequest(1234),
            $commentId
        ));
    }

    /**
     * @Given there is a :branch branch in the repository for the flow :flow
     */
    public function thereIsABranchInTheRepository($branch, $flow)
    {
        $this->branchQuery->addBranch($flow, $branch);
    }
}
