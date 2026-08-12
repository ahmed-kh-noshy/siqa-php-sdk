<?php

declare(strict_types=1);

namespace SIQA\Agent;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use SIQA\Agent\Exceptions\ApiException;
use SIQA\Agent\Exceptions\NetworkException;

class CitationMonitor
{
    private ClientInterface $http;
    private string $brandId;
    private ?string $lastSeen = null;

    public function __construct(ClientInterface $http, string $brandId)
    {
        $this->http = $http;
        $this->brandId = $brandId;
    }

    /**
     * Poll for new citation events since the last call.
     *
     * @return array<int,array>
     * @throws ApiException
     * @throws NetworkException
     */
    public function poll(): array
    {
        $params = ['brand_id' => $this->brandId];
        if ($this->lastSeen !== null) {
            $params['since'] = $this->lastSeen;
        }

        try {
            $response = $this->http->request('GET', 'api/v2/sdk/citations', [
                'query' => $params,
            ]);
        } catch (BadResponseException $e) {
            throw new ApiException(
                'Citation API returned ' . $e->getResponse()->getStatusCode(),
                $e->getResponse()->getStatusCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new NetworkException('Citation poll request failed: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException("Citation API returned {$statusCode}", $statusCode);
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];
        $events = $data['citations'] ?? [];

        if (!empty($events)) {
            $last = end($events);
            $this->lastSeen = $last['detected_at'] ?? null;
        }

        return $events;
    }

    /**
     * Begin monitoring. PHP has no background event loop, so this performs a
     * single initial poll to establish the lastSeen cursor (mirroring
     * Heartbeat::start sending one immediate beat). Errors are swallowed so a
     * transient citation failure never breaks Client::init().
     *
     * @param callable|null $onEvents Optional callback invoked with any events found.
     */
    public function start(?callable $onEvents = null): void
    {
        try {
            $events = $this->poll();
            if ($onEvents !== null && !empty($events)) {
                $onEvents($events);
            }
        } catch (\Throwable $e) {
            // Non-fatal: monitoring is best-effort during init.
        }
    }

    /**
     * Stop the citation monitor. No-op in standard PHP SAPI.
     */
    public function stop(): void
    {
        // PHP request lifecycle ends after script execution.
    }

    public function getLastSeen(): ?string
    {
        return $this->lastSeen;
    }
}
