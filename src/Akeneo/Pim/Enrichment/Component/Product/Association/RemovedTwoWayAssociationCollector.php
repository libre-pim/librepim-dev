<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Association;

use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithAssociationsInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Collects the entities that lost a two-way association, so that they can be indexed after the save.
 *
 * When an entity is removed from a two-way association, its inversed association is removed too and it is persisted,
 * but it is no longer part of the associations of the saved entity. It is therefore invisible to
 * PersistTwoWayAssociationSubscriber, which only iterates over the remaining associations.
 */
class RemovedTwoWayAssociationCollector
{
    /** @var UuidInterface[] */
    private array $productUuids = [];

    /** @var string[] */
    private array $productModelCodes = [];

    public function collect(EntityWithAssociationsInterface $associatedEntity): void
    {
        if ($associatedEntity instanceof ProductInterface) {
            $this->productUuids[] = $associatedEntity->getUuid();
        } elseif ($associatedEntity instanceof ProductModelInterface) {
            $this->productModelCodes[] = $associatedEntity->getCode();
        }
    }

    /**
     * @return UuidInterface[]
     */
    public function getProductUuids(): array
    {
        return $this->productUuids;
    }

    /**
     * @return string[]
     */
    public function getProductModelCodes(): array
    {
        return $this->productModelCodes;
    }

    public function reset(): void
    {
        $this->productUuids = [];
        $this->productModelCodes = [];
    }
}
