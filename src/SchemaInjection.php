<?php

declare(strict_types=1);

namespace SIQA\Agent;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use SIQA\Agent\Exceptions\ApiException;
use SIQA\Agent\Exceptions\NetworkException;
use SIQA\Agent\Models\SchemaRecommendation;

class SchemaInjection
{
    private ClientInterface $http;
    private string $brandId;

    public function __construct(ClientInterface $http, string $brandId)
    {
        $this->http = $http;
        $this->brandId = $brandId;
    }

    /**
     * Fetch schema recommendations for a given page URL.
     *
     * @return SchemaRecommendation[]
     * @throws ApiException
     * @throws NetworkException
     */
    public function getRecommendations(string $pageUrl): array
    {
        try {
            $response = $this->http->request('GET', 'api/v2/sdk/schema-injection', [
                'query' => [
                    'brand_id' => $this->brandId,
                    'page_url' => $pageUrl,
                ],
            ]);
        } catch (BadResponseException $e) {
            throw new ApiException(
                'Schema injection API returned ' . $e->getResponse()->getStatusCode(),
                $e->getResponse()->getStatusCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new NetworkException('Schema injection request failed: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException("Schema injection API returned {$statusCode}", $statusCode);
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];
        return SchemaRecommendation::fromList($data['schemas'] ?? []);
    }

    /**
     * Report that schemas have been injected on a page.
     *
     * @param string[] $schemas
     * @throws ApiException
     * @throws NetworkException
     */
    public function reportInjected(string $pageUrl, array $schemas): void
    {
        try {
            $response = $this->http->request('POST', 'sdk/v1/schema-result', [
                'json' => ['url' => $pageUrl, 'schemas' => $schemas],
            ]);
        } catch (BadResponseException $e) {
            throw new ApiException(
                'Schema result API returned ' . $e->getResponse()->getStatusCode(),
                $e->getResponse()->getStatusCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new NetworkException('Schema result report failed: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException("Schema result API returned {$statusCode}", $statusCode);
        }
    }
}
