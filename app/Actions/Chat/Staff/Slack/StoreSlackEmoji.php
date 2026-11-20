<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Chat\SlackEmoji;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One custom emoji of the Slack workspace, with aiku's copy of its image; an alias ("alias:other") has no image of its own.
 */
class StoreSlackEmoji
{
    use AsAction;

    public const array IMAGE_MIME_TYPES = ['image/png', 'image/gif', 'image/jpeg', 'image/webp'];

    public function handle(string $name, string $value): SlackEmoji
    {
        $aliasFor = str_starts_with($value, 'alias:') ? substr($value, 6) : null;
        $emoji    = SlackEmoji::createOrFirst(['name' => $name]);
        $urlChanged = $emoji->url !== ($aliasFor ? null : $value);
        $emoji->fill(['alias_for' => $aliasFor, 'url' => $aliasFor ? null : $value, 'removed_in_slack_at' => null])->save();

        if (!$aliasFor && ($urlChanged || !$emoji->media_id)) {
            $this->copyImage($emoji);
        }

        return $emoji;
    }

    /**
     * An emoji_changed event of the Slack Events API; Slack asks for the whole list to be read again when the subtype is missing or new.
     *
     * @param  array<string, mixed>  $event
     */
    public function fromEvent(array $event): void
    {
        match (Arr::get($event, 'subtype')) {
            'add'    => $this->handle((string) Arr::get($event, 'name'), (string) Arr::get($event, 'value')),
            'remove' => $this->markRemoved((array) Arr::get($event, 'names', [])),
            'rename' => $this->rename((string) Arr::get($event, 'old_name'), (string) Arr::get($event, 'new_name'), (string) Arr::get($event, 'value')),
            default  => SyncSlackEmojis::run(),
        };
    }

    /**
     * @param  array<int, string>  $names
     */
    public function markRemoved(array $names): int
    {
        return SlackEmoji::whereIn('name', $names)->whereNull('removed_in_slack_at')->update(['removed_in_slack_at' => now()]);
    }

    private function rename(string $oldName, string $newName, string $value): void
    {
        $emoji = SlackEmoji::where('name', $oldName)->first();
        if (!$emoji || SlackEmoji::where('name', $newName)->exists()) {
            $this->markRemoved([$oldName]);
            $this->handle($newName, $value);

            return;
        }

        $emoji->update(['name' => $newName]);
        SlackEmoji::where('alias_for', $oldName)->update(['alias_for' => $newName]);
        $this->handle($newName, $value);
    }

    private function copyImage(SlackEmoji $emoji): void
    {
        $url  = (string) $emoji->url;
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !preg_match('/(^|\.)(slack-edge|slack)\.com$/', $host)) {
            return;
        }

        $response = Http::timeout(30)->get($url);
        $mimeType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if ($response->failed() || !in_array($mimeType, self::IMAGE_MIME_TYPES, true)) {
            return;
        }

        $path = tempnam(sys_get_temp_dir(), 'slack-emoji-');
        try {
            file_put_contents($path, $response->body());
            $media = StoreMediaFromFile::run($emoji, [
                'path'         => $path,
                'originalName' => $emoji->name,
                'extension'    => explode('/', $mimeType)[1] === 'jpeg' ? 'jpg' : explode('/', $mimeType)[1],
                'checksum'     => md5_file($path),
            ], 'slack_emojis');
            $emoji->update(['media_id' => $media->id]);
        } finally {
            unlink($path);
        }
    }
}
