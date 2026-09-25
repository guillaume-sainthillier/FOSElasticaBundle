<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use FOS\ElasticaBundle\Doctrine\ORM\IdRangePager;
use FOS\ElasticaBundle\Doctrine\ORMPagerProvider;
use FOS\ElasticaBundle\Doctrine\RegisterListenersService;
use FOS\ElasticaBundle\Provider\PagerfantaPager;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use FOS\ElasticaBundle\Tests\Unit\Mocks\DoctrineORMCustomRepositoryMock;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * @internal
 */
class ORMPagerProviderTest extends TestCase
{
    public function testShouldImplementPagerProviderInterface(): void
    {
        $rc = new \ReflectionClass(ORMPagerProvider::class);

        $this->assertTrue($rc->implementsInterface(PagerProviderInterface::class));
    }

    public function testCouldBeConstructedWithExpectedArguments(): void
    {
        $doctrine = $this->createDoctrineMock();
        $objectClass = 'anObjectClass';
        $baseConfig = [];

        new ORMPagerProvider($doctrine, $this->createRegisterListenersServiceMock(), $objectClass, $baseConfig);
    }

    public function testShouldReturnPagerfantaPagerWithDoctrineORMAdapter(): void
    {
        $objectClass = 'anObjectClass';
        $baseConfig = ['query_builder_method' => 'createQueryBuilder'];

        $expectedBuilder = $this->createMock(QueryBuilder::class);
        $expectedBuilder->method('getDQLPart')
            ->with('orderBy')
            ->willReturn([$this->createMock(OrderBy::class)])
        ;

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($expectedBuilder)
        ;

        $manager = $this->createMock(EntityManager::class);
        $manager
            ->expects($this->once())
            ->method('getRepository')
            ->with($objectClass)
            ->willReturn($repository)
        ;

        $doctrine = $this->createDoctrineMock();
        $doctrine
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with($objectClass)
            ->willReturn($manager)
        ;

        $provider = new ORMPagerProvider($doctrine, $this->createRegisterListenersServiceMock(), $objectClass, $baseConfig);

        $pager = $provider->provide();

        $this->assertInstanceOf(PagerfantaPager::class, $pager);

        $adapter = $pager->getPagerfanta()->getAdapter();
        $this->assertInstanceOf(QueryAdapter::class, $adapter);
    }

    public function testShouldAllowCallCustomRepositoryMethod(): void
    {
        $objectClass = 'anObjectClass';
        $baseConfig = ['query_builder_method' => 'createQueryBuilder'];

        $expectedBuilder = $this->createMock(QueryBuilder::class);
        $expectedBuilder->method('getDQLPart')
            ->with('orderBy')
            ->willReturn([$this->createMock(OrderBy::class)])
        ;

        $repository = $this->createMock(DoctrineORMCustomRepositoryMock::class);
        $repository
            ->expects($this->once())
            ->method('createCustomQueryBuilder')
            ->willReturn($expectedBuilder)
        ;

        $manager = $this->createMock(EntityManager::class);
        $manager
            ->expects($this->once())
            ->method('getRepository')
            ->with($objectClass)
            ->willReturn($repository)
        ;

        $doctrine = $this->createDoctrineMock();
        $doctrine
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with($objectClass)
            ->willReturn($manager)
        ;

        $provider = new ORMPagerProvider($doctrine, $this->createRegisterListenersServiceMock(), $objectClass, $baseConfig);

        $pager = $provider->provide(['query_builder_method' => 'createCustomQueryBuilder']);

        $this->assertInstanceOf(PagerfantaPager::class, $pager);
    }

    public function testShouldCallRegisterListenersService(): void
    {
        $objectClass = 'anObjectClass';
        $baseConfig = ['query_builder_method' => 'createQueryBuilder'];

        $expectedBuilder = $this->createMock(QueryBuilder::class);
        $expectedBuilder->method('getDQLPart')
            ->with('orderBy')
            ->willReturn([$this->createMock(OrderBy::class)])
        ;

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($expectedBuilder)
        ;

        $manager = $this->createMock(EntityManager::class);
        $manager
            ->expects($this->once())
            ->method('getRepository')
            ->with($objectClass)
            ->willReturn($repository)
        ;

        $doctrine = $this->createDoctrineMock();
        $doctrine
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with($objectClass)
            ->willReturn($manager)
        ;

        $registerListenersMock = $this->createRegisterListenersServiceMock();
        $registerListenersMock
            ->expects($this->once())
            ->method('register')
            ->with($this->identicalTo($manager), $this->isInstanceOf(PagerInterface::class), $baseConfig)
        ;

        $provider = new ORMPagerProvider($doctrine, $registerListenersMock, $objectClass, $baseConfig);

        $provider->provide();
    }

    public function testShouldReturnIdRangePager(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['a']);
        $queryBuilder->expects($this->never())->method('addOrderBy');

        $pager = $this->createIdRangeProvider($queryBuilder, 'integer')->provide();

        $this->assertInstanceOf(IdRangePager::class, $pager);
    }

    public function testIdRangePaginationNeedsAnIntegerIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "id_range" pagination mode of "anObjectClass" needs a single integer identifier.');

        $this->createIdRangeProvider($this->createMock(QueryBuilder::class), 'guid')->provide();
    }

    public function testIdRangePaginationNeedsAQueryBuilder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "id_range" pagination mode of "anObjectClass" needs "createCustomQueryBuilder()" to return a QueryBuilder.');

        $this->createIdRangeProvider($this->createMock(QueryBuilder::class), 'integer', 'createCustomQueryBuilder')->provide();
    }

    private function createIdRangeProvider(QueryBuilder $queryBuilder, string $identifierType, string $queryBuilderMethod = 'createQueryBuilder'): ORMPagerProvider
    {
        $repository = $this->createMock(DoctrineORMCustomRepositoryMock::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('getTypeOfField')->with('id')->willReturn($identifierType);

        $manager = $this->createMock(EntityManager::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->method('getClassMetadata')->with('anObjectClass')->willReturn($metadata);

        $doctrine = $this->createDoctrineMock();
        $doctrine->method('getManagerForClass')->willReturn($manager);

        return new ORMPagerProvider($doctrine, $this->createRegisterListenersServiceMock(), 'anObjectClass', [
            'query_builder_method' => $queryBuilderMethod,
            'pagination_mode' => 'id_range',
        ]);
    }

    /**
     * @return RegisterListenersService|\PHPUnit\Framework\MockObject\MockObject
     */
    private function createRegisterListenersServiceMock(): \PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(RegisterListenersService::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ManagerRegistry
     */
    private function createDoctrineMock(): \PHPUnit\Framework\MockObject\MockObject
    {
        return $this->createMock(ManagerRegistry::class);
    }
}
