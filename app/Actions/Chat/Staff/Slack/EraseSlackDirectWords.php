<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Notifications\SlackJoinRequestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A person's own words in Slack DMs and group DMs, removed for good when they ask (INI-568): the messages, their versions,
 * reactions, stories and file copies. A group administrator approves the request; only a named legal reason (a dispute or
 * claim, an investigation, a legal duty) holds it instead, and then the messages stay hidden until the hold ends, at most
 * six years. Public and private channels are never touched, nor anyone else's words. Erasing also ends the person's private
 * opt-in, or Verba would bring the same words back from Slack. Logs counts only.
 */
class EraseSlackDirectWords
{
    use AsAction;

    public const int BATCH = 500;

    public const int MAX_HOLD_YEARS = 6;

    public const array HOLD_REASONS = ['dispute', 'investigation', 'legal_duty'];

    public string $commandSignature = 'slack:erase-direct-words
        {person? : aiku username or Slack user id of the person who asked}
        {--approved-by= : username of the group administrator who approved the request}
        {--ticket= : help desk ticket of the request}
        {--hold= : keep the messages hidden instead, for a legal reason: dispute, investigation or legal_duty}
        {--hold-until= : date the hold ends, at most six years from now (default)}
        {--erase= : id of a held or unfinished request to erase now}
        {--due : erase every held request whose hold has ended}';

    public string $commandDescription = "Remove for good a person's own words in Slack DMs and group DMs, once a group administrator approved it";

