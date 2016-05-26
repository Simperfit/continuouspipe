<?php

namespace ContinuousPipe\River\CodeRepository\Status\EventListener;

use ContinuousPipe\River\CodeRepository\CodeStatusException;
use ContinuousPipe\River\Tide\Status\CodeStatusUpdater;
use ContinuousPipe\River\Event\TideCreated;
use ContinuousPipe\River\Event\TideEvent;
use ContinuousPipe\River\Event\TideFailed;
use ContinuousPipe\River\Event\TideSuccessful;
use ContinuousPipe\River\Repository\TideRepository;
use ContinuousPipe\River\View\TideRepository as TideViewRepository;
use ContinuousPipe\River\Tide\Status\Status;
use ContinuousPipe\River\Tide;
use ContinuousPipe\River\View\TimeResolver;
use LogStream\LoggerFactory;
use LogStream\Node\Text;

class TideStatusListener
{
    /**
     * @var TideRepository
     */
    private $tideRepository;

    /**
     * @var CodeStatusUpdater
     */
    private $codeStatusUpdater;

    /**
     * @var LoggerFactory
     */
    private $loggerFactory;

    /**
     * @var TideViewRepository
     */
    private $tideViewRepository;

    /**
     * @var TimeResolver
     */
    private $timeResolver;

    /**
     * @param TideRepository     $tideRepository
     * @param CodeStatusUpdater  $codeStatusUpdater
     * @param LoggerFactory      $loggerFactory
     * @param TideViewRepository $tideViewRepository
     * @param TimeResolver       $timeResolver
     */
    public function __construct(TideRepository $tideRepository, CodeStatusUpdater $codeStatusUpdater, LoggerFactory $loggerFactory, TideViewRepository $tideViewRepository, TimeResolver $timeResolver)
    {
        $this->tideRepository = $tideRepository;
        $this->codeStatusUpdater = $codeStatusUpdater;
        $this->loggerFactory = $loggerFactory;
        $this->tideViewRepository = $tideViewRepository;
        $this->timeResolver = $timeResolver;
    }

    /**
     * @param TideEvent $event
     */
    public function notify(TideEvent $event)
    {
        $tide = $this->tideRepository->find($event->getTideUuid());

        try {
            $this->updateTideStatus($event, $tide);
        } catch (CodeStatusException $e) {
            $logger = $this->loggerFactory->from($tide->getContext()->getLog());
            $logger->child(new Text($e->getMessage()));
        }
    }

    /**
     * @param TideEvent $event
     * @param Tide      $tide
     *
     * @throws CodeStatusException
     */
    private function updateTideStatus(TideEvent $event, Tide $tide)
    {
        if ($event instanceof TideCreated) {
            $this->codeStatusUpdater->update($tide, new Status(Status::STATE_PENDING, 'Running'));
        } elseif ($event instanceof TideSuccessful) {
            $status = new Status(Status::STATE_SUCCESS, sprintf('Successfully ran in %d seconds', $this->getDurationString($tide)));

            $this->codeStatusUpdater->update($tide, $status);
        } elseif ($event instanceof TideFailed) {
            $this->codeStatusUpdater->update($tide, new Status(Status::STATE_FAILURE, $event->getReason()));
        }
    }

    /**
     * @param Tide $tide
     *
     * @return string
     */
    private function getDurationString(Tide $tide)
    {
        $view = $this->tideViewRepository->find($tide->getUuid());

        if ($view->getStartDate() !== null) {
            $duration = $this->timeResolver->resolve()->getTimestamp() - $view->getStartDate()->getTimestamp();

            return gmdate('i\m s\s', $duration);
        }

        return '0';
    }
}
