<?php
namespace Kitar\Dynamodb\Collections;

use Illuminate\Database\Eloquent\Collection;

class BatchGetItemCollection extends Collection
{
    /**
     * @var array $meta
     */
    protected $meta = [];

    /**
     * @var array $unprocessedKeys
     */
    protected $unprocessedKeys = [];

    public function __construct($response)
    {
        parent::__construct($response['Responses']);

        $this->meta = $response['@metadata'];
        $this->unprocessedKeys = $response['UnprocessedKeys'];
    }

    /**
     * Get meta information
     *
     * @return array
     */
    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Get unprocessed keys
     *
     * @return array
     */
    public function getUnprocessedKeys()
    {
        return $this->unprocessedKeys;
    }
}
