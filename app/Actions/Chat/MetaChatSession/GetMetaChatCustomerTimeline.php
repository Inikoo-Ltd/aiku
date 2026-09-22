<?php

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\GetChatContactTimeline;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMetaChatCustomerTimeline
{
    use AsAction;

    public function handle(MetaChatSession $metaChatSession): array
    {
        $customer = $metaChatSession->customer;

        if (!$customer) {
            return ['events' => []];
        }

        return GetChatContactTimeline::run($customer, $metaChatSession);
    }

    public function asController(MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        return response()->json($this->handle($metaChatSession));
    }
}
