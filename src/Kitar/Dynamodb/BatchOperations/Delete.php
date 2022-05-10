<?php

namespace Kitar\Dynamodb\BatchOperations;

use Illuminate\Support\Collection;
use Kitar\Dynamodb\Contracts\Operation;
use Kitar\Dynamodb\Query\Builder;

class Delete implements Operation
{
    /**
     * @var array|Collection $key
     */
    protected $key;

    /**
     * @var Builder
     */
    protected $builder;

    public function __construct(Builder $builder, $key)
    {
        $this->builder = $builder;
        $this->key = $key;
    }

    /**
     * Process keys of request
     *
     * @return array|\array[][]
     */
    public function process()
    {
        if ($this->key instanceof Collection) {
            $this->key = $this->key->toArray();
        }

        if (! is_array(reset($this->key)) ) {
            $this->key = [$this->key];
        }

        return array_map(function ($key){
            return [
                'DeleteRequest' => $this->builder->grammar->compileKey($key)
            ];
        }, $this->key);
    }
}
