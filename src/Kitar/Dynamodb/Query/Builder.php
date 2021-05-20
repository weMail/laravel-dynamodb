<?php

namespace Kitar\Dynamodb\Query;

use Closure;
use BadMethodCallException;
use Illuminate\Support\Collection;
use Kitar\Dynamodb\Collections\ItemCollection;
use Kitar\Dynamodb\Connection;
use Illuminate\Support\Str;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Kitar\Dynamodb\Paginator;

/**
 * Class Builder
 * @method Builder keyConditionBetween($key, array $values)
 * @method Builder keyCondition($key, $operator, $value = null)
 *
 * @package Kitar\Dynamodb\Query
 */
class Builder extends BaseBuilder
{

    /**
     * Name of the index.
     * @var string|null
     */
    public $index;

    /**
     * The key.
     * @var array
     */
    public $key = [];

    /**
     * The item.
     * @var array
     */
    public $item = [];

    /**
     * The key/values to update.
     * @var array
     */
    public $updates = [
        'set' => [],
        'remove' => [],
        'add' => [],
        'append' => []
    ];

    /**
     * LastEvaluatedKey option.
     * @var array|null
     */
    public $exclusive_start_key;

    /**
     * ConsistentRead option.
     * @var boolean|null
     */
    public $consistent_read;

    /**
     * dry run option.
     * @var boolean
     */
    public $dry_run = false;

    /**
     * The model class name used to transform the DynamoDB responses.
     * @var string|null
     */
    public $model_class;

    /**
     * The ExpressionAttributes object.
     * @var \Kitar\Dynamodb\Query\ExpressionAttributes
     */
    protected $expression_attributes;

    /**
     * Available where methods which will pass to dedicated queries.
     * @var array
     */
    protected $available_wheres;

    /**
     * The attribute name to place compiled wheres.
     * @var string
     */
    protected $where_as;

    /**
     * Dedicated query for building FilterExpression.
     * @var \Kitar\Dynamodb\Query\Builder
     */
    protected $filter_query;

    /**
     * Dedicated query for building ConditionExpression.
     * @var \Kitar\Dynamodb\Query\Builder
     */
    protected $condition_query;

    /**
     * Dedicated query for building KeyConditionExpression.
     * @var \Kitar\Dynamodb\Query\Builder
     */
    protected $key_condition_query;

    protected $rawParams = [];

    /**
     * Create a new query builder instance.
     *
     * @param Kitar\Dynamodb\Connection $connection
     * @param Kitar\Dynamodb\Query\Grammar $grammar
     * @param Kitar\Dynamodb\Query\Processor $processor
     * @param Kitar\Dynamodb\Query\ExpressionAttributes|null $expression_attributes
     * @param bool $is_nested_query
     * @return void
     */
    public function __construct(Connection $connection, Grammar $grammar, Processor $processor, $expression_attributes = null, $is_nested_query = false)
    {
        $this->connection = $connection;

        $this->grammar = $grammar;

        $this->processor = $processor;

        $this->expression_attributes = $expression_attributes ?? new ExpressionAttributes;

        if (! $is_nested_query) {
            $this->initializeDedicatedQueries();
        }
    }

