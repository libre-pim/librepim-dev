<?php

declare(strict_types=1);

namespace Specification\Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Denormalizer;

use Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Denormalizer\MultiColumnValueMerger;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\Metric;
use Akeneo\Pim\Enrichment\Component\Product\Model\PriceCollection;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductPrice;
use Akeneo\Pim\Enrichment\Component\Product\Model\WriteValueCollection;
use Akeneo\Pim\Enrichment\Component\Product\Value\MetricValue;
use Akeneo\Pim\Enrichment\Component\Product\Value\PriceCollectionValue;
use Akeneo\Pim\Enrichment\Component\Product\Value\ScalarValue;
use PhpSpec\ObjectBehavior;

class MultiColumnValueMergerSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldHaveType(MultiColumnValueMerger::class);
    }

    public function it_keeps_the_currencies_that_are_absent_from_the_imported_file(
        EntityWithValuesInterface $product
    ): void {
        $product->getValues()->willReturn($this->priceValues());

        $item = ['values' => ['price' => [
            ['locale' => null, 'scope' => null, 'data' => [['amount' => '130.00', 'currency' => 'USD']]],
        ]]];

        $this->merge($product, $item)->shouldReturn(['values' => ['price' => [
            ['locale' => null, 'scope' => null, 'data' => [
                ['amount' => '130.00', 'currency' => 'USD'],
                ['amount' => '100.00', 'currency' => 'EUR'],
            ]],
        ]]]);
    }

    public function it_lets_the_imported_currency_win_over_the_existing_one(
        EntityWithValuesInterface $product
    ): void {
        $product->getValues()->willReturn($this->priceValues());

        $item = ['values' => ['price' => [
            ['locale' => null, 'scope' => null, 'data' => [
                ['amount' => '130.00', 'currency' => 'USD'],
                ['amount' => null, 'currency' => 'EUR'],
            ]],
        ]]];

        // EUR is present in the file, so the existing amount is not restored and the currency gets cleared.
        $this->merge($product, $item)->shouldReturn($item);
    }

    public function it_does_not_touch_values_that_are_not_price_collections(
        EntityWithValuesInterface $product
    ): void {
        $product->getValues()->willReturn(
            new WriteValueCollection([ScalarValue::value('name', 'a name')])
        );

        $item = ['values' => ['name' => [
            ['locale' => null, 'scope' => null, 'data' => 'another name'],
        ]]];

        $this->merge($product, $item)->shouldReturn($item);
    }

    public function it_does_not_touch_an_item_without_values(EntityWithValuesInterface $product): void
    {
        $this->merge($product, ['enabled' => true])->shouldReturn(['enabled' => true]);
    }

    public function it_does_not_merge_when_the_entity_has_no_value_yet(
        EntityWithValuesInterface $product
    ): void {
        $product->getValues()->willReturn(new WriteValueCollection());

        $item = ['values' => ['price' => [
            ['locale' => null, 'scope' => null, 'data' => [['amount' => '130.00', 'currency' => 'USD']]],
        ]]];

        $this->merge($product, $item)->shouldReturn($item);
    }

    public function it_keeps_the_metric_amount_when_only_the_unit_is_imported(
        EntityWithValuesInterface $product
    ): void {
        $product->getValues()->willReturn($this->metricValues());

        $item = ['values' => ['weight' => [
            ['locale' => null, 'scope' => null, 'data' => ['amount' => null, 'unit' => 'KILOGRAM']],
        ]]];

        $this->merge($product, $item)->shouldReturn(['values' => ['weight' => [
            ['locale' => null, 'scope' => null, 'data' => ['amount' => '10.0000', 'unit' => 'KILOGRAM']],
        ]]]);
    }

    public function it_keeps_the_metric_unit_when_only_the_amount_is_imported(
        EntityWithValuesInterface $product
    ): void {
        $product->getValues()->willReturn($this->metricValues());

        $item = ['values' => ['weight' => [
            ['locale' => null, 'scope' => null, 'data' => ['amount' => '25', 'unit' => null]],
        ]]];

        $this->merge($product, $item)->shouldReturn(['values' => ['weight' => [
            ['locale' => null, 'scope' => null, 'data' => ['amount' => '25', 'unit' => 'GRAM']],
        ]]]);
    }

    public function it_does_not_change_a_metric_when_both_parts_are_imported(
        EntityWithValuesInterface $product
    ): void {
        $product->getValues()->willReturn($this->metricValues());

        $item = ['values' => ['weight' => [
            ['locale' => null, 'scope' => null, 'data' => ['amount' => '5', 'unit' => 'TON']],
        ]]];

        $this->merge($product, $item)->shouldReturn($item);
    }

    private function metricValues(): WriteValueCollection
    {
        return new WriteValueCollection([
            MetricValue::value('weight', new Metric('Weight', 'GRAM', '10.0000', 'KILOGRAM', '0.010000000000')),
        ]);
    }

    private function priceValues(): WriteValueCollection
    {
        $prices = new PriceCollection([
            new ProductPrice('100.00', 'EUR'),
            new ProductPrice('110.00', 'USD'),
        ]);

        return new WriteValueCollection([PriceCollectionValue::value('price', $prices)]);
    }
}
