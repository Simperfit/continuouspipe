<?php

namespace ContinuousPipe\River\Handler;

use ContinuousPipe\River\Command\StartTideCommand;
use ContinuousPipe\River\Event\TideStarted;
use ContinuousPipe\River\Queue\DelayedMessageProducer;
use ContinuousPipe\River\Tide;
use ContinuousPipe\River\View\TideRepository;
use SimpleBus\Message\Bus\MessageBus;

class StartTideHandler
{
    /**
     * @var MessageBus
     */
    private $eventBus;

    /**
     * @var TideRepository
     */
    private $tideRepository;

    /**
     * @var Tide\Concurrency\TideConcurrencyManager
     */
    private $concurrencyManager;

    /**
     * @var DelayedMessageProducer
     */
    private $delayedMessageProducer;

    /**
     * @param MessageBus                              $eventBus
     * @param TideRepository                          $tideRepository
     * @param Tide\Concurrency\TideConcurrencyManager $concurrencyManager
     * @param DelayedMessageProducer                  $delayedMessageProducer
     */
    public function __construct(MessageBus $eventBus, TideRepository $tideRepository, Tide\Concurrency\TideConcurrencyManager $concurrencyManager, DelayedMessageProducer $delayedMessageProducer)
    {
        $this->eventBus = $eventBus;
        $this->tideRepository = $tideRepository;
        $this->concurrencyManager = $concurrencyManager;
        $this->delayedMessageProducer = $delayedMessageProducer;
    }

    /**
     * @param StartTideCommand $command
     */
    public function handle(StartTideCommand $command)
    {
        $tide = $this->tideRepository->find($command->getTideUuid());

        if ($this->concurrencyManager->shouldTideStart($tide)) {
            $this->eventBus->handle(new TideStarted($command->getTideUuid()));
        } else {
            $this->delayedMessageProducer->queue($command, 60);
        }
    }
}
