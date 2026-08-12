<?php

declare(strict_types=1);

namespace SIQA\Agent;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use SIQA\Agent\Exceptions\ApiException;
use SIQA\Agent\Exceptions\NetworkException;
use SIQA\Agent\Models\SIQAConfig;

class Client
{
    private SIQAConfig $config;
    private HttpClient $http;

    public readonly Heartbeat $heartbeat;
    public readonly SchemaInjection $schemaInjection;
    public readonly CitationMonitor $citationMonitor;
    public readonly OfflineQueue $offlineQueue;

    private ?string $deploymentId = null;
    private string $mode = 'monitor';

    public function __construct(SIQAConfig $config, ?HttpClient $http = null)
    {
        $this->config = $config;
        $this->http = $http ?? new HttpClient([
            'base_uri' => rtrim($config->baseUrl, '/') . '/',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $config->apiKey,
            ],
            'timeout' => $config->timeout,
        ]);

        $this->heartbeat = new Heartbeat($this->http, $config->brandId);
        $this->schemaInjection = new SchemaInjection($this->http, $config->brandId);
        $this->citationMonitor = new CitationMonitor($this->http, $config->brandId);
        $this->offlineQueue = new OfflineQueue();
    }

    /**
     * Static convenience constructor accepting raw strings.
     */
    public static function create(
        string $apiKey,
        string $brandId,
        string $baseUrl = 'https://siqaai.com',
        float $timeout = 15.0,
    ): self {
        return new self(new SIQAConfig($apiKey, $brandId, $baseUrl, $timeout));
    }

    /**
     * Initialise tracking for a page URL and auto-start heartbeat.
     *
     * @throws ApiException
     * @throws NetworkException
     */
    public function init(string $pageUrl, string $pageContent = ''): array
    {
        $contentHash = $this->computeContentHash($pageContent);
        try {
            $response = $this->http->request('POST', 'sdk/v1/init', [
                'json' => [
                    'brand_id' => $this->config->brandId,
                    'page_url' => $pageUrl,
                    'content_hash' => $contentHash,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new NetworkException('Init request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];
        $this->deploymentId = $data['deployment_id'] ?? null;
        $this->mode = $data['mode'] ?? 'monitor';

        $this->heartbeat->start($pageUrl);
        $this->citationMonitor->start();

        return $data;
    }

    /**
     * @return SchemaRecommendation[]
     */
    public function getSchemaRecommendations(string $pageUrl): array
    {
        return $this->schemaInjection->getRecommendations($pageUrl);
    }

    /**
     * Report successfully injected schemas back to SIQA.
     */
    public function reportSchemaInjection(string $pageUrl, array $schemas): array
    {
        try {
            $response = $this->http->request('POST', 'sdk/v1/schema-result', [
                'json' => [
                    'url' => $pageUrl,
                    'schemas' => $schemas,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new NetworkException('Schema result request failed: ' . $e->getMessage(), 0, $e);
        }
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    public function pollCitations(): array
    {
        return $this->citationMonitor->poll();
    }

    public function sendHeartbeat(string $pageUrl): HeartbeatResponse
    {
        return $this->heartbeat->send($pageUrl);
    }

    public function submitPageReport(array $report): array
    {
        try {
            $response = $this->http->request('POST', 'sdk/v1/page-report', ['json' => $report]);
        } catch (GuzzleException $e) {
            throw new NetworkException('Page report request failed: ' . $e->getMessage(), 0, $e);
        }
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * Generate an AEO-scored content brief.
     */
    public function generateContent(array $opts): array
    {
        try {
            $response = $this->http->request('POST', 'api/v2/content/generate', ['json' => $opts]);
        } catch (GuzzleException $e) {
            throw new NetworkException('Content generation request failed: ' . $e->getMessage(), 0, $e);
        }
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    public function computeContentHash(string $content): string
    {
        return sha1($content);
    }

    /**
     * Stop background timers and monitors.
     */
    public function stop(): void
    {
        $this->heartbeat->stop();
        $this->citationMonitor->stop();
    }

    public function getDeploymentId(): ?string
    {
        return $this->deploymentId;
    }

    public function getMode(): string
    {
        return $this->mode;
    }
}
