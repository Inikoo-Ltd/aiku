<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Chat\Staff\UI\ShowSlackMessageFile;
use App\Enums\CRM\Livechat\SlackFileStateEnum;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The files of a page of Slack messages as the screen shows them, in one slack_files query whatever the page size.
 * A file is reached only through the route of the message it is listed in; nothing of Slack's own links is sent,
 * except the link of a file Slack itself only linked to (mode external).
 */
class GetSlackMessageFiles
{
    use AsObject;

    public const string ROUTE = 'grp.chat.staff.slack.files.show';

    public const string ID_PATTERN = '[A-Za-z0-9]+';

    /**
     * Files of a private channel or DM use only their private copies (INI-441), never a copy found by the same Slack file id in
     * a public channel, and the other way round; they get no preview or thumbnail, and one not copied yet shows as not available.
     *
     * @param  Collection<int, SlackMessage>  $messages
     * @return array<int, list<array<string, mixed>>>
     */
    public function handle(Collection $messages, bool $isPrivateChannel = false): array
    {
        $entries = $messages->mapWithKeys(fn (SlackMessage $message) => [
            $message->id => array_values(array_filter(is_array($message->data['files'] ?? null) ? $message->data['files'] : [], 'is_array')),
        ])->filter();

        $ids = $entries->flatten(1)->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '')->unique()->values()->all();
        $copies = $ids === [] ? collect() : SlackFile::whereIn('slack_id', $ids)
            ->where('is_private', $isPrivateChannel)
            ->get(['id', 'slack_id', 'name', 'mime_type', 'size', 'mode', 'state', 'reason', 'media_id', 'disk', 'path', 'data', 'is_private'])
            ->keyBy('slack_id');

        return $entries->map(fn (array $files, int $messageId) => array_map(
            function (array $file) use ($messageId, $copies, $isPrivateChannel) {
                $copy = is_string($file['id'] ?? null) ? $copies->get($file['id']) : null;

                return $isPrivateChannel && !$copy ? $this->privateRow($file) : $this->row($messageId, $file, $copy, $isPrivateChannel);
            },
            $files
        ))->all();
    }

    /**
     * @param  array<string, mixed>  $file  one entry of the message's data->files
     * @return array<string, mixed>
     */
    private function row(int $messageId, array $file, ?SlackFile $copy, bool $isPrivate = false): array
    {
        $id    = is_string($file['id'] ?? null) && preg_match('/^'.self::ID_PATTERN.'$/', $file['id']) ? $file['id'] : null;
        $name  = $this->stringOrNull($file['name'] ?? null) ?? $this->stringOrNull($file['title'] ?? null) ?? $copy?->name ?? __('file');
        $mime  = $copy?->mime_type ?? $this->stringOrNull($file['mimetype'] ?? null);
        $state = $this->state($file, $copy);
        $state = $state === 'ready' && $id === null ? 'pending' : $state;
        $ready = $state === 'ready';
        $url   = $ready ? route(self::ROUTE, ['slackMessage' => $messageId, 'slackFile' => $id]) : null;
        $shown = $ready && !$isPrivate && $copy->media_id && in_array($mime, CopySlackMessageFile::IMAGE_MIME_TYPES, true);

        return [
            'id'            => $id,
            'name'          => $name,
            'type'          => $this->type($name, $file),
            'size'          => is_numeric($copy?->size ?? $file['size'] ?? null) ? (int) ($copy?->size ?? $file['size']) : null,
            'state'         => $state,
            'note'          => $isPrivate && $state === 'pending' ? __('File not available yet') : $this->note($state, $copy),
            'is_image'      => $shown,
            'opens_in_tab'  => $ready && !$isPrivate && $copy->media_id && $mime === ShowSlackMessageFile::PDF,
            'url'           => $url,
            'thumbnail_url' => $shown ? $url.'?variant=thumbnail' : null,
            'external_url'  => $state === 'external' ? $this->externalUrl($file, $copy) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function privateRow(array $file): array
    {
        $name = $this->stringOrNull($file['name'] ?? null) ?? $this->stringOrNull($file['title'] ?? null) ?? __('file');

        return [
            'id'            => null,
            'name'          => $name,
            'type'          => $this->type($name, $file),
            'size'          => is_numeric($file['size'] ?? null) ? (int) $file['size'] : null,
            'state'         => 'pending',
            'note'          => __('File not available yet'),
            'is_image'      => false,
            'opens_in_tab'  => false,
            'url'           => null,
            'thumbnail_url' => null,
            'external_url'  => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $file
     */
    private function state(array $file, ?SlackFile $copy): string
    {
        $mode = $this->stringOrNull($file['mode'] ?? null) ?? $copy?->mode;

        return match (true) {
            $copy?->state === SlackFileStateEnum::COPIED && self::hasCopy($copy)                        => 'ready',
            $copy?->state === SlackFileStateEnum::EXTERNAL, !$copy && $mode === 'external'               => 'external',
            $copy?->state === SlackFileStateEnum::SKIPPED                                               => 'skipped',
            in_array($copy?->state, [SlackFileStateEnum::NOT_AVAILABLE, SlackFileStateEnum::COPIED], true),
            !$copy && CopySlackMessageFile::isMarkedGone($file)                                         => 'not_available',
            default                                                                                     => 'pending',
        };
    }

    public static function hasCopy(SlackFile $copy): bool
    {
        if ($copy->is_private) {
            return $copy->media_id === null && in_array($copy->disk, [CopySlackMessageFile::LARGE_DISK, CopySlackMessageFile::PRIVATE_DISK], true) && (string) $copy->path !== '';
        }

        return $copy->media_id !== null || ($copy->disk === CopySlackMessageFile::LARGE_DISK && (string) $copy->path !== '');
    }

    private function note(string $state, ?SlackFile $copy): ?string
    {
        return match ($state) {
            'pending'       => __('Not copied from Slack yet'),
            'skipped'       => str_starts_with((string) $copy?->reason, 'larger than') ? __('Too large to keep a copy') : __('No copy kept'),
            'not_available' => __('No longer available'),
            'external'      => __('Kept outside Slack'),
            default         => null,
        };
    }

    /**
     * @param  array<string, mixed>  $file
     */
    private function type(string $name, array $file): ?string
    {
        $type = pathinfo($name, PATHINFO_EXTENSION) ?: (string) ($this->stringOrNull($file['filetype'] ?? null) ?? '');

        return preg_match('/^[A-Za-z0-9]{1,8}$/', $type) ? strtoupper($type) : null;
    }

    /**
     * @param  array<string, mixed>  $file
     */
    private function externalUrl(array $file, ?SlackFile $copy): ?string
    {
        $url = $this->stringOrNull($file['external_url'] ?? null) ?? $this->stringOrNull($file['url_private'] ?? null)
            ?? $this->stringOrNull($copy?->data['external_url'] ?? null) ?? $this->stringOrNull($copy?->data['url_private'] ?? null);

        return FormatSlackMessages::isSafeUrl($url) && preg_match('/^https?:\/\//i', $url) === 1 ? $url : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
