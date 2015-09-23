<?php

namespace ContinuousPipe\Builder\Docker;

use ContinuousPipe\Builder\RegistryCredentials;
use ContinuousPipe\Builder\Archive;
use ContinuousPipe\Builder\Image;
use ContinuousPipe\Builder\Request\BuildRequest;
use Docker\Container;
use Docker\Docker;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Stream\Stream;
use LogStream\Logger;
use LogStream\Node\Text;
use Psr\Log\LoggerInterface;

class HttpClient implements Client
{
    /**
     * @var Docker
     */
    private $docker;

    /**
     * @var DockerfileResolver
     */
    private $dockerfileResolver;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Docker             $docker
     * @param DockerfileResolver $dockerfileResolver
     * @param LoggerInterface    $logger
     */
    public function __construct(Docker $docker, DockerfileResolver $dockerfileResolver, LoggerInterface $logger)
    {
        $this->docker = $docker;
        $this->dockerfileResolver = $dockerfileResolver;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function build(Archive $archive, BuildRequest $request, Logger $logger)
    {
        try {
            return $this->doBuild($archive, $request, $this->getOutputCallback($logger));
        } catch (RequestException $e) {
            $this->logger->notice('An error appeared while building an image', [
                'buildRequest' => $request,
                'exception' => $e,
            ]);

            if ($e->getPrevious() instanceof DockerException) {
                throw $e->getPrevious();
            }

            throw new DockerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function push(Image $image, RegistryCredentials $credentials, Logger $logger)
    {
        try {
            $this->docker->getImageManager()->push(
                $image->getName(), $image->getTag(),
                $credentials->getAuthenticationString(),
                $this->getOutputCallback($logger)
            );
        } catch (\Docker\Exception $e) {
            $this->logger->notice('An error appeared while pushing an image', [
                'image' => $image,
                'exception' => $e,
            ]);

            throw new DockerException($e->getMessage(), $e->getCode(), $e);
        } catch (RequestException $e) {
            if ($e->getPrevious() instanceof DockerException) {
                throw $e->getPrevious();
            }

            $this->logger->warning('An unexpected error appeared while building an image', [
                'image' => $image,
                'exception' => $e,
            ]);

            throw new DockerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function runAndCommit(Image $image, Logger $logger, $command)
    {
        $containerManager = $this->docker->getContainerManager();
        $container = new Container([
            'Image' => $this->getImageName($image),
            'Cmd' => [
                '/bin/sh', '-c', $command,
            ],
        ]);

        try {
            $this->logger->debug('Running a container', [
                'container' => $container,
            ]);

            $successful = $containerManager->run($container, $this->getOutputCallback($logger));
            if (!$successful) {
                throw new DockerException(sprintf(
                    'Expected exit code 0, but got %d',
                    $container->getExitCode()
                ));
            }

            $this->logger->debug('Committing a container', [
                'container' => $container,
                'image' => $image,
            ]);

            $this->commit($container, $image);
        } catch (\Docker\Exception $e) {
            $this->logger->warning('An error appeared while running container', [
                'container' => $container,
                'exception' => $e,
            ]);

            throw new DockerException(sprintf(
                'Unable to run container: %s',
                $e->getMessage()
            ), $e->getCode(), $e);
        } finally {
            try {
                if ($container->getId()) {
                    $containerManager->remove($container, true);
                }
            } catch (\Exception $e) {
                $this->logger->warning('An error appeared while removing a container', [
                    'container' => $container,
                    'image' => $image,
                    'exception' => $e,
                ]);
            }
        }

        return $image;
    }

    /**
     * Commit the given container.
     *
     * @param Container $container
     * @param Image     $image
     *
     * @return \Docker\Image
     *
     * @throws DockerException
     */
    private function commit(Container $container, Image $image)
    {
        try {
            return $this->docker->commit($container, [
                'repo' => $image->getName(),
                'tag' => $image->getTag(),
            ]);
        } catch (\Docker\Exception $e) {
            throw new DockerException(
                sprintf('Unable to commit container: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get the client stream callback.
     *
     * @param Logger $logger
     *
     * @return callable
     */
    private function getOutputCallback(Logger $logger)
    {
        return function ($output) use ($logger) {
            if (is_array($output)) {
                if (array_key_exists('error', $output)) {
                    if (!is_string($output['error'])) {
                        $output['error'] = 'Stringified error: '.print_r($output, true);
                    }

                    throw new DockerException($output['error']);
                } elseif (array_key_exists('stream', $output)) {
                    $output = $output['stream'];
                } elseif (array_key_exists('status', $output)) {
                    $output = $output['status'];
                }
            }

            if (null !== $output && !is_string($output)) {
                $output = 'Unknown ('.gettype($output).')';
            }

            if (!empty($output)) {
                $logger->append(new Text($output));
            }
        };
    }

    /**
     * @param Archive      $archive
     * @param BuildRequest $request
     * @param callable     $callback
     *
     * @return Image
     */
    private function doBuild(Archive $archive, BuildRequest $request, callable $callback)
    {
        $image = $request->getImage();

        $options = [
            'q' => (integer) false,
            't' => $this->getImageName($image),
            'nocache' => (integer) false,
            'rm' => (integer) false,
            'dockerfile' => $this->dockerfileResolver->getFilePath($request->getContext()),
        ];

        $content = $archive->isStreamed() ? new Stream($archive->read()) : $archive->read();

        $this->docker->getHttpClient()->post(['/build{?data*}', ['data' => $options]], [
            'headers' => ['Content-Type' => 'application/tar'],
            'body' => $content,
            'stream' => true,
            'callback' => $callback,
            'wait' => true,
        ]);

        return $image;
    }

    /**
     * @param Image $image
     *
     * @return string
     */
    private function getImageName(Image $image)
    {
        return $image->getName().':'.$image->getTag();
    }
}
