<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Home;

use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\StaffMessage;
use App\Models\Chat\StaffMessageStory;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * The AI editor of Verba Home: reads the Verbs written since its last run in Verbrations, group chats and public
 * Slack channels (never DMs or task threads) and writes a story for the few worth telling everyone in them.
 */
class EditStaffMessageStories
{
    use AsAction;
    use WithAIGateway;
    use WithAICreditErrorHandler;

    public const string MODEL = 'anthropic/claude-sonnet-5.5';

    public const int BATCH = 150;

    public const int MIN_CHARACTERS = 25;

    public const int FIRST_RUN_HOURS = 24;

    public string $commandSignature = 'chat:edit-stories';

    /**
     * @return int stories written
     */
    public function handle(Group $group): int
    {
        $cursorKey = 'staff-stories-cursor:'.$group->id;
        $cursor    = Cache::get($cursorKey, ['staff' => null, 'slack' => null]);

        $staff = $this->staffCandidates($group, $cursor['staff']);
        $slack = $this->slackCandidates($group, $cursor['slack']);

        $lines = $staff->map(fn (StaffMessage $message) => [
            'id'      => 's'.$message->id,
            'where'   => $message->conversation->name,
            'author'  => $message->user->chatName(),
            'photo'   => (bool) $message->media_id,
            'text'    => mb_substr($message->body, 0, 600),
        ])->concat($slack->map(fn (SlackMessage $message) => [
            'id'      => 'k'.$message->id,
            'where'   => '#'.$message->channel->name,
            'author'  => $message->user?->chatName() ?? 'someone',
            'photo'   => $this->slackHasImage($message),
            'text'    => mb_substr((string) $message->text, 0, 600),
        ]))->values();

        $written = 0;
        if ($lines->isNotEmpty()) {
            $staffById = $staff->keyBy('id');
            $slackById = $slack->keyBy('id');

            foreach ($this->askEditor($lines) as $story) {
                $message = str_starts_with($story['id'], 's') ? $staffById->get((int) substr($story['id'], 1)) : $slackById->get((int) substr($story['id'], 1));
                if (!$message) {
                    continue;
                }

                $isStaff = $message instanceof StaffMessage;
                StaffMessageStory::updateOrCreate(
                    $isStaff ? ['staff_message_id' => $message->id] : ['slack_message_id' => $message->id],
                    [
                        'group_id'              => $group->id,
                        'staff_conversation_id' => $isStaff ? $message->staff_conversation_id : null,
                        'slack_channel_id'      => $isStaff ? null : $message->slack_channel_id,
                        'author_user_id'        => $message->user_id,
                        'category'              => in_array($story['category'], StaffMessageStory::CATEGORIES, true) ? $story['category'] : 'other',
                        'importance'            => max(1, min(5, (int) $story['importance'])),
                        'headline'              => mb_substr(trim($story['headline']), 0, 120),
                        'blurb'                 => mb_substr(trim($story['blurb']), 0, 400),
                        'happened_at'           => $isStaff ? $message->created_at : $message->sent_at,
                    ]
                );
                $written++;
            }
        }

        Cache::forever($cursorKey, [
            'staff' => $staff->max('id') ?? $cursor['staff'] ?? StaffMessage::max('id'),
            'slack' => $slack->max('id') ?? $cursor['slack'] ?? SlackMessage::max('id'),
        ]);

        return $written;
    }

    /**
     * @return Collection<int, StaffMessage>
     */
    private function staffCandidates(Group $group, ?int $afterId): Collection
    {
        return StaffMessage::query()
            ->with(['conversation', 'user'])
            ->whereHas('conversation', fn ($query) => $query->where('group_id', $group->id)->where('type', 'group')->whereNull('context_type')->whereNotNull('name'))
            ->whereNull('parent_id')
            ->whereNull('staff_call_id')
            ->when($afterId, fn ($query) => $query->where('id', '>', $afterId), fn ($query) => $query->where('created_at', '>=', now()->subHours(self::FIRST_RUN_HOURS)))
            ->where(fn ($query) => $query->whereNotNull('media_id')->orWhereRaw('char_length(body) >= ?', [self::MIN_CHARACTERS]))
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();
    }

