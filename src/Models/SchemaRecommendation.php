<?php

declare(strict_types=1);

namespace SIQA\Agent\Models;

final class SchemaRecommendation
{
    public function __construct(
        public readonly string $type,
        public readonly string $action,
        public readonly array $schema = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? '',
            action: $data['action'] ?? '',
            schema: $data['schema'] ?? [],
        );
    }

    /** @param array<int,array> $items */
    public static function fromList(array $items): array
    {
        return array_map(fn(array $d) => self::fromArray($d), $items);
    }
}
