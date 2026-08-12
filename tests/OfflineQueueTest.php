<?php

declare(strict_types=1);

namespace SIQA\Agent\Tests;

use PHPUnit\Framework\TestCase;
use SIQA\Agent\OfflineQueue;

class OfflineQueueTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'siqa-test-queue-') . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testEnqueueIncreasesSize(): void
    {
        $q = new OfflineQueue($this->tmpFile);
        $q->enqueue('heartbeat', ['url' => 'https://example.com']);
        $q->enqueue('heartbeat', ['url' => 'https://example.com/2']);
        $this->assertSame(2, $q->size());
    }

    public function testFlushSendsAllEventsAndClears(): void
    {
        $q = new OfflineQueue($this->tmpFile);
        $q->enqueue('heartbeat', ['url' => 'https://a.com']);
        $q->enqueue('citation', ['id' => '1']);

        $sent = [];
        $q->flush(function (array $event) use (&$sent) {
            $sent[] = $event;
        });

        $this->assertCount(2, $sent);
        $this->assertSame(0, $q->size());
    }

    public function testFlushRetainsEventsUnderMaxRetries(): void
    {
        $q = new OfflineQueue($this->tmpFile);
        $q->enqueue('heartbeat', ['url' => 'https://a.com']);

        $fail = fn() => throw new \RuntimeException('network error');

        $q->flush($fail);
        $this->assertSame(1, $q->size()); // retries=1

        $q->flush($fail);
        $this->assertSame(1, $q->size()); // retries=2

        $q->flush($fail);
        $this->assertSame(0, $q->size()); // retries=3, dropped
    }

    public function testClearEmptiesQueue(): void
    {
        $q = new OfflineQueue($this->tmpFile);
        $q->enqueue('t', []);
        $q->enqueue('t', []);
        $q->clear();
        $this->assertSame(0, $q->size());
    }

    public function testEnqueueReturnsUniqueIds(): void
    {
        $q = new OfflineQueue($this->tmpFile);
        $id1 = $q->enqueue('t', []);
        $id2 = $q->enqueue('t', []);
        $this->assertNotSame($id1, $id2);
    }

    public function testFlushNoopWhenEmpty(): void
    {
        $q = new OfflineQueue($this->tmpFile);
        $called = false;
        $q->flush(function () use (&$called) { $called = true; });
        $this->assertFalse($called);
    }

    public function testQueuePersistsAcrossInstances(): void
    {
        $q1 = new OfflineQueue($this->tmpFile);
        $q1->enqueue('heartbeat', ['url' => 'https://example.com']);

        // New instance reads from same file
        $q2 = new OfflineQueue($this->tmpFile);
        $this->assertSame(1, $q2->size());
    }
}
