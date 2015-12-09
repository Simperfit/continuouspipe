<?php

namespace ContinuousPipe\River\Event\GitHub;

use ContinuousPipe\River\CodeReference;
use ContinuousPipe\River\Event\CodeRepositoryEvent;
use ContinuousPipe\River\Flow;
use GitHub\WebHook\Event\PushEvent;

class CodePushed implements CodeRepositoryEvent
{
    /**
     * @var PushEvent
     */
    private $gitHubEvent;

    /**
     * @var CodeReference
     */
    private $codeReference;

    /**
     * @var Flow
     */
    private $flow;

    /**
     * @param Flow          $flow
     * @param PushEvent     $gitHubEvent
     * @param CodeReference $codeReference
     */
    public function __construct(Flow $flow, PushEvent $gitHubEvent, CodeReference $codeReference)
    {
        $this->gitHubEvent = $gitHubEvent;
        $this->codeReference = $codeReference;
        $this->flow = $flow;
    }

    /**
     * @return PushEvent
     */
    public function getGitHubEvent()
    {
        return $this->gitHubEvent;
    }

    /**
     * @return CodeReference
     */
    public function getCodeReference()
    {
        return $this->codeReference;
    }

    /**
     * @return Flow
     */
    public function getFlow()
    {
        return $this->flow;
    }
}
