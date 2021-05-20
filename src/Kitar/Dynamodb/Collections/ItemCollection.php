<?php
namespace Kitar\Dynamodb\Collections;

use Aws\DynamoDb\Marshaler;
use Illuminate\Support\Collection;
use Kitar\Dynamodb\Contracts\DynamoDBItems;

class ItemCollection extends Collection implements DynamoDBItems
{
    protected $metaData = [];

    /**
     * Set meta information
     *
     * @param $metaData
     * @return $this
     */
    public function setMetaData($metaData)
    {
        $this->metaData = $metaData;

        return $this;
    }

    /**
     * Get full meta data array
     *
     * @return array
     */
    public function getMetaData()
    {
        return $this->metaData;
    }

    /**
     * Get last evaluated key
     *
     * @return array|null
     */
    public function getLastEvaluatedKey($raw = false)
    {
        if (! array_key_exists('LastEvaluatedKey', $this->metaData)) {
            return null;
        }

        if ($raw) {
            return $this->metaData['LastEvaluatedKey'];
        }

        return (new Marshaler())->unmarshalItem($this->metaData['LastEvaluatedKey']);
    }

    /**
     * Get first evaluated key
     *
     * @return null|array
     */
    public function getFirstEvaluatedKey()
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->first()->getKey();
    }

    /**
     * Items count
     *
     * @return int
     */
    public function itemsCount()
    {
        return $this->metaData['Count'];
    }

    /**
     * Scanned count
     *
     * @return int
     */
    public function scannedCount()
    {
        return $this->metaData['ScannedCount'];
    }

    /**
     * Get meta data
     *
     * @return array
     */
    public function metaData()
    {
        return $this->metaData['@metadata'];
    }

    /**
     * Has next page
     *
     * @return bool
     */
    public function hasNextPage()
    {
        return array_key_exists('LastEvaluatedKey', $this->metaData);
    }
}
