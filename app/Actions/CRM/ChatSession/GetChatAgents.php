<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatAgent;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatAgents
{
    use AsAction;

    public function handle(): array
    {
        return ChatAgent::with(['user', 'shopAssignments.shop', 'shopAssignments.organisation'])
            ->has('shopAssignments')
            ->get()
            ->map(function (ChatAgent $agent) {

                $isAvailable = $agent->isAvailableForChat();

                if (!$isAvailable) {
                    return null;
                }

                $user = $agent->user;
                $name = $user->contact_name ?? $user->username ?? 'Unknown';

                $shopDetails = $agent->shopAssignments->map(function ($assignment) {
                    $orgCode = $assignment->organisation->code ?? '-';
                    $shopName = $assignment->shop->name ?? '-';
                    return "{$orgCode} | {$shopName}";
                })->implode(', ');

                return [
                    'label'        => $name,
                    'name'         => $name,
                    'agent_id'     => $agent->id,
                    'image'        => $user->imageSources(48, 48),
                    'shop_names'   => $shopDetails,
                    'is_available' => true,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function asController(): JsonResponse
    {
        $agents = $this->handle();

        return response()->json([
            'success' => true,
            'message' => 'Chat agents retrieved successfully',
            'data'    => $agents

        ]);
    }
}
