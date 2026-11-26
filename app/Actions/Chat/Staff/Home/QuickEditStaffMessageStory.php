<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Home;

use App\Actions\Helpers\AI\AskJev;
use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\StaffMessage;
use App\Models\Chat\StaffMessageStory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Verba Home within seconds: Jev (a fraction of a cent) decides whether a new Verb is news, and only then Haiku writes
 * the headline. The story is marked quick; the routine editor every 30 minutes rewrites it or removes it.
 */
class QuickEditStaffMessageStory
{
    use AsAction;
    use WithAIGateway;
    use WithAICreditErrorHandler;

    public const string MODEL = 'anthropic/claude-haiku-5.5';

    public const float NEWS_CONFIDENCE = 0.7;

    public const int MIN_CHARACTERS = 25;

    public string $jobQueue = 'default';

    public function handle(StaffMessage|SlackMessage $message): ?StaffMessageStory
    {
        if (!self::isCandidate($message) || $this->hasStory($message)) {
            return null;
        }

        $state = $this->state($message);

        $probability = AskJev::make()->noul(
            $state,
            'A colleague posted this message in a company team channel. Is it news worth showing to everyone in that channel on the company news feed?',
            'A new product or sample shown, a celebration (birthday, welcome, farewell, anniversary, baby, team photo), an announcement, a decision, a big order or sale, an operations problem or win.',
            'Small talk, a question to one person, thanks, a routine status line, a link without context, or anything only one or two people care about.'
        );

        if ($probability === null || $probability < self::NEWS_CONFIDENCE) {
            return null;
        }

        $story = $this->write($state);

        return $story ? StaffMessageStory::storeFor($message, $story, StaffMessageStory::SOURCE_QUICK) : null;
    }

    public static function isCandidate(StaffMessage|SlackMessage $message): bool
    {
        if ($message instanceof StaffMessage) {
            $conversation = $message->conversation;

            return $conversation->type === 'group' && $conversation->context_type === null && filled($conversation->name)
                && $message->parent_id === null && $message->staff_call_id === null
                && ($message->media_id || mb_strlen($message->body) >= self::MIN_CHARACTERS);
        }

        return !$message->trashed()
            && SlackChannel::query()->kept()->whereKey($message->slack_channel_id)->exists()
            && ($message->thread_ts === null || $message->thread_ts === $message->ts)
            && in_array($message->subtype, [null, 'file_share'], true)
            && (self::slackHasImage($message) || mb_strlen((string) $message->text) >= self::MIN_CHARACTERS);
    }

    public static function slackHasImage(SlackMessage $message): bool
    {
        return collect($message->data['files'] ?? [])->contains(fn ($file) => is_array($file) && str_starts_with((string) ($file['mimetype'] ?? ''), 'image/'));
    }

    private function hasStory(StaffMessage|SlackMessage $message): bool
    {
        return StaffMessageStory::query()
            ->where($message instanceof StaffMessage ? 'staff_message_id' : 'slack_message_id', $message->id)
            ->exists();
    }

    /**
     * @return array{where: string, author: string, photo: bool, text: string}
     */
    private function state(StaffMessage|SlackMessage $message): array
    {
        return $message instanceof StaffMessage
            ? ['where' => (string) $message->conversation->name, 'author' => $message->user->chatName(), 'photo' => (bool) $message->media_id, 'text' => mb_substr($message->body, 0, 600)]
            : ['where' => '#'.$message->channel->name, 'author' => $message->user?->chatName() ?? 'someone', 'photo' => self::slackHasImage($message), 'text' => mb_substr((string) $message->text, 0, 600)];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{category: string, importance: int, headline: string, blurb: string}|null
     */
    private function write(array $state): ?array
    {
        $response = $this->aiRequest()
            ->timeout(30)
            ->post('chat/completions', [
                'model'           => $this->aiModel(self::MODEL),
                'messages'        => [['role' => 'user', 'content' => 'Write a news feed item for colleagues about this message from a company team channel. Short headline (max 10 words, no emoji, no clickbait), one or two plain sentences saying what happened and who, in English, never adding facts that are not in the message. Importance 1 (nice to know) to 5 (everyone must see).'
                    ."\n\n".json_encode($state, JSON_UNESCAPED_UNICODE)]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'verba_story', 'strict' => true, 'schema' => [
                    'type'                 => 'object',
                    'properties'           => [
                        'category'   => ['type' => 'string', 'enum' => StaffMessageStory::CATEGORIES],
                        'importance' => ['type' => 'integer'],
                        'headline'   => ['type' => 'string'],
                        'blurb'      => ['type' => 'string'],
                    ],
                    'required'             => ['category', 'importance', 'headline', 'blurb'],
                    'additionalProperties' => false,
                ]]],
            ]);

        $this->guardAICreditResponse($response, 'grp');
        if (!$response->successful()) {
            return null;
        }

        $story = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim((string) $response->json('choices.0.message.content')))), true);

        return is_array($story) && is_string($story['headline'] ?? null) && is_string($story['blurb'] ?? null) && is_string($story['category'] ?? null)
            ? ['category' => $story['category'], 'importance' => (int) ($story['importance'] ?? 2), 'headline' => $story['headline'], 'blurb' => $story['blurb']]
            : null;
    }
}
