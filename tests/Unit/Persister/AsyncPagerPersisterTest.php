<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Tests\Unit\Persister;

use Elastica\Client;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Message\AsyncPersistPage;
use FOS\ElasticaBundle\Persister\AsyncPagerPersister;
use FOS\ElasticaBundle\Persister\PagerPersisterInterface;
use FOS\ElasticaBundle\Persister\PagerPersisterRegistry;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use FOS\ElasticaBundle\Provider\PagerProviderRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class AsyncPagerPersisterTest extends TestCase
{
    public function testShouldImplementPagerPersisterInterface(): void
    {
        $reflectionClass = new \ReflectionClass(AsyncPagerPersister::class);
        $this->assertTrue($reflectionClass->implementsInterface(PagerPersisterInterface::class));
    }

    public function testInsertDispatchAsyncPersistPageObject(): void
    {
        $pagerPersisterRegistry = new PagerPersisterRegistry($this->createMock(ServiceLocator::class));
        $pagerProviderRegistry = $this->createMock(PagerProviderRegistry::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $sut = new AsyncPagerPersister($pagerPersisterRegistry, $pagerProviderRegistry, $messageBus);

        $messageBus->expects($this->once())->method('dispatch')->with(
            $this->callback(
                fn (object $message): bool => $message instanceof AsyncPersistPage
            )
        )->willReturn(new Envelope(new AsyncPersistPage(0, [])));

        $pager = $this->createMock(PagerInterface::class);
        $sut->insert($pager);
    }

    public function testInsertTargetsThePopulatedIndex(): void
    {
        $index = $this->createIndex();
        $index->overrideName('index_2026-09-25-120000');
        $messages = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static function (AsyncPersistPage $message) use (&$messages): Envelope {
            $messages[] = $message;

            return new Envelope($message);
        });
        $pager = $this->createMock(PagerInterface::class);
        $pager->method('getMaxPerPage')->willReturn(10);
        $pager->method('getCurrentPage')->willReturn(1);
        $pager->method('getNbPages')->willReturn(2);

        $sut = new AsyncPagerPersister(
            new PagerPersisterRegistry(new ServiceLocator([])),
            $this->createMock(PagerProviderRegistry::class),
            $messageBus,
            $this->createIndexManager($index)
        );
        $sut->insert($pager, ['indexName' => 'index', 'max_per_page' => 10]);

        $this->assertCount(2, $messages);
        $this->assertSame('index_2026-09-25-120000', $messages[0]->getOptions()['target_index_name']);
        $this->assertSame('index_2026-09-25-120000', $messages[1]->getOptions()['target_index_name']);
    }

    public function testInsertPagePersistsIntoThePopulatedIndex(): void
    {
        $index = $this->createIndex();
        $namesDuringInsert = [];
        $inPlacePersister = $this->createMock(PagerPersisterInterface::class);
        $inPlacePersister->method('insert')->willReturnCallback(static function () use ($index, &$namesDuringInsert): void {
            $namesDuringInsert[] = $index->getName();
        });

        $this->createPersister($inPlacePersister, $index)
            ->insertPage(2, ['indexName' => 'index', 'max_per_page' => 10, 'target_index_name' => 'index_new'])
        ;

        $this->assertSame(['index_new'], $namesDuringInsert);
        $this->assertSame('index', $index->getName());
    }

    public function testInsertPageRestoresTheIndexNameOnFailure(): void
    {
        $index = $this->createIndex();
        $inPlacePersister = $this->createMock(PagerPersisterInterface::class);
        $inPlacePersister->method('insert')->willThrowException(new \RuntimeException('Bulk failed'));

        try {
            $this->createPersister($inPlacePersister, $index)
                ->insertPage(2, ['indexName' => 'index', 'max_per_page' => 10, 'target_index_name' => 'index_new'])
            ;
            $this->fail('The exception should be thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Bulk failed', $e->getMessage());
        }

        $this->assertSame('index', $index->getName());
    }

    private function createPersister(PagerPersisterInterface $inPlacePersister, Index $index): AsyncPagerPersister
    {
        return new AsyncPagerPersister(
            new PagerPersisterRegistry(new ServiceLocator(['in_place' => static fn () => $inPlacePersister])),
            $this->createPagerProviderRegistry(),
            $this->createMock(MessageBusInterface::class),
            $this->createIndexManager($index)
        );
    }

    private function createIndex(): Index
    {
        return new Index(new Client(), 'index');
    }

    private function createIndexManager(Index $index): IndexManager
    {
        $indexManager = $this->createMock(IndexManager::class);
        $indexManager->method('getIndex')->with('index')->willReturn($index);

        return $indexManager;
    }

    private function createPagerProviderRegistry(): PagerProviderRegistry
    {
        $provider = $this->createMock(PagerProviderInterface::class);
        $provider->method('provide')->willReturn($this->createMock(PagerInterface::class));
        $registry = $this->createMock(PagerProviderRegistry::class);
        $registry->method('getProvider')->willReturn($provider);

        return $registry;
    }
}
