<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\ChatSession\Concerns\WithChatSlackSettings;
use App\Helpers\SlackNotification;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShareChatSessionToSlack
{
    use AsAction;
    use WithChatSlackSettings;

    /**
     * @param  array<int, array{type: string, id: string, name: string}>  $destinations
     *
     * @return array{succeeded: array<int, string>, failed: array<int, array{destination: string, error: string}>}
     */
    public function handle(ChatSession $chatSession, string $token, array $destinations): array
    {
        $chatSession->loadMissing(['webUser.customer', 'shop', 'assignments.chatAgent.user']);

        $contactName = $chatSession->webUser?->customer?->contact_name
            ?? $chatSession->webUser?->username
            ?? $chatSession->guest_identifier
            ?? 'Guest';

        $ulid      = $chatSession->ulid;
        $status    = ucfirst($chatSession->status->value);
        $shopName  = $chatSession->shop?->name ?? '-';
        $agent     = $chatSession->assignments->sortByDesc('assigned_at')->first()?->chatAgent?->user?->contact_name ?? '-';
        $startedAt = $chatSession->created_at?->format('d M Y H:i') ?? '-';

        $orgSlug = $chatSession->shop?->organisation?->slug
            ?? optional(request()->route())->parameter('organisation');

        $url = $orgSlug
            ? route('grp.org.chat.conversations.detail', [
                'organisation' => $orgSlug,
                'chatSession'  => $chatSession->id,
            ])
            : null;

        $message = (new SlackMessage())
            ->headerBlock(":speech_balloon: Chat Session: $ulid")
            ->sectionBlock(function (SectionBlock $block) use ($ulid, $contactName, $status, $shopName, $agent, $startedAt) {
                $block->field("*Contact:*\n$contactName")->markdown();
                $block->field("*Status:*\n$status")->markdown();
                $block->field("*Shop:*\n$shopName")->markdown();
                $block->field("*Agent:*\n$agent")->markdown();
                $block->field("*Started:*\n$startedAt")->markdown();
                $block->field("*Session ID:*\n`$ulid`")->markdown();
            });

        if ($url) {
            $message->dividerBlock()
                ->actionsBlock(function (ActionsBlock $block) use ($url) {
                    $block->button('View Conversation')->primary()->url($url);
                });
        }

        $message->dividerBlock()
            ->contextBlock(function (ContextBlock $block) {
                $block->text('Shared via Aiku CRM · ' . now()->format('d M Y H:i'));
            });

        config(['services.slack.notifications.bot_user_oauth_token' => $token]);

        $succeeded = [];
        $failed    = [];

        foreach ($destinations as $destination) {
            try {
                (new AnonymousNotifiable())
                    ->route('slack', $destination['id'])
                    ->notify(new SlackNotification($message));

                $succeeded[] = $destination['name'];
            } catch (\Exception $e) {
                $failed[] = [
                    'destination' => $destination['name'],
                    'error'       => $e->getMessage(),
                ];
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    public function rules(): array
    {
        return [
            'destination_keys'   => ['sometimes', 'array'],
            'destination_keys.*' => ['string'],
        ];
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $chatSession->loadMissing('shop');

        $slack = $this->shopSlackSettings($chatSession->shop);

        if (blank($slack['token'])) {
            return response()->json([
                'success'        => false,
                'not_configured' => true,
                'message'        => __('Slack is not configured for this shop.'),
            ], 503);
        }

        if (empty($slack['destinations'])) {
            return response()->json([
                'success'        => false,
                'not_configured' => true,
                'message'        => __('No Slack channels or people configured.'),
            ], 503);
        }

        $validated       = $request->validated();
        $destinationKeys = $validated['destination_keys'] ?? null;

        $destinations = collect($slack['destinations'])
            ->when(
                $destinationKeys !== null,
                fn ($collection) => $collection->filter(
                    fn (array $destination) => in_array($destination['type'] . ':' . $destination['id'], $destinationKeys, true)
                )
            )
            ->values()
            ->all();

        if (empty($destinations)) {
            return response()->json([
                'success' => false,
                'message' => __('No Slack destination selected.'),
            ], 422);
        }

        $result = $this->handle($chatSession, $slack['token'], $destinations);

        $succeeded = $result['succeeded'];
        $failed    = $result['failed'];

        if (empty($succeeded) && !empty($failed)) {
            $failedDetails = collect($failed)->map(fn ($f) => "{$f['destination']}: {$f['error']}")->join(', ');

            return response()->json([
                'success' => false,
                'message' => __('Failed to share to all destinations. :details', ['details' => $failedDetails]),
                'failed'  => $failed,
            ], 500);
        }

        if (!empty($failed)) {
            $failedDestinations = collect($failed)->pluck('destination')->join(', ');

            return response()->json([
                'success'   => true,
                'partial'   => true,
                'message'   => __('Shared to :succeeded. Failed: :failed.', [
                    'succeeded' => collect($succeeded)->join(', '),
                    'failed'    => $failedDestinations,
                ]),
                'succeeded' => $succeeded,
                'failed'    => $failed,
            ]);
        }

        return response()->json([
            'success'   => true,
            'message'   => count($succeeded) > 1
                ? __('Shared to :destinations.', ['destinations' => collect($succeeded)->join(', ')])
                : __('Conversation shared to Slack successfully.'),
            'succeeded' => $succeeded,
        ]);
    }
}
