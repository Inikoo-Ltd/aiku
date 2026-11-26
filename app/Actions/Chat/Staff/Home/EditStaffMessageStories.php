<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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

    public const float MAYBE_NEWS = 0.3;

    public const int JEV_CHUNK = 25;

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

        $quickIds = StaffMessageStory::query()
            ->where('source', StaffMessageStory::SOURCE_QUICK)
            ->where(fn ($query) => $query->whereIn('staff_message_id', $staff->pluck('id'))->orWhereIn('slack_message_id', $slack->pluck('id')))
            ->get(['staff_message_id', 'slack_message_id'])
            ->map(fn (StaffMessageStory $story) => $story->staff_message_id ? 's'.$story->staff_message_id : 'k'.$story->slack_message_id)
            ->all();

        $lines = $this->maybeNews($lines, $quickIds);

        $written = 0;
        if ($lines->isNotEmpty()) {
            $staffById = $staff->keyBy('id');
            $slackById = $slack->keyBy('id');
            $kept      = [];

            foreach ($this->askEditor($lines) as $story) {
                $message = str_starts_with($story['id'], 's') ? $staffById->get((int) substr($story['id'], 1)) : $slackById->get((int) substr($story['id'], 1));
                if (!$message) {
                    continue;
                }

                StaffMessageStory::storeFor($message, $story, StaffMessageStory::SOURCE_EDITOR);
                $kept[] = $story['id'];
                $written++;
            }

            $dropped = array_diff($quickIds, $kept);
            StaffMessageStory::query()->where('source', StaffMessageStory::SOURCE_QUICK)->where(fn ($query) => $query
                ->whereIn('staff_message_id', collect($dropped)->filter(fn ($id) => $id[0] === 's')->map(fn ($id) => (int) substr($id, 1)))
                ->orWhereIn('slack_message_id', collect($dropped)->filter(fn ($id) => $id[0] === 'k')->map(fn ($id) => (int) substr($id, 1))))
                ->delete();
        }

        Cache::forever($cursorKey, [
            'staff' => $staff->max('id') ?? $cursor['staff'] ?? StaffMessage::max('id'),
            'slack' => $slack->max('id') ?? $cursor['slack'] ?? SlackMessage::max('id'),
        ]);

        return $written;
    }

    /**
     * Jev scores every message in a few cheap calls; only the ones that may be news, and those with a quick story to
     * review, go on to the editor model. Without an answer from Jev everything goes on.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  list<string>  $quickIds
     * @return Collection<int, array<string, mixed>>
     */
    private function maybeNews(Collection $lines, array $quickIds): Collection
    {
        return $lines->chunk(self::JEV_CHUNK)->flatMap(function (Collection $chunk) use ($quickIds) {
            $answers = AskJev::make()->handle(
                ['messages' => $chunk->values()->all()],
                $chunk->mapWithKeys(fn ($line) => [$line['id'] => [
                    'type'         => 'noul',
                    'instructions' => 'Colleagues posted these messages in company team channels. Could message '.$line['id'].' be news worth showing to everyone in its channel on the company news feed?',
                    'criteria'     => [
                        'true'  => 'A new product or sample, a celebration, an announcement, a decision, a big order or sale, an operations problem or win.',
                        'false' => 'Small talk, a question to one person, thanks, a routine status line, a link without context.',
                    ],
                ]])->all()
            );

            return $chunk->filter(fn ($line) => in_array($line['id'], $quickIds, true)
                || $answers === null
                || (float) ($answers[$line['id']]['noul'] ?? 1) >= self::MAYBE_NEWS);
        })->values();
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
            ."\nSome of these already have a quick story written seconds after posting; return a story for them only if they deserve one, otherwise leave them out and the quick story is removed."
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
