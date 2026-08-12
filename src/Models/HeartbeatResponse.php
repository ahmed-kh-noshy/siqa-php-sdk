<?php

declare(strict_types=1);

namespace SIQA\Agent\Models;

final class HeartbeatResponse
{
    public function __construct(
        public readonly string $installationId,
        public readonly string $status,
        public readonly array $config = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            installationId: $data['installation_id'] ?? '',
            status: $data['status'] ?? '',
            config: $data['config'] ?? [],
        );
    }
}
