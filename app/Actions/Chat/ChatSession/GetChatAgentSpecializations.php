<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAgentSpecializationEnum;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

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
