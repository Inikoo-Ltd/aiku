<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackFile;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Second half of sending files to Slack as the signed-in person: files.completeUploadExternal for files UploadSlackFile
 * already sent, with the typed text as the comment (escaped and with mentions exactly as SendSlackMessage does) and the
 * thread root when replying. No message row is stored here: it arrives as Slack's file_share event, which finds our copies
 * held and is marked sent_from_aiku through the cache marker StoreSlackMessage::handle reads (store() never writes it).
 */
class SendSlackFiles
{
    use AsAction;
    use WithSlackMessageWrite;

    public const int MAX_FILES = 10;

    public const int SENT_MARKER_SECONDS = 600;

    public static function sentMarkerKey(string $fileId): string
    {
        return 'slack-file-sent-from-aiku:'.$fileId;
    }

    public function rules(): array
    {
        return [
            'file_ids'        => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'file_ids.*'      => ['required', 'string', 'regex:/^[A-Za-z0-9]+$/'],
            'text'            => ['sometimes', 'nullable', 'string', 'max:'.SendSlackMessage::MAX_LENGTH],
            'thread_ts'       => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'notify_everyone' => ['sometimes', 'boolean'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /**
     * @param  list<string>  $fileIds
     * @return array{file_ids: list<string>, stand_in: bool}
     */
    public function handle(User $user, SlackChannel $slackChannel, array $fileIds, ?string $text, ?string $threadTs, string $idempotencyKey, bool $notifyEveryone = false): array
    {
        $this->ensureMayWrite($user, $slackChannel);
        if (!SlackChannel::kept()->whereKey($slackChannel->id)->exists()) {
            $this->refuse(__('Files cannot be sent to this channel.'));
        }

        $titles = [];
        foreach (array_values(array_unique($fileIds)) as $id) {
            $name = Cache::get(UploadSlackFile::cacheKey($user, $slackChannel, $id));
            if (!$name) {
                $this->refuse(__('A file was not uploaded or took too long to send. Attach it again.'));
            }
            $titles[] = ['id' => $id, 'title' => (string) (SlackFile::where('slack_id', $id)->value('name') ?: $name)];
        }

        $sender  = SendSlackMessage::make();
        $comment = $sender->withMentions(SendSlackMessage::toSlackText(trim((string) $text)), $user, $slackChannel, $notifyEveryone);
        if (mb_strlen($comment) > SendSlackMessage::MAX_LENGTH) {
            $this->refuse(__('The message is too long for Slack, keep it under :max characters.', ['max' => number_format(SendSlackMessage::MAX_LENGTH)]));
        }
        $rootTs = $threadTs ? $sender->threadRoot($slackChannel, $threadTs) : null;
        $client = $this->clientFor($user);

        $claimKey = 'slack-send-files:'.$user->id.':'.$slackChannel->id.':'.$idempotencyKey;
        if (!Cache::add($claimKey, 'sending', SendSlackMessage::IDEMPOTENCY_SECONDS)) {
            if (Cache::get($claimKey) === 'sending') {
                $this->refuse(__('These files are already being sent.'));
            }

            return ['file_ids' => array_column($titles, 'id'), 'stand_in' => false];
        }

        foreach ($titles as $title) {
            Cache::put(self::sentMarkerKey($title['id']), true, self::SENT_MARKER_SECONDS);
        }
        try {
            $client->call('files.completeUploadExternal', [
                'files'           => $titles,
                'channel_id'      => $slackChannel->slack_id,
                'initial_comment' => $comment !== '' ? $comment : null,
                'thread_ts'       => $rootTs,
            ]);
        } catch (SlackWriteException $exception) {
            if ($exception->slackError === 'unreachable') {
                Cache::put($claimKey, 'sending', SendSlackMessage::UNCERTAIN_SECONDS);
            } else {
                $this->release($claimKey, $titles);
            }
            $this->refuse(SendSlackMessage::errorSentence($exception->slackError));
        } catch (Throwable $exception) {
            $this->release($claimKey, $titles);
            report($exception);
            $this->refuse(__('The files were not sent. Try again.'));
        }

        Cache::put($claimKey, 'sent', SendSlackMessage::IDEMPOTENCY_SECONDS);
        foreach ($titles as $title) {
            Cache::forget(UploadSlackFile::cacheKey($user, $slackChannel, $title['id']));
        }

        return ['file_ids' => array_column($titles, 'id'), 'stand_in' => $client->isStandIn()];
    }

    /**
     * @param  list<array{id: string, title: string}>  $titles
     */
    private function release(string $claimKey, array $titles): void
    {
        Cache::forget($claimKey);
        foreach ($titles as $title) {
            Cache::forget(self::sentMarkerKey($title['id']));
        }
    }

    /**
     * @return array{file_ids: list<string>, stand_in: bool}
     */
    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return $this->handle($request->user(), $slackChannel, $request->validated('file_ids'), $request->validated('text'), $request->validated('thread_ts'), $request->validated('idempotency_key'), (bool) $request->validated('notify_everyone', false));
    }
}
