<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Doctrine\ORM;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use FOS\ElasticaBundle\Provider\PagerInterface;

/**
 * Pages a query by blocks of identifiers instead of offsets: page N holds the rows whose
 * identifier is in ](N - 1) * maxPerPage, N * maxPerPage].
 *
 * Each page is a range scan on the primary key, as fast wherever it sits, where an OFFSET
 * reads and skips every row before the page. It needs no paginator either, even with
 * fetch joins, since the range bounds the root entities.
 *
 * A page only depends on its number, so the workers of an async populate can persist the
 * pages in any order. It holds at most maxPerPage rows: fewer, or none, where identifiers
 * are missing. Rows created with an identifier above the last page are left to the listener.
 */
final class IdRangePager implements PagerInterface
{
    private int $currentPage = 1;
    private int $maxPerPage = 10;
    private ?int $nbResults = null;
    private ?int $maxIdentifier = null;

    /**
     * @param string $identifierField the root entity's integer identifier, prefixed with its alias
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly string $identifierField,
    ) {
    }

    public function getNbResults(): int
    {
        return $this->nbResults ??= (int) $this->createAggregateQuery('COUNT(DISTINCT %s)')->getSingleScalarResult();
    }

    public function getNbPages(): int
    {
        $this->maxIdentifier ??= (int) $this->createAggregateQuery('MAX(%s)')->getSingleScalarResult();

        return \max(1, (int) \ceil($this->maxIdentifier / $this->maxPerPage));
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(int $page): void
    {
        $this->currentPage = $page;
    }

    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    public function setMaxPerPage(int $perPage): void
    {
        $this->maxPerPage = $perPage;
    }

    public function getCurrentPageResults(): iterable
    {
        return (clone $this->queryBuilder)
            ->andWhere(\sprintf('%1$s > :fos_elastica_id_range_from AND %1$s <= :fos_elastica_id_range_to', $this->identifierField))
            ->setParameter('fos_elastica_id_range_from', ($this->currentPage - 1) * $this->maxPerPage)
            ->setParameter('fos_elastica_id_range_to', $this->currentPage * $this->maxPerPage)
            ->orderBy($this->identifierField)
            ->getQuery()
            ->getResult()
        ;
    }

    private function createAggregateQuery(string $aggregate): Query
    {
        return (clone $this->queryBuilder)
            ->select(\sprintf($aggregate, $this->identifierField))
            ->resetDQLPart('orderBy')
            ->getQuery()
        ;
    }
}
