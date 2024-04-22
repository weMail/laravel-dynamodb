<?php

namespace Kitar\Dynamodb\Tests;

use Mockery as m;
use Kitar\Dynamodb\Connection;
use Kitar\Dynamodb\Query\Builder;
use Aws\DynamoDb\DynamoDbClient;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class ConnectionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected $connection;

    protected function setUp() :void
    {
        $this->connection = new Connection([]);
    }

    protected function tearDown() :void
    {
        m::close();
    }

    /** @test */
    public function it_creates_connection()
    {
        $this->assertInstanceOf(Connection::class, $this->connection);
    }

    /** @test */
    public function it_returns_dynamodb_client()
    {
        $this->assertInstanceOf(
            DynamoDbClient::class,
            $this->connection->getClient()
        );
    }

    /** @test */
    public function it_returns_driver_name()
    {
        $this->assertEquals(
            'dynamodb',
            $this->connection->getDriverName()
        );
    }

    /** @test */
    public function it_destroys_connection()
    {
        $this->connection->disconnect();

        $this->assertNull($this->connection->getClient());
    }

    /** @test */
    public function it_returns_query_builder_instance()
    {
        $query = $this->connection->table('test');

        $this->assertInstanceOf(Builder::class, $query);

        $this->assertEquals('test', $query->from);
    }

    /** @test */
    public function it_can_call_client_query()
    {
        $client = m::mock(DynamoDbClient::class);
        $client->shouldReceive('query')->with([
            'TableName' => 'User'
        ])->andReturn('mocked_result');

        $connection = new Connection([]);
        $connection->setClient($client);
        $result = $connection->clientQuery([
            'TableName' => 'User'
        ]);

        $this->assertNotNull($result);
        $this->assertEquals('mocked_result', $result);
    }

    /** @test */
    public function it_can_forward_call_to_dynamodb_client()
    {
        $client = m::mock(DynamoDbClient::class);
        $client->shouldReceive('getItem')->with([
            'TableName' => 'User'
        ])->andReturn('mocked_result');

        $connection = new Connection([]);
        $connection->setClient($client);
        $result = $connection->getItem([
            'TableName' => 'User'
        ]);
        $this->assertEquals('mocked_result', $result);
    }
}
