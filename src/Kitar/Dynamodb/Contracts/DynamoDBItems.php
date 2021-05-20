<?php
namespace Kitar\Dynamodb\Contracts;

interface DynamoDBItems
{
    public function getMetaData();

    public function setMetaData($metaData);
}
