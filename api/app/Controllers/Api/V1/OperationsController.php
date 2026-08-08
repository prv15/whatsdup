<?php

declare(strict_types=1);

namespace WhatstheUp\Controllers\Api\V1;

use WhatstheUp\Services\OperationsService;
use WhatstheUp\Support\Request;

final class OperationsController
{
    public function __construct(private readonly OperationsService $operations)
    {
    }

    public function dashboard(Request $request): array { return ['data' => $this->operations->dashboard($request->attributes['identity']['business']['id'])]; }
    public function contacts(Request $request): array { return ['data' => $this->operations->contacts($request->attributes['identity']['business']['id'])]; }
    public function importContacts(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->importContacts($identity['business']['id'], $identity['id'], $request->json())]; }
    public function templates(Request $request): array { return ['data' => $this->operations->templates($request->attributes['identity']['business']['id'])]; }
    public function createTemplate(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->createTemplate($identity['business']['id'], $identity['id'], $request->json())]; }
    public function campaigns(Request $request): array { return ['data' => $this->operations->campaigns($request->attributes['identity']['business']['id'])]; }
    public function createCampaign(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->createCampaign($identity['business']['id'], $identity['id'], $request->json())]; }
    public function launchCampaign(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->launchCampaign($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
}
