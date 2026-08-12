<?php

declare(strict_types=1);

namespace SIQA\Agent;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use SIQA\Agent\Exceptions\ApiException;
use SIQA\Agent\Exceptions\NetworkException;
use SIQA\Agent\Models\HeartbeatResponse;

class Heartbeat
{
    private const SDK_VERSION = '1.0.1';

    private ClientInterface $http;
    private string $brandId;
    private ?string $installationId = null;

    public function __construct(ClientInterface $http, string $brandId)
    {
        $this->http = $http;
        $this->brandId = $brandId;
    }

    /**
     * Send a heartbeat ping and return the response.
     *
     * @throws ApiException
     * @throws NetworkException
     */
    public function send(string $pageUrl): HeartbeatResponse
    {
        $host = parse_url($pageUrl, PHP_URL_HOST) ?? '';
        $domain = preg_replace('/^www\./', '', $host) ?? $host;

        try {
            $response = $this->http->request('POST', 'sdk/v1/heartbeat', [
                'json' => [
                    'domain' => $domain,
                    'sdk_version' => self::SDK_VERSION,
                    'page_url' => $pageUrl,
                ],
            ]);
        } catch (BadResponseException $e) {
            // Guzzle's http_errors middleware throws on 4xx/5xx — surface as an
            // ApiException carrying the status code (NetworkException is for
            // transport-level failures only).
            throw new ApiException(
                'Heartbeat API returned ' . $e->getResponse()->getStatusCode(),
                $e->getResponse()->getStatusCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new NetworkException('Heartbeat request failed: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException("Heartbeat API returned {$statusCode}", $statusCode);
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];
        $result = HeartbeatResponse::fromArray($data);

        if (!empty($result->installationId)) {
            $this->installationId = $result->installationId;
        }

        return $result;
    }

    /**
     * Start periodic heartbeat pings.
     *
     * NOTE: PHP is request-scoped; this method records the page URL and
     * sends one immediate heartbeat. For true background loops, call
     * send() periodically from a long-running CLI script or job worker.
     */
    public function start(string $pageUrl): void
    {
        // Send one immediate heartbeat to register the installation.
        $this->send($pageUrl);
    }

    /**
     * Stop the heartbeat. No-op in standard PHP SAPI.
     */
    public function stop(): void
    {
        // PHP request lifecycle ends after script execution;
        // nothing to clean up unless using ReactPHP/Swoole.
    }

    public function getInstallationId(): ?string
    {
        return $this->installationId;
    }
}
