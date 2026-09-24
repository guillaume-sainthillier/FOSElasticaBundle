<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use FOS\ElasticaBundle\Persister\Event\PersistEvent;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PostPersistEvent;
use FOS\ElasticaBundle\Persister\Event\PreFetchObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PreInsertObjectsEvent;
use FOS\ElasticaBundle\Provider\PagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RegisterListenersService
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function register(ObjectManager $manager, PagerInterface $pager, array $options): void
    {
        $options = \array_replace([
            'clear_object_manager' => true,
            'debug_logging' => false,
            'sleep' => 0,
        ], $options);

        $listeners = [];

        if ($options['clear_object_manager']) {
            $listeners[] = $this->addListener($pager, PostInsertObjectsEvent::class, function () use ($manager): void {
                $manager->clear();
            });
        }

        if ($options['sleep']) {
            $listeners[] = $this->addListener($pager, PostInsertObjectsEvent::class, function () use ($options): void {
                \usleep($options['sleep']);
            });
        }

        if (
            false === $options['debug_logging']
            && $manager instanceof EntityManagerInterface
        ) {
            $configuration = $manager->getConnection()->getConfiguration();
            if (\method_exists($configuration, 'getSQLLogger') && \method_exists($configuration, 'setSQLLogger')) {
                $logger = $configuration->getSQLLogger();

                $listeners[] = $this->addListener($pager, PreFetchObjectsEvent::class, function () use ($configuration): void {
                    $configuration->setSQLLogger(null);
                });

                $listeners[] = $this->addListener($pager, PreInsertObjectsEvent::class, function () use ($configuration, $logger): void {
                    $configuration->setSQLLogger($logger);
                });
            }
        }

        if ($listeners) {
            $this->removeListenersOncePersisted($pager, $listeners);
        }
    }

    /**
     * @return array{string, \Closure}
     */
    private function addListener(PagerInterface $pager, string $eventName, \Closure $callable): array
    {
        $listener = function (PersistEvent $event) use ($pager, $callable): void {
            if ($event->getPager() !== $pager) {
                return;
            }

            \call_user_func_array($callable, \func_get_args());
        };

        $this->dispatcher->addListener($eventName, $listener);

        return [$eventName, $listener];
    }

    /**
     * Left on the dispatcher, the listeners would keep the pager, and the objects of its current page, in memory:
     * a Messenger worker handling AsyncPersistPage messages registers them for a new pager with each message.
     *
     * @param list<array{string, \Closure}> $listeners
     */
    private function removeListenersOncePersisted(PagerInterface $pager, array $listeners): void
    {
        $this->dispatcher->addListener(PostPersistEvent::class, $cleanup = function (PostPersistEvent $event) use ($pager, $listeners, &$cleanup): void {
            if ($event->getPager() !== $pager) {
                return;
            }

            foreach ($listeners as [$eventName, $listener]) {
                $this->dispatcher->removeListener($eventName, $listener);
            }

            $this->dispatcher->removeListener(PostPersistEvent::class, $cleanup);
        });
    }
}
