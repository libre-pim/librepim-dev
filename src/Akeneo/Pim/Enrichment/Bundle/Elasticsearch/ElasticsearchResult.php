<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Elasticsearch;

use Akeneo\Pim\Enrichment\Component\Product\Query\ResultInterface;

/**
 * @author    Nicolas Marniesse <nicolas.marniesse@akeneo.com>
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class ElasticsearchResult implements ResultInterface
{
    /** @var array */
    private $rawResult;

    public function __construct($rawResult)
    {
        if ($rawResult instanceof \Elastic\Elasticsearch\Response\Elasticsearch) {
            $rawResult = $rawResult->asArray();
        }

        if (!is_array($rawResult)) {
            throw new \InvalidArgumentException(sprintf(
                'Argument 1 passed to "%s" must be of the type array or instance of "%s", "%s" given.',
                __METHOD__,
                \Elastic\Elasticsearch\Response\Elasticsearch::class,
                get_debug_type($rawResult)
            ));
        }

        $this->rawResult = $rawResult;
    }

    public function getRawResult(): array
    {
        return $this->rawResult;
    }
}
