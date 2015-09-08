<?php

namespace TestBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class TestExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $environment = $container->getParameter('kernel.environment');

        // Add our fake adapter
        $loader->load('fake-adapter.xml');

        // Updates the message bus to trace events
        $loader->load('message-bus.xml');

        // Add integration stubs
        $loader->load('integration/authenticator.xml');
        $loader->load('integration/logstream.xml');
        $loader->load('integration/notification.xml');
        $loader->load('kubernetes/client.xml');

        // Add in-memory stubs if not in smoke tests case
        if ($environment != 'smoke_test') {
            $loader->load('in-memory/deployment.xml');
            $loader->load('kubernetes/in-memory/adapter.xml');
        }

        $loader->load('httplabs.xml');
    }
}
