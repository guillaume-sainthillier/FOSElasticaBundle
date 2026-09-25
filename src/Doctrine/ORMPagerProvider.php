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

use Doctrine\ORM\Query\Expr\From;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use FOS\ElasticaBundle\Doctrine\ORM\IdRangePager;
use FOS\ElasticaBundle\Doctrine\ORM\PaginationMode;
use FOS\ElasticaBundle\Provider\PagerfantaPager;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

final class ORMPagerProvider implements PagerProviderInterface
{
    public const ENTITY_ALIAS = 'a';

    public function __construct(private readonly ManagerRegistry $doctrine, private readonly RegisterListenersService $registerListenersService, private readonly string $objectClass, private readonly array $baseOptions)
    {
    }

    public function provide(array $options = []): PagerInterface
    {
        $options = \array_replace($this->baseOptions, $options);

        $manager = $this->doctrine->getManagerForClass($this->objectClass);
        $repository = $manager->getRepository($this->objectClass);

        $qb = \call_user_func([$repository, $options['query_builder_method']], self::ENTITY_ALIAS);

        $paginationMode = PaginationMode::from($options['pagination_mode'] ?? PaginationMode::Offset->value);
        $pager = match ($paginationMode) {
            PaginationMode::Offset => $this->createOffsetPager($qb, $manager),
            PaginationMode::IdRange => $this->createIdRangePager($qb, $manager, $options['query_builder_method']),
        };

        $this->registerListenersService->register($manager, $pager, $options);

        return $pager;
    }

    private function createOffsetPager(mixed $qb, ObjectManager $manager): PagerfantaPager
    {
        // Ensure that the query builder has a sorting configured. Without a ORDER BY clause, the SQL standard does not
        // guarantee any order, which breaks the pagination (second page might use a different sorting that when retrieving
        // the first page).
        // If the QueryBuilder already has its own ordering, or the method returned a Query instead of a QueryBuilder, we
        // assume that the query already provides a proper sorting. This allows giving full control over sorting if wanted
        // when using a custom method.
        if ($qb instanceof QueryBuilder && empty($qb->getDQLPart('orderBy'))) {
            // When getting root aliases, the QueryBuilder normalizes all from parts to From objects, in case they were added as string using the low-level API.
            // This side-effect allows us to be sure to get only From objects in the next call.
            $qb->getRootAliases();

            /** @var From[] $fromClauses */
            $fromClauses = $qb->getDQLPart('from');

            foreach ($fromClauses as $fromClause) {
                $identifiers = $manager->getClassMetadata($fromClause->getFrom())->getIdentifierFieldNames();

                foreach ($identifiers as $identifier) {
                    $qb->addOrderBy($fromClause->getAlias().'.'.$identifier);
                }
            }
        }

        return new PagerfantaPager(new Pagerfanta(new QueryAdapter($qb)));
    }

    private function createIdRangePager(mixed $qb, ObjectManager $manager, string $queryBuilderMethod): IdRangePager
    {
        if (!$qb instanceof QueryBuilder) {
            throw new \InvalidArgumentException(\sprintf('The "id_range" pagination mode of "%s" needs "%s()" to return a QueryBuilder.', $this->objectClass, $queryBuilderMethod));
        }

        $metadata = $manager->getClassMetadata($this->objectClass);
        $identifiers = $metadata->getIdentifierFieldNames();
        if (1 !== \count($identifiers) || !\in_array($metadata->getTypeOfField($identifiers[0]), ['integer', 'smallint', 'bigint'], true)) {
            throw new \InvalidArgumentException(\sprintf('The "id_range" pagination mode of "%s" needs a single integer identifier.', $this->objectClass));
        }

        return new IdRangePager($qb, $qb->getRootAliases()[0].'.'.$identifiers[0]);
    }
}
