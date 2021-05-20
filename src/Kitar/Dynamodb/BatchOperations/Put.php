<?php
namespace Kitar\Dynamodb\BatchOperations;

use Kitar\Dynamodb\Contracts\Operation;
use Kitar\Dynamodb\Query\Builder;

class Put implements Operation
{
    /**
     * @var Builder $builder
     */
    protected $builder;

    /**
     * @var array $item
     */
    protected $item;

    public function __construct(Builder $builder, $item)
    {
        $this->builder = $builder;
        $this->item = $item;
    }

    /**
     * Process a single item
     *
     * @return array[]
     */
    public function process()
    {
        if (! is_array(reset($this->item)) ) {
            $this->item = [$this->item];
        }

        return array_map(function ($item){
            return [
                'PutRequest' => $this->builder->grammar->compileItem($item)
            ];
        }, $this->item);
    }
}
