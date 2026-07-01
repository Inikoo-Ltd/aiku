<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\CRM\Customer\UI\GetCustomerTimeline;
use App\Models\Chat\ChatSession;
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
