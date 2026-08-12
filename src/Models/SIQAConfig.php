<?php

declare(strict_types=1);

namespace SIQA\Agent\Models;

final class SIQAConfig
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $brandId,
        public readonly string $baseUrl = 'https://siqaai.com',
        public readonly float $timeout = 15.0,
        public readonly bool $debug = false,
    ) {}
}
