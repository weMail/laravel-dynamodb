<?php

namespace Kitar\Dynamodb\Query;

use Aws\Result;
use Aws\DynamoDb\Marshaler;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;
use Illuminate\Support\Collection;
use Kitar\Dynamodb\Collections\BatchGetItemCollection;
use Kitar\Dynamodb\Collections\ItemCollection;
use Kitar\Dynamodb\Collections\UnprocessedItemCollection;
use Kitar\Dynamodb\Paginator;

class Processor extends BaseProcessor
{
    public $marshaler;

    public function __construct()
    {
        $this->marshaler = new Marshaler;
    }

    protected function unmarshal(Result $res)
    {
        $responseArray = $res->toArray();

        if (! empty($responseArray['Item'])) {
            $responseArray['Item'] = $this->marshaler->unmarshalItem($responseArray['Item']);
        }

        if (! empty($responseArray['Items'])) {
            foreach ($responseArray['Items'] as &$item) {
                $item = $this->marshaler->unmarshalItem($item);
            }
        }

        if (! empty($responseArray['Attributes'])) {
            $responseArray['Attributes'] = $this->marshaler->unmarshalItem($responseArray['Attributes']);
        }

        return $responseArray;
    }

    public function processSingleItem(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass)) {
            return $response;
        }

        if (! empty($response['Item'])) {
            $item = (new $modelClass)->newFromBuilder($response['Item']);
            unset($response['Item']);
            $item->setMeta($response ?? null);
            return $item;
        }

        if (! empty($response['Attributes'])) {
            return $response;
        }
    }

    public function processMultipleItems(Result $awsResponse, $modelClass = null)
    {
        $response = $this->unmarshal($awsResponse);

        if (empty($modelClass)) {
            return $response;
        }

        $items = new ItemCollection();

        foreach ($response['Items'] as $item) {
            $item = (new $modelClass)->newFromBuilder($item);
            $items->push($item);
        }

        unset($response['Items']);

        return $items->setMetaData($response);
    }

    /**
     * Process batch item get response
     *
     * @param Result $result
     * @return BatchGetItemCollection
     */
    public function processBatchGetItem(Result $result)
    {
        $data = $result->toArray();
        $tempItems = [];

        foreach ($data['Responses'] as $table => $items) {
            $tempItems[$table] = [];

            foreach ($items as $item) {
                $tempItems[$table][] = $this->marshaler->unmarshalItem($item);
            }

            $tempItems[$table] = new Collection($tempItems[$table]);
        }

        $data['Responses'] = $tempItems;

        return new BatchGetItemCollection($data);
    }

    /**
     * Process batch write response
     *
     * @param Result $result
     * @return UnprocessedItemCollection
     */
    public function processBatchWriteItem(Result $result)
    {
        $data = $result->toArray();

        $tempItems = [];

        foreach ($data['UnprocessedItems'] as $table => $items) {
            foreach ($items as $item) {
                $tempItems[$table][] = $this->marshaler->unmarshalItem($item);
            }
        }

        $data['UnprocessedItems'] = $tempItems;

        return new UnprocessedItemCollection($data);
    }
}
