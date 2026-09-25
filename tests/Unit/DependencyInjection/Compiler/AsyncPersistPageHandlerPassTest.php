<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Tests\Unit\DependencyInjection\Compiler;

use FOS\ElasticaBundle\DependencyInjection\Compiler\AsyncPersistPageHandlerPass;
use FOS\ElasticaBundle\Message\AsyncPersistPage;
use FOS\ElasticaBundle\Message\Handler\AsyncPersistPageHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class AsyncPersistPageHandlerPassTest extends TestCase
{
    public function testKeepsTheBundleHandlerAlone(): void
    {
        $container = $this->createContainer();
        $container->register('other_handler', OtherMessageHandler::class)->addTag('messenger.message_handler');

        (new AsyncPersistPageHandlerPass())->process($container);

        $this->assertTrue($container->hasDefinition(AsyncPersistPageHandler::class));
    }

    public function testRemovesTheBundleHandlerForAnApplicationHandler(): void
    {
        $container = $this->createContainer();
        $container->register('app_handler', AppAsyncPersistPageHandler::class)->addTag('messenger.message_handler');

        (new AsyncPersistPageHandlerPass())->process($container);

        $this->assertFalse($container->hasDefinition(AsyncPersistPageHandler::class));
    }

    public function testRemovesTheBundleHandlerForAHandlerDeclaringTheMessage(): void
    {
        $container = $this->createContainer();
        $container->register('app_handler', OtherMessageHandler::class)
            ->addTag('messenger.message_handler', ['handles' => AsyncPersistPage::class, 'method' => 'persistPage'])
        ;

        (new AsyncPersistPageHandlerPass())->process($container);

        $this->assertFalse($container->hasDefinition(AsyncPersistPageHandler::class));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(AsyncPersistPageHandler::class, AsyncPersistPageHandler::class)->addTag('messenger.message_handler');

        return $container;
    }
}

class AppAsyncPersistPageHandler
{
    public function __invoke(AsyncPersistPage $message): void
    {
    }
}

class OtherMessageHandler
{
    public function __invoke(\stdClass $message): void
    {
    }

    public function persistPage(AsyncPersistPage $message): void
    {
    }
}
