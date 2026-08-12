<?php

declare(strict_types=1);

namespace SIQA\Agent\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\TestCase;
use SIQA\Agent\Heartbeat;
use SIQA\Agent\Exceptions\ApiException;

class HeartbeatTest extends TestCase
{
    private function makeClient(array $responses, array &$history = []): HttpClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        return new HttpClient(['handler' => $stack]);
    }

    public function testSendReturnsHeartbeatResponse(): void
    {
        $http = $this->makeClient([
            new Response(200, [], json_encode([
                'installation_id' => 'inst-abc',
                'status' => 'ok',
                'config' => [],
            ])),
        ]);

        $hb = new Heartbeat($http, 'brand-1');
        $result = $hb->send('https://example.com/page');

        $this->assertSame('inst-abc', $result->installationId);
        $this->assertSame('ok', $result->status);
    }

    public function testSendStoresInstallationId(): void
    {
        $http = $this->makeClient([
            new Response(200, [], json_encode([
                'installation_id' => 'inst-xyz',
                'status' => 'ok',
                'config' => [],
            ])),
        ]);

        $hb = new Heartbeat($http, 'brand-1');
        $hb->send('https://example.com/');

        $this->assertSame('inst-xyz', $hb->getInstallationId());
    }

    public function testSendStripsWwwFromDomain(): void
    {
        $history = [];
        $http = $this->makeClient([
            new Response(200, [], json_encode([
                'installation_id' => 'i',
                'status' => 'ok',
                'config' => [],
            ])),
        ], $history);

        $hb = new Heartbeat($http, 'brand-1');
        $hb->send('https://www.example.com/page');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('example.com', $body['domain']);
    }

    public function testSendThrowsApiExceptionOn5xx(): void
    {
        $this->expectException(ApiException::class);

        $http = $this->makeClient([new Response(500, [], '{"detail":"error"}')]);
        $hb = new Heartbeat($http, 'brand-1');
        $hb->send('https://example.com/');
    }

    public function testInstallationIdNullBeforeSend(): void
    {
        $http = $this->makeClient([]);
        $hb = new Heartbeat($http, 'brand-1');
        $this->assertNull($hb->getInstallationId());
    }
}
