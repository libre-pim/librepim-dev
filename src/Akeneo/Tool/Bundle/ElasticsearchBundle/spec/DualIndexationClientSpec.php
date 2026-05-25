<?php

declare(strict_types=1);

namespace spec\Akeneo\Tool\Bundle\ElasticsearchBundle;

use Akeneo\Tool\Bundle\ElasticsearchBundle\Client;
use Akeneo\Tool\Bundle\ElasticsearchBundle\DualIndexationClient;
use Akeneo\Tool\Bundle\ElasticsearchBundle\IndexConfiguration\Loader;
use Akeneo\Tool\Bundle\ElasticsearchBundle\Refresh;
use Elastic\Elasticsearch\ClientInterface as NativeClient;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Endpoints\Indices;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Elastic\Transport\Transport;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Elastic\Transport\NodePool\NodePoolInterface;

class DualIndexationClientSpec extends ObjectBehavior
{
    function let(
        MockDualIndexationElasticClientInterface $nativeClient,
        ClientBuilder $clientBuilder,
        Loader $indexConfigurationLoader,
        Client $dualClient,
        HttpClientInterface $httpClient,
        NodePoolInterface $nodePool,
        LoggerInterface $logger
    ) {
        // Create a real client to satisfy the type hint of ClientBuilder::build()
        $transport = new Transport($httpClient->getWrappedObject(), $nodePool->getWrappedObject(), $logger->getWrappedObject());
        $realClient = new \Elastic\Elasticsearch\Client($transport, $logger->getWrappedObject());

        $clientBuilder->setHosts(['localhost:9200'])->willReturn($clientBuilder);
        $clientBuilder->build()->willReturn($realClient);

        $this->beConstructedWith(
            $clientBuilder,
            $indexConfigurationLoader,
            ['localhost:9200'],
            'an_index_name',
            '',
            100000000,
            $dualClient
        );

        // Force instantiation and replace the client property with our mock
        $wrappedObject = $this->getWrappedObject();
        $reflection = new ReflectionClass(Client::class);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($wrappedObject, $nativeClient->getWrappedObject());
    }

    function it_can_be_instantiated()
    {
        $this->shouldBeAnInstanceOf(DualIndexationClient::class);
        $this->shouldBeAnInstanceOf(Client::class);
    }

    function it_indexes_on_both_clients(NativeClient $nativeClient, Client $dualClient)
    {
        $nativeClient->index(
            [
                'index' => 'an_index_name',
                'id' => 'identifier',
                'body' => ['a key' => 'a value'],
                'refresh' => 'wait_for',
            ]
        )->willReturn(['errors' => false]);
        $dualClient->index('identifier', ['a key' => 'a value'], Refresh::waitFor())->shouldBeCalled();

        $this->index('identifier', ['a key' => 'a value'], Refresh::waitFor())
            ->shouldReturn(['errors' => false]);
    }

    function it_bulk_indexes_on_both_clients(NativeClient $nativeClient, Client $dualClient)
    {
        $expectedResponse = [
            'took' => 1,
            'errors' => false,
            'items' => [
                ['item_foo'],
                ['item_bar'],
            ],
        ];

        $nativeClient->bulk([
            'body' => [
                ['index' => [
                    '_index' => 'an_index_name',
                    '_id' => 'foo',
                ]],
                ['identifier' => 'foo', 'name' => 'a name'],
                ['index' => [
                    '_index' => 'an_index_name',
                    '_id' => 'bar',
                ]],
                ['identifier' => 'bar', 'name' => 'a name'],
            ],
            'refresh' => 'wait_for',
        ])->shouldBeCalled()->willReturn($expectedResponse);;

        $documents = [
            ['identifier' => 'foo', 'name' => 'a name'],
            ['identifier' => 'bar', 'name' => 'a name'],
        ];

        $dualClient->bulkIndexes($documents, 'identifier', Refresh::waitFor())->shouldBeCalled();

        $this->bulkIndexes($documents, 'identifier', Refresh::waitFor())->shouldReturn($expectedResponse);
    }

    function it_deletes_by_query_on_both_clients(NativeClient $nativeClient, Client $dualClient)
    {
        $query = ['foo' => 'bar'];

        $nativeClient->deleteByQuery([
            'index' => 'an_index_name',
            'body' => $query,
        ])->shouldBeCalled();
        $dualClient->deleteByQuery($query)->shouldBeCalled();

        $this->deleteByQuery($query);
    }

    function it_refreshes_both_indexes(NativeClient $nativeClient, Client $dualClient, Indices $indices)
    {
        $nativeClient->indices()->willReturn($indices);
        $indices->refresh(['index' => 'an_index_name'])->willReturn(['errors' => false]);

        $dualClient->refreshIndex()->shouldBeCalled();

        $this->refreshIndex()->shouldReturn(['errors' => false]);
    }
}

interface MockDualIndexationElasticClientInterface extends \Elastic\Elasticsearch\ClientInterface
{
    public function index(array $params);
    public function bulk(array $params);
    public function get(array $params);
    public function search(array $params);
    public function msearch(array $params);
    public function count(array $params);
    public function delete(array $params);
    public function deleteByQuery(array $params);
    public function updateByQuery(array $params);
    public function indices();
}
