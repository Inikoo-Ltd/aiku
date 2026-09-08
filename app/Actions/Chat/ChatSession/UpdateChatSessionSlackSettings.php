<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\ChatSession\Concerns\WithChatSlackSettings;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateChatSessionSlackSettings
{
    use AsAction;
    use WithChatSlackSettings;

    /**
     * @param  array<string, mixed>  $modelData
     *
     * @return array{has_token: bool, destinations: array<int, array{type: string, id: string, name: string}>}
     */
    public function handle(ChatSession $chatSession, array $modelData): array
    {
        $chatSession->loadMissing('shop');
        $shop = $chatSession->shop;

        $settings      = $shop->settings ?? [];
        $existingToken = Arr::get($settings, 'chat.slack_token');

        $token = filled(Arr::get($modelData, 'token'))
            ? Arr::get($modelData, 'token')
            : $existingToken;

        $destinations = Arr::exists($modelData, 'destinations')
            ? collect(Arr::get($modelData, 'destinations', []))
                ->map(fn (array $destination) => [
                    'type' => $destination['type'],
                    'id'   => trim((string) $destination['id']),
                    'name' => trim((string) $destination['name']),
                ])
                ->values()
                ->all()
            : (array) Arr::get($settings, 'chat.slack_destinations', []);

        data_set($settings, 'chat.slack_token', $token);
        data_set($settings, 'chat.slack_destinations', $destinations);

        $shop->settings = $settings;
        $shop->save();

        return [
            'has_token'    => filled($token),
            'destinations' => $destinations,
        ];
    }

    public function rules(): array
    {
        return [
            'token'               => ['sometimes', 'nullable', 'string'],
            'destinations'        => ['sometimes', 'array'],
            'destinations.*.type' => ['required', 'in:channel,user'],
            'destinations.*.id'   => ['required', 'string', 'max:255'],
            'destinations.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $data = $this->handle($chatSession, $validated);

        return response()->json([
            'success' => true,
            'message' => __('Slack settings saved'),
            'data'    => $data,
        ]);
    }
}
