<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Denormalizer;

use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyVariantInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductPriceInterface;
use Akeneo\Pim\Enrichment\Component\Product\Value\MetricValueInterface;
use Akeneo\Pim\Enrichment\Component\Product\Value\PriceCollectionValueInterface;

/**
 * Restores the parts of a value that the imported file does not carry.
 *
 * A price collection holds every currency in a single value, and a metric holds its amount and its unit in a single
 * value, while the flat format spreads them over several columns ("price-EUR", "price-USD", "weight", "weight-unit").
 * Importing only some of those columns would therefore replace the whole value and empty the missing parts. Locales
 * and scopes do not behave that way, because each of them has its own value.
 *
 * What the file provides always wins, so an empty column still clears the part it refers to.
 */
class MultiColumnValueMerger
{
    public function merge(EntityWithValuesInterface $entity, array $filteredItem): array
    {
        if (!\is_array($filteredItem['values'] ?? null)) {
            return $filteredItem;
        }

        // On a variant entity, getValues() also returns the values inherited from the parent. Merging those in would
        // copy them onto the child, so only the values that belong to the entity itself are considered here.
        $ownValues = $entity instanceof EntityWithFamilyVariantInterface
            ? $entity->getValuesForVariation()
            : $entity->getValues();

        foreach ($filteredItem['values'] as $attributeCode => $values) {
            if (!\is_array($values)) {
                continue;
            }

            foreach ($values as $index => $value) {
                if (!\is_array($value['data'] ?? null)) {
                    continue;
                }

                $existingValue = $ownValues->getByCodes(
                    $attributeCode,
                    $value['scope'] ?? null,
                    $value['locale'] ?? null
                );

                if ($existingValue instanceof PriceCollectionValueInterface) {
                    $filteredItem['values'][$attributeCode][$index]['data'] = $this->mergePrices(
                        $existingValue,
                        $value['data']
                    );
                } elseif ($existingValue instanceof MetricValueInterface) {
                    $filteredItem['values'][$attributeCode][$index]['data'] = $this->mergeMetric(
                        $existingValue,
                        $value['data']
                    );
                }
            }
        }

        return $filteredItem;
    }

    private function mergePrices(PriceCollectionValueInterface $existingValue, array $importedData): array
    {
        $importedCurrencies = \array_column($importedData, 'currency');
        $mergedData = $importedData;

        /** @var ProductPriceInterface $price */
        foreach ($existingValue->getData() ?? [] as $price) {
            if (!\in_array($price->getCurrency(), $importedCurrencies, true)) {
                $mergedData[] = ['amount' => $price->getData(), 'currency' => $price->getCurrency()];
            }
        }

        return $mergedData;
    }

    /**
     * A file holding only the unit column converts to an amount-less metric, and a file holding only the amount
     * column converts to a unit-less one. The missing part is taken back from the entity, so that changing the unit
     * does not drop the amount and the other way around. Clearing a metric empties both columns, which converts to a
     * null value and therefore never reaches this method.
     */
    private function mergeMetric(MetricValueInterface $existingValue, array $importedData): array
    {
        $existingMetric = $existingValue->getData();
        if (null === $existingMetric) {
            return $importedData;
        }

        $mergedData = $importedData;

        if (null === ($mergedData['amount'] ?? null)) {
            $mergedData['amount'] = $existingMetric->getData();
        }

        if (null === ($mergedData['unit'] ?? null)) {
            $mergedData['unit'] = $existingMetric->getUnit();
        }

        return $mergedData;
    }
}
