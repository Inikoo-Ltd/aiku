<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Livechat\ChatAgent;

class GetChatAgents
{
    use AsAction;

    public function handle(): array
    {
        return ChatAgent::with('user')
            ->get()
            ->map(function (ChatAgent $agent) {
                $user = $agent->user;
                $name = $user->contact_name ?? $user->username ?? 'Unknown';

                return [
                    'label' => $name,
                    'name'  => $name,
                    'agent_id'    => $agent->id,
                    'image' => $user->imageSources(48, 48)
                ];
            })
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
