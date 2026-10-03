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
    public function updateContact(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->updateContact($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''), $request->json())]; }
    public function deleteContact(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->deleteContact($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
    public function contactGroups(Request $request): array { return ['data' => $this->operations->contactGroups($request->attributes['identity']['business']['id'])]; }
    public function createContactGroup(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->createContactGroup($identity['business']['id'], $identity['id'], $request->json())]; }
    public function updateContactGroup(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->updateContactGroup($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''), $request->json())]; }
    public function deleteContactGroup(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->deleteContactGroup($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
    public function templates(Request $request): array { return ['data' => $this->operations->templates($request->attributes['identity']['business']['id'])]; }
    public function createTemplate(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->createTemplate($identity['business']['id'], $identity['id'], $request->json())]; }
    public function updateTemplate(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->updateTemplate($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''), $request->json())]; }
    public function deleteTemplate(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->deleteTemplate($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
    public function submitTemplate(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->submitTemplate($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
    public function campaigns(Request $request): array { return ['data' => $this->operations->campaigns($request->attributes['identity']['business']['id'])]; }
    public function createCampaign(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->createCampaign($identity['business']['id'], $identity['id'], $request->json())]; }
    public function updateCampaign(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->updateCampaign($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''), $request->json())]; }
    public function deleteCampaign(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->deleteCampaign($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
    public function launchCampaign(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->launchCampaign($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
    public function campaignRecipients(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->campaignRecipients($identity['business']['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
    public function settings(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->settings($identity['business']['id'], $identity['id'])]; }
    public function updateSettings(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->updateSettings($identity['business']['id'], $identity['id'], $request->json())]; }
    public function inviteTeamMember(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->inviteTeamMember($identity['business']['id'], $identity['id'], $request->json())]; }
    public function removeTeamMember(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->removeTeamMember($identity['business']['id'], $identity['id'], (string) ($request->attributes['route']['id'] ?? ''))]; }
    public function changePassword(Request $request): array { $identity = $request->attributes['identity']; return ['data' => $this->operations->changePassword($identity['id'], $request->json())]; }
    public function reports(Request $request): array { return ['data' => $this->operations->reports($request->attributes['identity']['business']['id'], $request->query)]; }
}
