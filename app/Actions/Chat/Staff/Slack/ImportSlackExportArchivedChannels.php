<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * The aiku app cannot join an archived channel and Slack gives a bot the history of its own channels only, so the
 * history of the archived public channels comes from a workspace export (Slack admin → Import/Export Data). Only
 * channels aiku already lists as public, archived, kept and never joined are read; a channel finished once is not
 * read again, so a stopped run picks up where it was. File links in the export carry the export's own token, which
 * is taken out before anything is stored.
 */
class ImportSlackExportArchivedChannels
{
    use AsAction;

    public string $commandSignature = 'slack:import-archived-channels
        {path : Folder of the unzipped Slack workspace export}
        {--channel=* : Only these channels, by Slack id or name}
        {--dry-run : Only say what each archived channel holds in the export}';

    public string $commandDescription = 'Bring the history of the archived public Slack channels from a workspace export';

    private const string TOKEN_PATTERN = '/xox[a-z]-/i';

    /**
     * @param  list<string>  $only
     * @return list<array{channel: string, status: string, in_export: int, files: int, withheld: int, stored: int}>
     */
    public function handle(string $path, array $only = [], bool $dryRun = false): array
    {
        $root             = realpath($path);
        $exportedChannels = $root ? json_decode((string) @file_get_contents($root.'/channels.json'), true) : null;
        if (!is_array($exportedChannels)) {
            throw new RuntimeException("No channels.json in $path");
        }

        $rows = [];
        foreach ($exportedChannels as $exportedChannel) {
            $slackId = (string) Arr::get($exportedChannel, 'id');
            $name    = (string) Arr::get($exportedChannel, 'name');
            if (!Arr::get($exportedChannel, 'is_archived') || ($only && !array_intersect([$slackId, $name], $only))) {
                continue;
            }

            $folder  = self::channelFolder($root, $name);
            $days    = $folder ? (glob($folder.'/*.json') ?: []) : [];
            $channel = SlackChannel::kept()->where('slack_id', $slackId)->first();
            $row     = ['channel' => $name, 'status' => '', 'in_export' => 0, 'files' => 0, 'withheld' => 0, 'stored' => (int) $channel?->messages()->count()];
            sort($days);

            $row['status'] = match (true) {
                !$folder && $name !== '' && basename($name) !== $name => 'skipped: unsafe folder name',
                !$channel                                             => 'skipped: not a kept public channel in aiku',
                !$channel->is_archived                                => 'skipped: not archived in aiku',
                $channel->is_member                                   => 'skipped: aiku app is a member, its history comes from Slack',
                $channel->history_synced_at !== null                  => 'done before',
                !$days                                                => 'nothing in the export',
                $dryRun                                               => 'to import',
                default                                               => 'imported',
            };

            if ($row['status'] === 'to import' || $row['status'] === 'imported') {
                foreach ($days as $day) {
                    foreach ((array) json_decode((string) file_get_contents($day), true) as $message) {
                        $row['in_export']++;
                        $row['files'] += count((array) Arr::get((array) $message, 'files', []));
                        $message = self::withoutExportToken((array) $message);
                        if (preg_match(self::TOKEN_PATTERN, (string) json_encode($message))) {
                            $row['withheld']++;
                        } elseif (!$dryRun) {
                            StoreSlackMessage::make()->store($channel, $message);
                        }
                    }
                }
                if (!$dryRun) {
                    $channel->update(['history_synced_at' => now()]);
                    $row['stored'] = $channel->messages()->count();
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private static function channelFolder(string $root, string $name): ?string
    {
        if ($name === '' || basename($name) !== $name) {
            return null;
        }
        $folder = realpath($root.'/'.$name);

        return $folder && is_dir($folder) && str_starts_with($folder, $root.DIRECTORY_SEPARATOR) ? $folder : null;
    }

    /**
     * Slack file links carry the export's token as t or token; those parameters go whatever their value, and any
     * Slack token left anywhere else, plain or URL-encoded, is cut out. What still looks like a token is withheld.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public static function withoutExportToken(array $message): array
    {
        array_walk_recursive($message, function (mixed &$value) {
            if (!is_string($value)) {
                return;
            }
            if (self::isSlackUrl($value) && str_contains($value, '?')) {
                [$base, $query] = explode('?', $value, 2);
                parse_str($query, $parameters);
                $query = http_build_query(Arr::except($parameters, ['t', 'token']));
                $value = $base.($query !== '' ? '?'.$query : '');
            }
            $value = (string) preg_replace('/(?:(?:[?&]|%3F|%26)(?:t|token)(?:=|%3D))?xox[a-z]-[\w%-]*/i', '', $value);
        });

        return $message;
    }

    private static function isSlackUrl(string $value): bool
    {
        $host = (string) parse_url($value, PHP_URL_HOST);

        return str_starts_with($value, 'https://') && ($host === 'slack.com' || str_ends_with($host, '.slack.com') || str_ends_with($host, '.slack-edge.com'));
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $rows = $this->handle((string) $command->argument('path'), array_values((array) $command->option('channel')), (bool) $command->option('dry-run'));
        $command->table(['Channel', 'Status', 'Messages in export', 'Files', 'Withheld (token left)', 'Stored in aiku'], $rows);

        return 0;
    }
}
