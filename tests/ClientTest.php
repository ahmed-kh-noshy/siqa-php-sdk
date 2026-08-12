<?php

declare(strict_types=1);

namespace SIQA\Agent\Tests;

use PHPUnit\Framework\TestCase;
use SIQA\Agent\Client;
use SIQA\Agent\Models\SIQAConfig;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ClientTest extends TestCase
{
    private function clientWithResponses(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $http = new HttpClient([
            'handler' => $stack,
            'base_uri' => 'https://siqaai.com/',
            'headers' => ['X-API-Key' => 'test_key'],
        ]);

        return new Client(new SIQAConfig('test_key', 'test_brand'), $http);
    }

    public function testInitReturnsDeploymentConfig(): void
    {
        // init() fires three requests: init POST, then heartbeat->start() and
        // citationMonitor->start() (one immediate heartbeat + one citation poll).
        $client = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'deployment_id' => 'dep-123',
                'mode' => 'autonomous',
                'enabled_features' => ['schema' => true],
                'agent_config' => [],
            ])),
            new Response(200, [], json_encode(['installation_id' => 'inst-1', 'status' => 'ok', 'config' => []])),
            new Response(200, [], json_encode(['citations' => []])),
        ]);

        $result = $client->init('https://example.com/page');

        $this->assertSame('dep-123', $result['deployment_id']);
        $this->assertSame('autonomous', $result['mode']);
    }

    public function testCreateFactoryBuildsClient(): void
    {
        $client = Client::create('test_key', 'test_brand', 'https://siqaai.com');
        $this->assertInstanceOf(Client::class, $client);
    }

    public function testComputeContentHash(): void
    {
        $client = Client::create('test_key', 'test_brand');
        $hash = $client->computeContentHash('hello world');
        $this->assertEquals(40, strlen($hash));      // SHA-1 hex digest
        $this->assertSame($hash, $client->computeContentHash('hello world'));
    }
}
