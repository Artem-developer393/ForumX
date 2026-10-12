<?php

namespace App\DTOs\Output;

class RefreshTokenStatsResponseDTO
{
    public function __construct(
        public readonly int $total,
        public readonly int $active,
        public readonly int $used,
        public readonly int $revoked,
        public readonly int $expired,
        public readonly int $ready_for_cleanup
    ) {}

    public static function fromArray(array $stats): self
    {
        return new self(
            total: (int)$stats['total'],
            active: (int)$stats['active'],
            used: (int)$stats['used'],
            revoked: (int)$stats['revoked'],
            expired: (int)$stats['expired'],
            ready_for_cleanup: (int)$stats['ready_for_cleanup']
        );
    }
}
