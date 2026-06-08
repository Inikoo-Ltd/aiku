<?php

namespace App\Actions\CRM\ChatSession;

use App\Helpers\SlackNotification;
use App\Models\CRM\Livechat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShareChatSessionToSlack
{
    use AsAction;

    public function handle(ChatSession $chatSession, string $token, array $channels): array
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

        foreach ($channels as $channel) {
            try {
                (new AnonymousNotifiable())
                    ->route('slack', $channel)
                    ->notify(new SlackNotification($message));

                $succeeded[] = $channel;
            } catch (\Exception $e) {
                $failed[] = [
                    'channel' => $channel,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $chatSession->loadMissing('shop');

        $settings = $chatSession->shop?->settings ?? [];
        $token    = Arr::get($settings, 'chat.slack_token') ?? '';
        $channels = array_values(array_filter((array) (Arr::get($settings, 'chat.slack_channels') ?? [])));

        if (empty($token)) {
            return response()->json([
                'success'        => false,
                'not_configured' => true,
                'message'        => __('Slack is not configured for this shop.'),
            ], 503);
        }

        if (empty($channels)) {
            return response()->json([
                'success'        => false,
                'not_configured' => true,
                'message'        => __('No Slack channels configured.'),
            ], 503);
        }

        $result = $this->handle($chatSession, $token, $channels);

        $succeeded = $result['succeeded'];
        $failed    = $result['failed'];

        if (empty($succeeded) && !empty($failed)) {
            $failedDetails = collect($failed)->map(fn ($f) => "{$f['channel']}: {$f['error']}")->join(', ');

            return response()->json([
                'success' => false,
                'message' => __('Failed to share to all channels. :details', ['details' => $failedDetails]),
                'failed'  => $failed,
            ], 500);
        }

        if (!empty($failed)) {
            $failedChannels = collect($failed)->pluck('channel')->join(', ');

            return response()->json([
                'success'   => true,
                'partial'   => true,
                'message'   => __('Shared to :succeeded. Failed: :failed.', [
                    'succeeded' => collect($succeeded)->join(', '),
                    'failed'    => $failedChannels,
                ]),
                'succeeded' => $succeeded,
                'failed'    => $failed,
            ]);
        }

        return response()->json([
            'success'   => true,
            'message'   => count($succeeded) > 1
                ? __('Shared to :channels.', ['channels' => collect($succeeded)->join(', ')])
                : __('Conversation shared to Slack successfully.'),
            'succeeded' => $succeeded,
        ]);
    }
}
