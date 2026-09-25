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
 * Asks a worker to reload objects by identifier and replace them into an index.
 */
class AsyncReplaceObjects
{
    /**
     * @param list<string> $identifiers
     */
    public function __construct(
        private readonly string $indexName,
        private readonly array $identifiers,
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
}
