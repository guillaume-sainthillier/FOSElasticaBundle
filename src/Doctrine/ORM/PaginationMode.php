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

/**
 * How the Doctrine ORM pager provider pages the objects to index.
 */
enum PaginationMode: string
{
    /**
     * Pages with LIMIT and OFFSET, through Doctrine's paginator.
     */
    case Offset = 'offset';

    /**
     * Pages by blocks of identifiers, see {@see IdRangePager}.
     */
    case IdRange = 'id_range';
}
