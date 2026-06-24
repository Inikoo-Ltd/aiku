<?php

namespace App\Actions\CRM\ChatSession;

use App\Actions\CRM\Customer\UI\GetCustomerTimeline;
use App\Models\CRM\Livechat\ChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatCustomerTimeline
{
    use AsAction;

    public function handle(ChatSession $chatSession): array
    {
        $customer = $chatSession->webUser?->customer;

        if (!$customer) {
            return ['events' => []];
        }

        return GetCustomerTimeline::run($customer);
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        return response()->json($this->handle($chatSession));
    }
}
