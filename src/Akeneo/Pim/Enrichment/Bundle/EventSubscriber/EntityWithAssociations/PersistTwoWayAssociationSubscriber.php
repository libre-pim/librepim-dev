<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\EventSubscriber\EntityWithAssociations;

use Akeneo\Pim\Enrichment\Component\Product\Association\RemovedTwoWayAssociationCollector;
use Akeneo\Pim\Enrichment\Component\Product\Model\AssociationInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithAssociationsInterface;
use Akeneo\Pim\Enrichment\Component\Product\Storage\Indexer\ProductIndexerInterface;
use Akeneo\Pim\Enrichment\Component\Product\Storage\Indexer\ProductModelIndexerInterface;
use Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final class PersistTwoWayAssociationSubscriber implements EventSubscriberInterface
{
    private array $productUuidsToIndex = [];
    private array $productModelCodesToIndex = [];
    private array $productUuidsToIndexOnRemove = [];
    private array $productModelCodesToIndexOnRemove = [];

    public function __construct(
        private ManagerRegistry $registry,
        private ProductIndexerInterface $productIndexer,
        private ProductModelIndexerInterface $productModelIndexer,
        private RemovedTwoWayAssociationCollector $removedAssociationCollector
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorageEvents::PRE_SAVE => 'handlePreSave',
            StorageEvents::POST_SAVE => 'indexAssociatedEntities',
            StorageEvents::PRE_REMOVE => 'handlePreRemove',
            StorageEvents::POST_REMOVE => 'indexAssociatedEntitiesOnRemove',
        ];
    }

    public function handlePreSave(GenericEvent $event): void
    {
        $entity = $event->getSubject();

        if (!$entity instanceof EntityWithAssociationsInterface) {
            return;
        }

        // TODO TIP-987 Remove this when decoupling PublishedProduct from Enrichment
        if ('Akeneo\Pim\WorkOrganization\Workflow\Component\Model\PublishedProduct' === \get_class($entity)) {
            return;
        }

        $em = $this->registry->getManager();

        /** @var AssociationInterface $association */
        foreach ($entity->getAssociations() as $association) {
            $associationType = $association->getAssociationType();

            if (!$associationType->isTwoWay()) {
                continue;
            }

            foreach ($association->getProducts() as $product) {
                $em->persist($product);
                $this->productUuidsToIndex[] = $product->getUuid();
            }

            foreach ($association->getProductModels() as $productModel) {
                $em->persist($productModel);
                $this->productModelCodesToIndex[] = $productModel->getCode();
            }
        }
    }

    public function handlePreRemove(RemoveEvent $event): void
    {
        $entity = $event->getSubject();

        if (!$entity instanceof EntityWithAssociationsInterface) {
            return;
        }

        // TODO TIP-987 Remove this when decoupling PublishedProduct from Enrichment
        if ('Akeneo\Pim\WorkOrganization\Workflow\Component\Model\PublishedProduct' === \get_class($entity)) {
            return;
        }

        /** @var AssociationInterface $association */
        foreach ($entity->getAssociations() as $association) {
            $associationType = $association->getAssociationType();

            if (!$associationType->isTwoWay()) {
                continue;
            }

            foreach ($association->getProducts() as $product) {
                $this->productUuidsToIndexOnRemove[] = $product->getUuid();
            }

            foreach ($association->getProductModels() as $productModel) {
                $this->productModelCodesToIndexOnRemove[] = $productModel->getCode();
            }
        }
    }

    public function indexAssociatedEntities(): void
    {
        // Entities removed from a two-way association are no longer part of the associations of the saved entity,
        // so they are collected at removal time instead of being read from the associations here.
        $this->productIndexer->indexFromProductUuids(
            \array_merge($this->productUuidsToIndex, $this->removedAssociationCollector->getProductUuids())
        );
        $this->productModelIndexer->indexFromProductModelCodes(
            \array_merge($this->productModelCodesToIndex, $this->removedAssociationCollector->getProductModelCodes())
        );

        $this->productUuidsToIndex = [];
        $this->productModelCodesToIndex = [];
        $this->removedAssociationCollector->reset();
    }

    public function indexAssociatedEntitiesOnRemove(): void
    {
        $this->productIndexer->indexFromProductUuids($this->productUuidsToIndexOnRemove);
        $this->productModelIndexer->indexFromProductModelCodes($this->productModelCodesToIndexOnRemove);

        $this->productUuidsToIndexOnRemove = [];
        $this->productModelCodesToIndexOnRemove = [];
    }
}