    /**
     * Set the index name.
     *
     * @param string $index
     * @return $this
     */
    public function index(string $index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @return $this
     */
    public function orderDesc()
    {
        $this->rawParams['ScanIndexForward'] = false;
        return $this;
    }

    /**
     * Set the key.
     *
     * @param array $key
     * @return $this
     */
    public function key(array $key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Set the ExclusiveStartKey option.
     *
     * @param array $key
     * @return $this
     */
    public function exclusiveStartKey($key)
    {
        $this->exclusive_start_key = $key;

        return $this;
    }

    /**
     * Set the ConsistentRead option.
     *
     * @param bool $active
     * @return $this
     */
    public function consistentRead($active = true)
    {
        $this->consistent_read = $active;

        return $this;
    }

    /**
     * Raw params
     *
     * @param array $params
     * @return $this
     */
    public function rawParams($params = [])
    {
        $this->rawParams = array_merge($this->rawParams, $params);

        return $this;
    }

    /**
     * Set the dry run option.
     *
     * @param bool $active
     * @return $this
     */
    public function dryRun($active = true)
    {
        $this->dry_run = $active;

        return $this;
    }

    /**
     * If set, response items will be converted to the model instance by using:
     * (new $model_class)->newFromBuilder($item).
     *
     * @var string
     * @return $this
     */
    public function usingModel($class_name)
    {
        $this->model_class = $class_name;

        return $this;
    }

    /**
     * Set key name of wheres. eg. FilterExpression
     *
     * @param string $condition_key_name
     * @return $this
     */
    protected function whereAs($condition_key_name)
    {
        $this->where_as = $condition_key_name;

        return $this;
    }

    /**
     * Get the where_as attribute.
     *
     * @return string
     */
    public function getWhereAs()
    {
        return $this->where_as;
    }

    /**
     * Get item.
     *
     * @param array|null $key
     * @return array|null
     */
    public function getItem($key = null)
    {
        if ($key) {
            $this->key($key);
        }

        return $this->process('getItem', 'processSingleItem');
    }

    /**
     * Put item.
     *
     * @param array $item
     * @return \Aws\Result
     */
    public function putItem($item)
    {
        $this->item = $item;

        return $this->process('putItem', null);
    }

    /**
     * Delete item.
     *
     * @param array $key;
     * @return \Aws\Result
     */
    public function deleteItem($key)
    {
        $this->key($key);

        return $this->process('deleteItem', null);
    }

    /**
     * Append item on list
     *
     * @param $item
     * @return array|Aws\Result|Illuminate\Support\Collection
     */
    public function append($item)
    {
        $emptyValue = $this->expression_attributes->addValue([]);

        foreach ($item as $name => $value) {
            $name = $this->expression_attributes->addName($name);
            $value = $this->expression_attributes->addValue($value);

            $this->updates['set'][] = "{$name} = list_append(if_not_exists({$name}, {$emptyValue}), {$value})";
        }

        return $this->process('updateItem', 'processSingleItem');
    }

    /**
     * Update item.
     *
     * @param array $item
     * @return array|null
     */
    public function updateItem($item)
    {
        foreach ($item as $name => $value) {
            $name = $this->expression_attributes->addName($name);

            // If value is null, it will pass to REMOVE actions.
            if ($value === null) {
                $this->updates['remove'][] = $name;

            // If value set, it will pass to SET actions.
            } else {
                $value = $this->expression_attributes->addValue($value);
                $this->updates['set'][] = "{$name} = {$value}";
            }
        }

        return $this->process('updateItem', 'processSingleItem');
    }

    /**
     * @inheritdoc
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        return $this->incrementOrDecrement($column, '+', $amount, $extra);
    }

    /**
     * @inheritdoc
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->incrementOrDecrement($column, '-', $amount, $extra);
    }

    /**
     * Increment or decrement column's value by a given amount.
     *
     * @param $column
     * @param $symbol
     * @param int $amount
     * @param array $extra
     * @return array|\Aws\Result|Aws\Result|Illuminate\Support\Collection
     */
    protected function incrementOrDecrement($column, $symbol, $amount = 1, array $extra = [])
    {
        $mode = 'set';
        if (Str::startsWith($column, 'add:')) {
            $column = mb_substr($column, 4);
            $mode = 'add';
        }

        $name = $this->expression_attributes->addName($column);
        $value = $this->expression_attributes->addValue($amount);
        if ($mode === 'add') {
            $this->updates[$mode][] = "{$name} {$value}";
        } else {
            $this->updates[$mode][] = "{$name} = {$name} {$symbol} {$value}";
        }

        return $this->updateItem($extra);
    }

    /**
     * Query.
     *
     * @return ItemCollection
     */
    public function query()
    {
        return $this->process('clientQuery', 'processMultipleItems');
    }

    /**
     * @inheritDoc
     * @return Paginator
     */
    public function paginate($perPage = 25, $columns = ['*'], $pageName = 'next-page', $page = null)
    {
        $this->limit($perPage);

        $cursor = Paginator::resolveCurrentPage($pageName);

        if (is_array($cursor)) {
            $this->exclusiveStartKey($cursor);
        }

        /** @var ItemCollection $items */
        $items = $this->process('clientQuery', 'processMultipleItems');

        return new Paginator($items, $items->getLastEvaluatedKey(true), $items->itemsCount(), $perPage);
    }

    /**
     * Scan.
     *
     * @param  array $columns
     * @return Illuminate\Support\Collection|array
     */
    public function scan($columns = [])
    {
        if (! empty($columns)) {
            $this->select($columns);
        }

        return $this->process('scan', 'processMultipleItems');
    }

    /**
     * Make individual Builder instance for condition types.
     *
     * @return void
     */
    protected function initializeDedicatedQueries()
    {
        // Set builder instances.
        $this->filter_query = $this->newQuery()->whereAs('FilterExpression');
        $this->condition_query = $this->newQuery()->whereAs('ConditionExpression');
        $this->key_condition_query = $this->newQuery()->whereAs('KeyConditionExpression');

        // Make method map.
        // Array of: 'incomingMethodName' => [ 'target_builder_instance_name', 'targetMethodName' ]
        foreach (['filter', 'condition', 'key_condition'] as $query_type) {
            foreach (['', 'or'] as $boolean) {
                foreach (['', 'in', 'between'] as $where_type) {
                    $target_query = $query_type . '_query';
                    $source_method = Str::camel(implode('_', [$boolean, $query_type, $where_type]));
                    $target_method = Str::camel(implode('_', [$boolean, 'where', $where_type]));

                    $this->available_wheres[$source_method] = [$target_query, $target_method];
                }
            }
        }
    }

    /**
     * Perform where methods within dedicated queries.
     *
     * @param string $method
     * @param array $parameters
     * @return $this
     */
    public function __call($method, $parameters)
    {
        if (isset($this->available_wheres[$method])) {
            $target_query = $this->available_wheres[$method][0];
            $target_method = $this->available_wheres[$method][1];

            $this->$target_query = call_user_func_array([$this->$target_query, $target_method], $parameters);

            return $this;
        }

        throw new BadMethodCallException('Call to undefined method ' . static::class . "::{$method}()");
    }

    /**
     * @inheritdoc
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // Convert column and value to ExpressionAttributes.
        if (! $column instanceof Closure) {
            $column = $this->expression_attributes->addName($column);
            if ($value !== null) {
                $value = $this->expression_attributes->addValue($value);
            }
        }

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            $operator = $this->expression_attributes->addValue($operator);
            [$value, $operator] = [$operator, '='];
        }

        $type = 'Basic';

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $this->wheres[] = compact(
            'type',
            'column',
            'operator',
            'value',
            'boolean'
        );

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * @inheritdoc
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $column = $this->expression_attributes->addName($column);

        foreach ($values as &$value) {
            $value = $this->expression_attributes->addValue($value);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * @inheritdoc
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $column = $this->expression_attributes->addName($column);

        foreach ($values as &$value) {
            $value = $this->expression_attributes->addValue($value);
        }

        return parent::whereBetween($column, $values, $boolean, $not);
    }

    /**
     * @inheritdoc
     */
    public function newQuery()
    {
        return new static($this->connection, $this->grammar, $this->processor, $this->expression_attributes, true);
    }

    /**
     * Execute DynamoDB call and returns processed result.
     *
     * @param string $query_method
     * @param array $params
     * @param string $processor_method
     * @return array|Illuminate\Support\Collection|Aws\Result
     */
    protected function process($query_method, $processor_method)
    {
        // Compile columns and wheres attributes.
        // These attributes needs to intaract with ExpressionAttributes during compile,
        // so it need to run before compileExpressionAttributes.
        $params = array_merge(
            $this->grammar->compileProjectionExpression($this->columns, $this->expression_attributes),
            $this->grammar->compileConditions($this->filter_query),
            $this->grammar->compileConditions($this->condition_query),
            $this->grammar->compileConditions($this->key_condition_query)
        );

        // Compile rest of attributes.
        $params = array_merge(
            $params,
            $this->grammar->compileTableName($this->from),
            $this->grammar->compileIndexName($this->index),
            $this->grammar->compileKey($this->key),
            $this->grammar->compileItem($this->item),
            $this->grammar->compileUpdates($this->updates),
            $this->grammar->compileDynamodbLimit($this->limit),
            $this->grammar->compileExclusiveStartKey($this->exclusive_start_key),
            $this->grammar->compileConsistentRead($this->consistent_read),
            $this->grammar->compileExpressionAttributes($this->expression_attributes)
        );

        if (!empty($this->rawParams)) {
            $params = array_merge($params, $this->rawParams);
        }

        // Dry run.
        if ($this->dry_run) {
            return [
                'method' => $query_method,
                'params' => $params,
                'processor' => $processor_method
            ];
        }

        // Execute.
        $response = $this->connection->$query_method($params);

        // Process.
        if ($processor_method) {
            return $this->processor->$processor_method($response, $this->model_class);
        } else {
            return $response;
        }
    }

    /**
     * Execute batch get items operation
     *
     * @param array $operations
     * @return array
     */
    public function batchGetItem(array $operations)
    {
        $params = [];
        $queryMethod = 'batchGetItem';

        foreach ($operations as $operation) {
            /** @var Builder $operation */
            if (! array_key_exists($operation->from, $params)) {
                $params[$operation->from] = [];
            }

            if ($this->key instanceof Collection) {
                $this->key = $this->key->toArray();
            }

            if (! is_array(reset($operation->key))) {
                $operation->key = [$operation->key];
            }

            $params[$operation->from] = array_merge_recursive(
                $params[$operation->from],
                $operation->grammar->compileConsistentRead($operation->consistent_read),
                $operation->grammar->compileKeys($operation->key)
            );
        }

        $params = [
            'RequestItems' => $params
        ];

        // Dry run.
        if ($this->dry_run) {
            return [
                'method' => $queryMethod,
                'params' => $params,
                'processor' => null
            ];
        }

        return $this->processor->processBatchGetItem(
            $this->connection->$queryMethod($params),
        );
    }

    /**
     * Batch write
     *
     * @param array $operations
     * @return array
     */
    public function batchWriteItem(array $operations)
    {
        $params = [];
        $queryMethod = 'batchWriteItem';

        foreach ($operations as $operation) {
            if (! ($operation instanceof Batch)) {
                continue;
            }

            if (! array_key_exists($operation->builder->from, $params)) {
                $params[$operation->builder->from] = [];
            }

            foreach ($operation->process() as $request) {
                $params[$operation->builder->from] = array_merge(
                    $params[$operation->builder->from],
                    $request
                );
            }
        }

        $params = [
            'RequestItems' => $params
        ];

        // Dry run.
        if ($this->dry_run) {
            return [
                'method' => $queryMethod,
                'params' => $params,
                'processor' => null
            ];
        }

        return $this->processor->processBatchWriteItem(
            $this->connection->$queryMethod($params)
        );
    }

    /**
     * Put items in batch operation
     *
     * @param $item
     * @return Batch
     */
    public function putItemBatch($item)
    {
        return (new Batch($this))
            ->putItemBatch($item);
    }

    /**
     *  Delete items in batch operation
     *
     * @param $key
     * @return Batch
     */
    public function deleteItemBatch($key)
    {
        return (new Batch($this))
            ->deleteItemBatch($key);
    }
}
