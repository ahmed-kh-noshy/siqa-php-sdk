<?php

declare(strict_types=1);

namespace SIQA\Agent;

use SIQA\Agent\Exceptions\QueueException;

class OfflineQueue
{
    private const MAX_RETRIES = 3;

    private string $filePath;

    /** @var array<int,array{id:string,type:string,payload:array,timestamp:int,retries:int}> */
    private array $events = [];

    public function __construct(string $filePath = '.siqa-queue.json')
    {
        $this->filePath = $filePath;
        $this->load();
    }

    private function load(): void
    {
        if (!file_exists($this->filePath)) {
            return;
        }
        $raw = file_get_contents($this->filePath);
        if ($raw === false) {
            return;
        }
        $this->events = json_decode($raw, true) ?? [];
    }

    private function persist(): void
    {
        file_put_contents($this->filePath, json_encode($this->events));
    }

    public function enqueue(string $type, array $payload): string
    {
        $id = bin2hex(random_bytes(16));
        $this->events[] = [
            'id' => $id,
            'type' => $type,
            'payload' => $payload,
            'timestamp' => (int) (microtime(true) * 1000),
            'retries' => 0,
        ];
        $this->persist();
        return $id;
    }

    public function size(): int
    {
        return count($this->events);
    }

    /**
     * Flush all queued events using the provided sender callable.
     *
     * @param callable(array): void $sender
     */
    public function flush(callable $sender): void
    {
        $remaining = [];

        foreach ($this->events as $event) {
            try {
                $sender($event);
            } catch (\Throwable) {
                $event['retries']++;
                if ($event['retries'] < self::MAX_RETRIES) {
                    $remaining[] = $event;
                }
            }
        }

        $this->events = $remaining;
        $this->persist();
    }

    public function clear(): void
    {
        $this->events = [];
        $this->persist();
    }
}
