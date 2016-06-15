<?php

namespace ContinuousPipe\River\Task\Build\Handler;

use ContinuousPipe\Builder\Client\BuilderClient;
use ContinuousPipe\Builder\HttpNotification;
use ContinuousPipe\Builder\Logging;
use ContinuousPipe\Builder\LogStreamLogging;
use ContinuousPipe\Builder\Notification;
use ContinuousPipe\Builder\Request\BuildRequest;
use ContinuousPipe\River\Repository\TideNotFound;
use ContinuousPipe\River\Repository\TideRepository;
use ContinuousPipe\River\Task\Build\Command\BuildImageCommand;
use ContinuousPipe\River\Task\Build\Event\BuildStarted;
use LogStream\LoggerFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BuildImageHandler
{
    /**
     * @var BuilderClient
     */
    private $builderClient;

    /**
     * @var MessageBus
     */
    private $eventBus;
    /**
     * @var TideRepository
     */
    private $tideRepository;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param BuilderClient         $builderClient
     * @param TideRepository        $tideRepository
     * @param MessageBus            $eventBus
     * @param UrlGeneratorInterface $urlGenerator
     * @param LoggerInterface       $logger
     *
     * @internal param LoggerFactory $loggerFactory
     */
    public function __construct(BuilderClient $builderClient, TideRepository $tideRepository, MessageBus $eventBus, UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->builderClient = $builderClient;
        $this->eventBus = $eventBus;
        $this->tideRepository = $tideRepository;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    /**
     * @param BuildImageCommand $command
     */
    public function handle(BuildImageCommand $command)
    {
        $tideUuid = $command->getTideUuid();

        try {
            $tide = $this->tideRepository->find($tideUuid);
        } catch (TideNotFound $e) {
            $this->logger->critical('Tide not found while starting to build an image', [
                'tideUuid' => (string) $tideUuid,
            ]);

            return;
        }

        $buildRequest = $this->getBuildRequestWithNotificationConfiguration($tideUuid, $command->getBuildRequest(), $command->getLogId());
        $build = $this->builderClient->build($buildRequest, $tide->getContext()->getUser());

        $this->eventBus->handle(new BuildStarted($tideUuid, $build));
    }

    /**
     * Add the notification configuration to the created build request.
     *
     * @param Uuid         $tideUuid
     * @param BuildRequest $buildRequest
     * @param string       $parentLogId
     *
     * @return BuildRequest
     */
    private function getBuildRequestWithNotificationConfiguration(Uuid $tideUuid, BuildRequest $buildRequest, $parentLogId)
    {
        $address = $this->urlGenerator->generate('builder_notification_post', [
            'tideUuid' => (string) $tideUuid,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $buildRequest = new BuildRequest(
            $buildRequest->getRepository(),
            $buildRequest->getImage(),
            $buildRequest->getContext(),
            Notification::withHttp(HttpNotification::fromAddress($address)),
            Logging::withLogStream(LogStreamLogging::fromParentLogIdentifier($parentLogId)),
            $buildRequest->getEnvironment(),
            $buildRequest->getCredentialsBucket()
        );

        return $buildRequest;
    }
}