    /**
     * @return Collection<int, SlackMessage>
     */
    private function slackCandidates(Group $group, ?int $afterId): Collection
    {
        return SlackMessage::query()
            ->with(['channel', 'user'])
            ->whereIn('slack_channel_id', SlackChannel::query()->kept()->where('group_id', $group->id)->select('id'))
            ->where(fn ($query) => $query->whereNull('thread_ts')->orWhereColumn('thread_ts', 'ts'))
            ->where(fn ($query) => $query->whereNull('subtype')->orWhere('subtype', 'file_share'))
            ->when($afterId, fn ($query) => $query->where('id', '>', $afterId), fn ($query) => $query->where('sent_at', '>=', now()->subHours(self::FIRST_RUN_HOURS)))
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get()
            ->filter(fn (SlackMessage $message) => $this->slackHasImage($message) || mb_strlen((string) $message->text) >= self::MIN_CHARACTERS)
            ->values();
    }

    private function slackHasImage(SlackMessage $message): bool
    {
        return collect($message->data['files'] ?? [])->contains(fn ($file) => is_array($file) && str_starts_with((string) ($file['mimetype'] ?? ''), 'image/'));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return list<array{id: string, category: string, importance: int, headline: string, blurb: string}>
     */
    private function askEditor(Collection $lines): array
    {
        $prompt = "You are the editor-in-chief of the internal news feed of a wholesale company (gifts, crystals, incense, aromatherapy; warehouses in the UK, Slovakia, Spain, Indonesia)."
            ."\nBelow are messages staff wrote in team channels since the last edition. Pick only those worth a story for colleagues: a new product or sample shown (especially with a photo), a celebration (birthday, welcome, farewell, anniversary, baby, team photo), an announcement, a decision, a big order or sale, an operations problem or win."
            ."\nSkip small talk, questions to one person, thanks, routine status lines, links without context. Most messages are not stories; an empty list is fine."
            ."\nFor each story: id as given, category (".implode(', ', StaffMessageStory::CATEGORIES)."), importance 1 (nice to know) to 5 (everyone must see), a short headline (max 10 words, no clickbait, no emoji), and a blurb of one or two plain sentences saying what happened and who. Write in English. Never invent facts that are not in the message."
            ."\n\nMessages (JSON lines):\n".$lines->map(fn ($line) => json_encode($line, JSON_UNESCAPED_UNICODE))->implode("\n");

        $response = $this->aiRequest()
            ->timeout(120)
            ->post('chat/completions', [
                'model'           => $this->aiModel(self::MODEL),
                'messages'        => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'verba_stories', 'strict' => true, 'schema' => [
                    'type'                 => 'object',
                    'properties'           => ['stories' => ['type' => 'array', 'items' => [
                        'type'                 => 'object',
                        'properties'           => [
                            'id'         => ['type' => 'string'],
                            'category'   => ['type' => 'string', 'enum' => StaffMessageStory::CATEGORIES],
                            'importance' => ['type' => 'integer'],
                            'headline'   => ['type' => 'string'],
                            'blurb'      => ['type' => 'string'],
                        ],
                        'required'             => ['id', 'category', 'importance', 'headline', 'blurb'],
                        'additionalProperties' => false,
                    ]]],
                    'required'             => ['stories'],
                    'additionalProperties' => false,
                ]]],
            ]);

        $this->guardAICreditResponse($response, 'grp');
        $response->throw();

        $answer = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim((string) $response->json('choices.0.message.content')))), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($answer['stories'] ?? null)) {
            throw new RuntimeException('The Verba editor returned no stories list');
        }

        return array_values(array_filter($answer['stories'], fn ($story) => is_array($story) && is_string($story['id'] ?? null) && is_string($story['headline'] ?? null) && is_string($story['blurb'] ?? null)));
    }

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info($group->slug.': '.$this->handle($group).' stories');
        }

        return 0;
    }
}
