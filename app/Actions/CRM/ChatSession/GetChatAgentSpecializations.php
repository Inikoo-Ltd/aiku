<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatAgentSpecializationEnum;

class GetChatAgentSpecializations
{
    use AsAction;

    public function handle(): array
    {
        $specializations = [];
        $labels = ChatAgentSpecializationEnum::cases()[0]->labels() ?? [];

        foreach (ChatAgentSpecializationEnum::cases() as $case) {
            $specializations[] = [
                'value' => $case->value,
                'label' => $labels[$case->value] ?? ucfirst($case->value),
            ];
        }

        return $specializations;
    }

    public function asController(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->handle(),
        ]);
    }
}
