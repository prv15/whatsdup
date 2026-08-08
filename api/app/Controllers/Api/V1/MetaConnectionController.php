<?php

declare(strict_types=1);

namespace WhatstheUp\Controllers\Api\V1;

use WhatstheUp\Services\MetaConnectionService;
use WhatstheUp\Support\Request;

final class MetaConnectionController
{
    public function __construct(private readonly MetaConnectionService $meta)
    {
    }

    public function configuration(): array
    {
        return ['data' => $this->meta->configuration()];
    }

    public function status(Request $request): array
    {
        return ['data' => $this->meta->status($request->attributes['identity']['business']['id'])];
    }

    public function complete(Request $request): array
    {
        $identity = $request->attributes['identity'];
        return ['data' => $this->meta->complete($identity['business']['id'], $identity['id'], $request->json())];
    }

    public function syncTemplates(Request $request): array
    {
        $identity = $request->attributes['identity'];
        return ['data' => $this->meta->syncTemplates($identity['business']['id'], $identity['id'])];
    }
}
