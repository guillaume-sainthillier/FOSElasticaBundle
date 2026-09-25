<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Tests\Unit\Doctrine\ORM\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class IdRangeChild
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    public ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: IdRangeEntity::class, inversedBy: 'children')]
        public IdRangeEntity $parent,
    ) {
        $parent->children->add($this);
    }
}
