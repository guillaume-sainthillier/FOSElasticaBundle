<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Tests\Unit\Message\Handler;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use FOS\ElasticaBundle\Message\AsyncDeleteObjects;
use FOS\ElasticaBundle\Message\AsyncInsertObjects;
use FOS\ElasticaBundle\Message\AsyncReplaceObjects;
use FOS\ElasticaBundle\Message\Handler\AsyncPersistObjectsHandler;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
class AsyncPersistObjectsHandlerTest extends TestCase
{
    private ObjectPersisterInterface&MockObject $persister;

    private ObjectRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->persister = $this->createMock(ObjectPersisterInterface::class);
        $this->repository = $this->createMock(ObjectRepository::class);
    }

    public function testInsertsTheReloadedObjects(): void
    {
        $objects = [new \stdClass(), new \stdClass()];
        $this->repository->expects($this->once())->method('findBy')->with(['key' => ['1', '2']])->willReturn($objects);
        $this->persister->expects($this->once())->method('insertMany')->with($objects);

        $this->createHandler()->insert(new AsyncInsertObjects('index', ['1', '2']));
    }

    public function testReplacesOnlyTheObjectsStillIndexable(): void
    {
        $indexable = new \stdClass();
        $notIndexable = new \stdClass();
        $this->repository->method('findBy')->willReturn([$notIndexable, $indexable]);
        $this->persister->expects($this->once())->method('replaceMany')->with([$indexable]);

        $this->createHandler(static fn (string $index, object $object): bool => $object === $indexable)
            ->replace(new AsyncReplaceObjects('index', ['1', '2']))
        ;
    }

    public function testSkipsObjectsRemovedSince(): void
    {
        $this->repository->method('findBy')->willReturn([]);
        $this->persister->expects($this->never())->method('insertMany');

        $this->createHandler()->insert(new AsyncInsertObjects('index', ['1']));
    }

    public function testDeletesByIdentifiers(): void
    {
        $this->repository->expects($this->never())->method('findBy');
        $this->persister->expects($this->once())->method('deleteManyByIdentifiers')->with(['1', '2'], 'a_routing');

        $this->createHandler()->delete(new AsyncDeleteObjects('index', ['1', '2'], 'a_routing'));
    }

    public function testRejectsIndexesWithoutAsyncListener(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createHandler()->insert(new AsyncInsertObjects('other_index', ['1']));
    }

    private function createHandler(?\Closure $isIndexable = null): AsyncPersistObjectsHandler
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getRepository')->with('Model')->willReturn($this->repository);

        $indexable = $this->createMock(IndexableInterface::class);
        $indexable->method('isObjectIndexable')->willReturnCallback($isIndexable ?? static fn (): bool => true);

        return new AsyncPersistObjectsHandler(
            new PersisterRegistry(new ServiceLocator(['index' => fn () => $this->persister])),
            $indexable,
            ['index' => ['registry' => $registry, 'model' => 'Model', 'identifier' => 'key']],
        );
    }
}
