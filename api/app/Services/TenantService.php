<?php

declare(strict_types=1);

namespace WhatstheUp\Services;

use WhatstheUp\Support\HttpException;

final class TenantService
{
    public function businessId(array $identity): string
    {
        $businessId = $identity['business']['id'] ?? null;
        if (!is_string($businessId) || $businessId === '') {
            throw new HttpException(403, 'No active business workspace is available.', 'workspace_unavailable');
        }
        return $businessId;
    }

    public function assertOwns(array $identity, string $recordBusinessId): void
    {
        if (!hash_equals($this->businessId($identity), $recordBusinessId)) {
            // Deliberately use 404 so callers cannot enumerate another tenant's records.
            throw new HttpException(404, 'Resource not found.', 'not_found');
        }
    }
}
