<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Message\Handler;

use Doctrine\Persistence\ManagerRegistry;
use FOS\ElasticaBundle\Message\AsyncDeleteObjects;
use FOS\ElasticaBundle\Message\AsyncInsertObjects;
use FOS\ElasticaBundle\Message\AsyncReplaceObjects;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\IndexableInterface;

/**
 * Indexes the changes sent by {@see \FOS\ElasticaBundle\Persister\AsyncObjectPersister}.
 */
class AsyncPersistObjectsHandler
{
    /**
     * @param array<string, array{registry: ManagerRegistry, model: class-string, identifier: string}> $indexes the indexes with an async listener
     */
    public function __construct(
        private readonly PersisterRegistry $persisterRegistry,
        private readonly IndexableInterface $indexable,
        private readonly array $indexes,
    ) {
    }

    public function insert(AsyncInsertObjects $message): void
    {
        if ($objects = $this->loadIndexableObjects($message->getIndexName(), $message->getIdentifiers())) {
            $this->persisterRegistry->getPersister($message->getIndexName())->insertMany($objects);
        }
    }

    public function replace(AsyncReplaceObjects $message): void
    {
        if ($objects = $this->loadIndexableObjects($message->getIndexName(), $message->getIdentifiers())) {
            $this->persisterRegistry->getPersister($message->getIndexName())->replaceMany($objects);
        }
    }

    public function delete(AsyncDeleteObjects $message): void
    {
        $this->persisterRegistry->getPersister($message->getIndexName())->deleteManyByIdentifiers($message->getIdentifiers(), $message->getRouting());
    }

    /**
     * Objects removed since the message was sent are skipped, and so are the ones that stopped
     * being indexable: the listener has sent a deletion for them.
     *
     * @param list<string> $identifiers
     *
     * @return list<object>
     */
    private function loadIndexableObjects(string $indexName, array $identifiers): array
    {
        if (!isset($this->indexes[$indexName])) {
            throw new \InvalidArgumentException(\sprintf('The listener of index "%s" is not async.', $indexName));
        }

        ['registry' => $registry, 'model' => $model, 'identifier' => $identifier] = $this->indexes[$indexName];
        $objects = $registry->getRepository($model)->findBy([$identifier => $identifiers]);

        return \array_values(\array_filter(
            $objects,
            fn (object $object): bool => $this->indexable->isObjectIndexable($indexName, $object)
        ));
    }
}
