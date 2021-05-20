<?php
namespace Kitar\Dynamodb;

use ArrayAccess;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use IteratorAggregate;
use JsonSerializable;

class Paginator extends AbstractPaginator implements JsonSerializable, Arrayable, ArrayAccess, IteratorAggregate
{
    /**
     * @inheritdoc
     */
    protected $pageName = 'next-page';

    /**
     * @var int $perPage
     */
    protected $perPage;

    /**
     * @var int
     */
    protected $itemsCount;

    public function __construct($items = [], $cursor = null, $itemsCount = 0, $perPage = 25)
    {
        $this->items = $items;

        if (! is_null($cursor)) {
            $this->options[$this->pageName] = Crypt::encrypt($cursor);
        }
        $this->itemsCount = $itemsCount;
        $this->perPage = $perPage;
    }

    public static function resolveCurrentPage($pageName = 'next-page', $default = 1)
    {
        $query = call_user_func(static::$queryStringResolver);

        if (! isset($query[$pageName])) {
            return null;
        }

        try {
            return Crypt::decrypt($query[$pageName]);
        }catch (EncryptException $e) {
            // Ignore exception
        }

        return null;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'data'      => $this->items->toArray(),
            'paginator' => 'db',
            'to'        => $this->itemsCount,
            'per_page'   => $this->perPage
        ] + ($this->options ?? []);
    }


    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Get the URL for a given page number.
     *
     * @param $cursor
     * @return string
     */
    public function url($cursor)
    {
        // If we have any extra query string key / value pairs that need to be added
        // onto the URL, we will put them in query string form and then attach it
        // to the URL. This allows for extra information like sortings storage.
        $parameters = [$this->pageName => $cursor];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        return $this->path()
            .(Str::contains($this->path(), '?') ? '&' : '?')
            .Arr::query($parameters)
            .$this->buildFragment();
    }
}
