<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\ChatSession\Concerns\WithChatSlackSettings;
use App\Helpers\SlackNotification;
use App\Models\Chat\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ActionsBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ForwardChatMessageToSlack
{
    use AsAction;
    use WithChatSlackSettings;

    /**
     * @param  array<int, string>  $destinationKeys  composite "type:id" keys, must match configured destinations
     *
     * @return array{succeeded: array<int, string>, failed: array<int, array{destination: string, error: string}>}
     */
    public function handle(ChatMessage $chatMessage, array $destinationKeys): array
    {
        $chatSession = $chatMessage->chatSession;
        $slack       = $this->shopSlackSettings($chatSession?->shop);

        $destinations = collect($slack['destinations'])
            ->filter(fn (array $destination) => in_array($destination['type'] . ':' . $destination['id'], $destinationKeys, true))
            ->values();

        $contactName = $chatSession?->webUser?->customer?->contact_name
            ?? $chatSession?->webUser?->username
            ?? $chatSession?->guest_identifier
            ?? 'Guest';

        $orgSlug = $chatSession?->shop?->organisation?->slug
            ?? optional(request()->route())->parameter('organisation');

        $url = ($orgSlug && $chatSession)
            ? route('grp.org.chat.conversations.detail', [
                'organisation' => $orgSlug,
                'chatSession'  => $chatSession->id,
            ])
            : null;

        $imageUrl = null;
        if ($chatMessage->isImage() && $chatMessage->attachment) {
            $imageUrl = $chatMessage->attachment->getUrl();
            if (!str_starts_with($imageUrl, 'http')) {
                $imageUrl = url($imageUrl);
            }
        }

        $message = (new SlackMessage())
            ->headerBlock(':outbox_tray: Forwarded message');

        if (filled($chatMessage->message_text)) {
            $message->sectionBlock(function (SectionBlock $block) use ($chatMessage) {
                $block->text($chatMessage->message_text)->markdown();
            });
        } elseif ($imageUrl) {
            $message->sectionBlock(function (SectionBlock $block) {
                $block->text(':frame_with_picture: Image message')->markdown();
            });
        }

        $message->contextBlock(function (ContextBlock $block) use ($contactName, $chatMessage) {
            $block->text("From *{$contactName}* · " . $chatMessage->created_at->format('d M Y H:i'))->markdown();
        });

        if ($url || $imageUrl) {
            $message->dividerBlock()
                ->actionsBlock(function (ActionsBlock $block) use ($url, $imageUrl) {
                    if ($imageUrl) {
                        $block->button('View Image')->url($imageUrl);
                    }
                    if ($url) {
                        $block->button('View Conversation')->primary()->url($url);
                    }
                });
        }

        config(['services.slack.notifications.bot_user_oauth_token' => $slack['token']]);

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
            'destination_keys'   => ['required', 'array', 'min:1'],
            'destination_keys.*' => ['required', 'string'],
        ];
    }

    public function asController(string $organisation, ChatMessage $chatMessage, ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $chatMessage->loadMissing(['chatSession.webUser.customer', 'chatSession.shop.organisation', 'attachment']);

        $slack = $this->shopSlackSettings($chatMessage->chatSession?->shop);

        if (blank($slack['token'])) {
            return response()->json([
                'success'        => false,
                'not_configured' => true,
                'message'        => __('Slack is not configured for this shop.'),
            ], 503);
        }

        $result = $this->handle($chatMessage, $validated['destination_keys']);

        if (empty($result['succeeded'])) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to forward the message to Slack.'),
                'failed'  => $result['failed'],
            ], 500);
        }

        return response()->json([
            'success'   => true,
            'partial'   => !empty($result['failed']),
            'message'   => __('Forwarded to :destinations.', ['destinations' => collect($result['succeeded'])->join(', ')]),
            'succeeded' => $result['succeeded'],
            'failed'    => $result['failed'],
        ]);
    }
}
