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

use FOS\ElasticaBundle\Message\AsyncDeleteObjects;
use FOS\ElasticaBundle\Message\AsyncInsertObjects;
use FOS\ElasticaBundle\Message\AsyncReplaceObjects;
use FOS\ElasticaBundle\Persister\AsyncObjectPersister;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class AsyncObjectPersisterTest extends TestCase
{
    /** @var list<object> */
    private array $dispatched = [];

    public function testDispatchesIdentifiersInsteadOfIndexing(): void
    {
        $persister = $this->createMock(ObjectPersisterInterface::class);
        $persister->expects($this->never())->method($this->logicalNot($this->equalTo('handlesObject')));
        $sut = new AsyncObjectPersister($persister, $this->createBus(), 'index', 'key');

        $sut->insertMany([$this->object(1), $this->object(2)]);
        $sut->replaceOne($this->object(3));
        $sut->deleteMany([$this->object(4)]);
        $sut->deleteById('5', 'a_routing');

        $this->assertEquals([
            new AsyncInsertObjects('index', ['1', '2']),
            new AsyncReplaceObjects('index', ['3']),
            new AsyncDeleteObjects('index', ['4']),
            new AsyncDeleteObjects('index', ['5'], 'a_routing'),
        ], $this->dispatched);
    }

    public function testDispatchesNothingWithoutObjects(): void
    {
        $sut = new AsyncObjectPersister($this->createMock(ObjectPersisterInterface::class), $this->createBus(), 'index');

        $sut->insertMany([]);
        $sut->replaceMany([]);
        $sut->deleteMany([]);

        $this->assertSame([], $this->dispatched);
    }

    public function testHandlesTheObjectsOfTheIndexPersister(): void
    {
        $object = $this->object(1);
        $persister = $this->createMock(ObjectPersisterInterface::class);
        $persister->expects($this->once())->method('handlesObject')->with($object)->willReturn(true);

        $this->assertTrue((new AsyncObjectPersister($persister, $this->createBus(), 'index'))->handlesObject($object));
    }

    private function createBus(): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return new Envelope($message);
        });

        return $bus;
    }

    private function object(int $key): object
    {
        $object = new \stdClass();
        $object->key = $key;
        $object->id = $key;

        return $object;
    }
}
