<?php
namespace Kitar\Dynamodb\Collections;

use Illuminate\Database\Eloquent\Collection;

class UnprocessedItemCollection extends Collection
{
    public function __construct($data)
    {
        parent::__construct($data['UnprocessedItems']);
    }
}
