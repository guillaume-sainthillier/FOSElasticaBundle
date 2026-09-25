<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Persister;

use FOS\ElasticaBundle\Message\AsyncDeleteObjects;
use FOS\ElasticaBundle\Message\AsyncInsertObjects;
use FOS\ElasticaBundle\Message\AsyncReplaceObjects;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Sends the Doctrine listener's changes to a Messenger bus instead of indexing them.
 *
 * Only the identifiers travel: the handler reloads the objects and indexes their state at
 * that time, with the index's own persister (this one is given to the listener only).
 */
class AsyncObjectPersister implements ObjectPersisterInterface
{
    private readonly PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        private readonly ObjectPersisterInterface $persister,
        private readonly MessageBusInterface $bus,
        private readonly string $indexName,
        private readonly string $identifier = 'id',
        ?PropertyAccessorInterface $propertyAccessor = null,
    ) {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function handlesObject(object $object): bool
    {
        return $this->persister->handlesObject($object);
    }

    public function insertOne(object $object): void
    {
        $this->insertMany([$object]);
    }

    public function replaceOne(object $object): void
    {
        $this->replaceMany([$object]);
    }

    public function deleteOne(object $object): void
    {
        $this->deleteMany([$object]);
    }

    public function deleteById(string $id, string|bool $routing = false): void
    {
        $this->deleteManyByIdentifiers([$id], $routing);
    }

    public function insertMany(array $objects): void
    {
        if ([] !== $objects) {
            $this->bus->dispatch(new AsyncInsertObjects($this->indexName, $this->getIdentifiers($objects)));
        }
    }

    public function replaceMany(array $objects): void
    {
        if ([] !== $objects) {
            $this->bus->dispatch(new AsyncReplaceObjects($this->indexName, $this->getIdentifiers($objects)));
        }
    }

    public function deleteMany(array $objects): void
    {
        $this->deleteManyByIdentifiers($this->getIdentifiers($objects));
    }

    public function deleteManyByIdentifiers(array $identifiers, string|bool $routing = false): void
    {
        if ([] !== $identifiers) {
            $this->bus->dispatch(new AsyncDeleteObjects($this->indexName, $identifiers, $routing));
        }
    }

    /**
     * @param list<object> $objects
     *
     * @return list<string>
     */
    private function getIdentifiers(array $objects): array
    {
        return \array_map(fn (object $object): string => (string) $this->propertyAccessor->getValue($object, $this->identifier), $objects);
    }
}
