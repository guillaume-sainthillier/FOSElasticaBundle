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

use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Message\AsyncPersistPage;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @phpstan-import-type TPagerPersisterOptions from PagerPersisterInterface
 */
final class AsyncPagerPersister implements PagerPersisterInterface
{
    public const NAME = 'async';
    private const DEFAULT_PAGE_SIZE = 100;

    public function __construct(
        private readonly PagerPersisterRegistry $pagerPersisterRegistry,
        private readonly PagerProviderRegistry $pagerProviderRegistry,
        private readonly MessageBusInterface $messageBus,
        private readonly ?IndexManager $indexManager = null,
    ) {
    }

    public function insert(PagerInterface $pager, array $options = []): void
    {
        $pager->setMaxPerPage(empty($options['max_per_page']) ? self::DEFAULT_PAGE_SIZE : $options['max_per_page']);

        $options = \array_replace([
            'max_per_page' => $pager->getMaxPerPage(),
            'first_page' => $pager->getCurrentPage(),
            'last_page' => $pager->getNbPages(),
        ], $options);

        $pager->setCurrentPage($options['first_page']);

        $lastPage = \min($options['last_page'], $pager->getNbPages());
        $page = $pager->getCurrentPage();

        if (null !== $this->indexManager && isset($options['indexName'])) {
            // With an alias, the populate fills a new index that only this process knows by its name:
            // through the alias, the workers would write into the previous index, deleted once the alias switches
            $options['target_index_name'] = $this->indexManager->getIndex($options['indexName'])->getName();
        }

        do {
            $this->messageBus->dispatch(new AsyncPersistPage($page, $options));

            ++$page;
        } while ($page <= $lastPage);
    }

    /**
     * @phpstan-param TPagerPersisterOptions $options
     */
    public function insertPage(int $page, array $options = []): void
    {
        if (!isset($options['indexName'])) {
            throw new \RuntimeException('Invalid call. $options is missing the indexName key.');
        }
        if (!isset($options['max_per_page'])) {
            throw new \RuntimeException('Invalid call. $options is missing the max_per_page key.');
        }

        $options['first_page'] = $page;
        $options['last_page'] = $page;

        $index = $this->overrideIndexName($options);

        try {
            $provider = $this->pagerProviderRegistry->getProvider($options['indexName']);
            $pager = $provider->provide($options);
            $pager->setMaxPerPage($options['max_per_page']);
            $pager->setCurrentPage($options['first_page']);

            /** @var InPlacePagerPersister $pagerPersister */
            $pagerPersister = $this->pagerPersisterRegistry->getPagerPersister(InPlacePagerPersister::NAME);
            $pagerPersister->insert($pager, $options);
        } finally {
            $index?->restoreName();
        }
    }

    /**
     * Points the index to the one the populate command fills, when the worker knows it by another name.
     *
     * @phpstan-param TPagerPersisterOptions $options
     */
    private function overrideIndexName(array $options): ?Index
    {
        if (null === $this->indexManager || !isset($options['target_index_name'], $options['indexName'])) {
            return null;
        }

        $index = $this->indexManager->getIndex($options['indexName']);
        if ($index->getName() === $options['target_index_name']) {
            return null;
        }

        $index->overrideName($options['target_index_name']);

        return $index;
    }
}
