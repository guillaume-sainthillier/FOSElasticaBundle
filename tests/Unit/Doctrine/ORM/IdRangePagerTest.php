<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Tests\Unit\Doctrine\ORM;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use FOS\ElasticaBundle\Doctrine\ORM\IdRangePager;
use FOS\ElasticaBundle\Tests\Unit\Doctrine\ORM\Fixtures\IdRangeChild;
use FOS\ElasticaBundle\Tests\Unit\Doctrine\ORM\Fixtures\IdRangeEntity;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class IdRangePagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        // Native lazy objects from PHP 8.4, generated proxies (which need a proxy directory) before
        if (\PHP_VERSION_ID >= 80400 && \method_exists(ORMSetup::class, 'createAttributeMetadataConfig')) {
            $config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/Fixtures'], true);
            $config->enableNativeLazyObjects(true);
        } else {
            $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/Fixtures'], true);
        }

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testPagesByBlocksOfIdentifiers(): void
    {
        $this->createEntities([1, 2, 3, 5, 8, 13, 21, 34]);
        $pager = $this->createPager($this->createQueryBuilder());
        $pager->setMaxPerPage(10);

        $this->assertSame(4, $pager->getNbPages());
        $this->assertSame(8, $pager->getNbResults());
        $this->assertSame([[1, 2, 3, 5, 8], [13], [21], [34]], $this->getPages($pager));
    }

    public function testKeepsTheConditionsOfTheQueryBuilder(): void
    {
        $this->createEntities([1, 2, 3, 12], [2, 12]);
        $pager = $this->createPager($this->createQueryBuilder()->where('a.active = true'));
        $pager->setMaxPerPage(2);

        $this->assertSame(2, $pager->getNbPages());
        $this->assertSame(2, $pager->getNbResults());
        $this->assertSame([[1], [3]], $this->getPages($pager));
    }

    public function testFetchJoinedCollectionsAreComplete(): void
    {
        $this->createEntities([1, 2], [], 3);
        $pager = $this->createPager($this->createQueryBuilder()->leftJoin('a.children', 'c')->addSelect('c'));
        $pager->setMaxPerPage(1);

        $this->assertSame(2, $pager->getNbResults());
        foreach ([1, 2] as $page) {
            $pager->setCurrentPage($page);
            $results = [...$pager->getCurrentPageResults()];

            $this->assertCount(1, $results);
            $this->assertCount(3, $results[0]->children);
        }
    }

    public function testHasOnePageWithoutRows(): void
    {
        $pager = $this->createPager($this->createQueryBuilder());

        $this->assertSame(1, $pager->getNbPages());
        $this->assertSame([[]], $this->getPages($pager));
    }

    /**
     * @param list<int> $ids
     * @param list<int> $inactiveIds
     */
    private function createEntities(array $ids, array $inactiveIds = [], int $childrenPerEntity = 0): void
    {
        foreach ($ids as $id) {
            $entity = new IdRangeEntity($id, !\in_array($id, $inactiveIds, true));
            for ($i = 0; $i < $childrenPerEntity; ++$i) {
                new IdRangeChild($entity);
            }
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->getRepository(IdRangeEntity::class)->createQueryBuilder('a');
    }

    private function createPager(QueryBuilder $queryBuilder): IdRangePager
    {
        return new IdRangePager($queryBuilder, 'a.id');
    }

    /**
     * @return list<list<int>>
     */
    private function getPages(IdRangePager $pager): array
    {
        $pages = [];
        for ($page = 1; $page <= $pager->getNbPages(); ++$page) {
            $pager->setCurrentPage($page);
            $pages[] = \array_map(static fn (IdRangeEntity $entity): int => $entity->id, [...$pager->getCurrentPageResults()]);
            $this->entityManager->clear();
        }

        return $pages;
    }
}