    public function request(string $slackUserId, User $approvedBy, ?string $ticket = null, ?string $holdReason = null, ?Carbon $holdUntil = null): int
    {
        if (!$approvedBy->status || $approvedBy->roles->where('name', RolesEnum::GROUP_ADMIN->value)->isEmpty()) {
            throw ValidationException::withMessages(['approved_by' => 'Only a group administrator approves the removal of DM words']);
        }
        if ($holdReason !== null && !in_array($holdReason, self::HOLD_REASONS, true)) {
            throw ValidationException::withMessages(['hold' => 'A hold needs a legal reason: '.implode(', ', self::HOLD_REASONS)]);
        }
        $latest = now()->addYears(self::MAX_HOLD_YEARS);
        if ($holdUntil && ($holdUntil->isPast() || $holdUntil->gt($latest))) {
            throw ValidationException::withMessages(['hold_until' => 'A hold ends within six years']);
        }

        $requestId = DB::table('slack_erasure_requests')->insertGetId([
            'group_id'            => $approvedBy->group_id,
            'user_id'             => SlackUser::where('slack_id', $slackUserId)->value('user_id'),
            'slack_user_id'       => $slackUserId,
            'approved_by_user_id' => $approvedBy->id,
            'ticket_reference'    => $ticket,
            'status'              => $holdReason ? 'held' : 'erasing',
            'hold_reason'         => $holdReason,
            'held_until'          => $holdReason ? ($holdUntil ?? $latest) : null,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        if (!$holdReason) {
            $this->erase($requestId);

            return $requestId;
        }

        $this->directMessages($slackUserId)->whereNull('deleted_at')->toBase()->update(['deleted_at' => now(), 'updated_at' => now()]);
        $this->tell($requestId, __('Your request to remove your direct messages'), __('Your words in direct messages and group chats are kept hidden for a legal reason, until :date at the latest, and then removed. Nobody sees them in Verba meanwhile.', ['date' => Carbon::parse(DB::table('slack_erasure_requests')->where('id', $requestId)->value('held_until'))->toDateString()]));

        return $requestId;
    }

    /**
     * @return array{messages: int, files: int}
     */
    public function erase(int $requestId): array
    {
        $request = DB::table('slack_erasure_requests')->where('id', $requestId)->first();
        if (!$request || $request->status === 'erased') {
            return ['messages' => 0, 'files' => 0];
        }
        DB::table('slack_erasure_requests')->where('id', $requestId)->update(['status' => 'erasing', 'updated_at' => now()]);

        SlackConnection::where('slack_user_id', $request->slack_user_id)->whereNotNull('private_consented_at')->get()
            ->each(fn (SlackConnection $connection) => $connection->endPrivateConsent('withdrawn'));

        $totals = ['messages' => 0, 'files' => 0];
        while (($ids = $this->directMessages($request->slack_user_id)->limit(self::BATCH)->pluck('id')->all()) !== []) {
            DB::transaction(function () use ($ids, &$totals) {
                $totals['files'] += self::forgetFiles($ids);
                $totals['messages'] += SlackMessage::withTrashed()->whereIn('id', $ids)->toBase()->delete();
            });
        }

        DB::table('slack_erasure_requests')->where('id', $requestId)->update([
            'status'          => 'erased',
            'messages_erased' => DB::raw('messages_erased + '.$totals['messages']),
            'files_erased'    => DB::raw('files_erased + '.$totals['files']),
            'erased_at'       => now(),
            'updated_at'      => now(),
        ]);
        Log::info('Slack DM words erased', ['request_id' => $requestId, 'messages' => $totals['messages'], 'files' => $totals['files']]);
        $this->tell($requestId, __('Your request to remove your direct messages'), __('Your words in direct messages and group chats have been removed from Verba. Messages in channels stay, as they belong to everyone in them.'));

        return $totals;
    }

    /**
     * Held requests whose hold has ended, erased; run nightly.
     */
    public function due(): int
    {
        $ids = DB::table('slack_erasure_requests')->where('status', 'held')->where('held_until', '<=', now())->pluck('id');
        $ids->each(fn (int $id) => $this->erase($id));

        return $ids->count();
    }

    /**
     * The file copies first seen in these messages, deleted; a copy another kept message still lists (one private copy serves every
     * private conversation sharing the Slack file) moves to that message instead.
     *
     * @param  list<int>|\Illuminate\Database\Eloquent\Builder  $messageIds
     */
    public static function forgetFiles(array|\Illuminate\Database\Eloquent\Builder $messageIds): int
    {
        $ids = is_array($messageIds) ? $messageIds : $messageIds->pluck('id')->all();

        return SlackFile::whereIn('slack_message_id', $ids)->get()->sum(function (SlackFile $file) use ($ids) {
            $stillListed = SlackMessage::withTrashed()->whereNotIn('id', $ids)
                ->whereIn('slack_channel_id', SlackChannel::where('is_public', !$file->is_private)->select('id'))
                ->whereRaw("data->'files' @> ?::jsonb", [json_encode([['id' => $file->slack_id]])])
                ->value('id');
            if ($stillListed) {
                $file->update(['slack_message_id' => $stillListed]);

                return 0;
            }
            $file->deleteStoredCopy();
            $file->delete();

            return 1;
        });
    }

    public static function isHeld(string $slackUserId): bool
    {
        return DB::table('slack_erasure_requests')->where('slack_user_id', $slackUserId)->where('status', 'held')->exists();
    }

    private function directMessages(string $slackUserId): \Illuminate\Database\Eloquent\Builder
    {
        return SlackMessage::withTrashed()
            ->whereIn('slack_channel_id', SlackChannel::whereNotNull('direct_type')->select('id'))
            ->where('slack_user_id', $slackUserId);
    }

    private function tell(int $requestId, string $title, string $body): void
    {
        $user = User::find(DB::table('slack_erasure_requests')->where('id', $requestId)->value('user_id'));
        $user?->notify(new SlackJoinRequestNotification($title, $body, route('grp.chat.staff.index'), 'slack_erasure'));
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        if ($command->option('due')) {
            $command->info('Held requests erased: '.$this->due());

            return 0;
        }
        if ($requestId = $command->option('erase')) {
            $totals = $this->erase((int) $requestId);
            $command->info("Messages erased: {$totals['messages']}, files: {$totals['files']}");

            return 0;
        }

        $person     = (string) $command->argument('person');
        $slackId    = SlackUser::where('slack_id', $person)->value('slack_id') ?? SlackUser::where('user_id', User::where('username', $person)->value('id'))->where('is_bot', false)->value('slack_id');
        $approvedBy = User::where('username', (string) $command->option('approved-by'))->first();
        if (!$slackId || !$approvedBy) {
            $command->error(!$slackId ? "No Slack person $person" : 'Pass --approved-by with the username of the group administrator who approved it');

            return 1;
        }

        try {
            $holdUntil = $command->option('hold-until') ? Carbon::parse($command->option('hold-until'))->endOfDay() : null;
            $requestId = $this->request($slackId, $approvedBy, $command->option('ticket') ?: null, $command->option('hold') ?: null, $holdUntil);
        } catch (ValidationException $exception) {
            $command->error(collect($exception->errors())->flatten()->first());

            return 1;
        }
        $row = DB::table('slack_erasure_requests')->where('id', $requestId)->first();
        $command->info($row->status === 'held' ? "Request $requestId held until $row->held_until: the messages are hidden" : "Request $requestId: messages erased $row->messages_erased, files $row->files_erased");

        return 0;
    }
}
