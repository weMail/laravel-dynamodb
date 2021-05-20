<?php


namespace Kitar\Dynamodb\Query;


use Kitar\Dynamodb\BatchOperations\Delete;
use Kitar\Dynamodb\BatchOperations\Put;
use Kitar\Dynamodb\Contracts\Operation;

class Batch
{
    /**
     * @var Builder $builder
     */
    public $builder;

    /**
     * List of operations
     *
     * @var array $operations
     */
    protected $operations = [];

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Delete item in batch
     *
     * @param array $key [Single or multiple key]
     * @return $this
     */
    public function deleteItemBatch($key)
    {
        $this->operations[] = new Delete($this->builder, $key);
        return $this;
    }

    /**
     * Put item in batch mode
     *
     * @param $item
     * @return $this
     */
    public function putItemBatch($item)
    {
        $this->operations[] = new Put($this->builder, $item);
        return $this;
    }

    /**
     * Process operations
     */
    public function process()
    {
        return array_map(function (Operation $operation){
            return $operation->process();
        }, $this->operations);
    }
}
