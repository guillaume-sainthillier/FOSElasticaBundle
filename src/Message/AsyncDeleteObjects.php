<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Message;

/**
 * Asks a worker to delete documents from an index by identifier.
 */
class AsyncDeleteObjects
{
    /**
     * @param list<string> $identifiers
     */
    public function __construct(
        private readonly string $indexName,
        private readonly array $identifiers,
        private readonly string|bool $routing = false,
    ) {
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    /**
     * @return list<string>
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    public function getRouting(): string|bool
    {
        return $this->routing;
    }
}
