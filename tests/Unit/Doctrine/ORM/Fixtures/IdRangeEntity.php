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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class IdRangeEntity
{
    /**
     * @var Collection<int, IdRangeChild>
     */
    #[ORM\OneToMany(targetEntity: IdRangeChild::class, mappedBy: 'parent', cascade: ['persist'])]
    public Collection $children;

    public function __construct(
        #[ORM\Id, ORM\Column]
        public int $id,
        #[ORM\Column]
        public bool $active = true,
    ) {
        $this->children = new ArrayCollection();
    }
}
