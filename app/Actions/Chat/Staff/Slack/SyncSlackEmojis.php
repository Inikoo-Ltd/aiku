<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackEmoji;
use App\Models\Chat\SlackSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class SyncSlackEmojis
{
    use AsAction;
    use WithSlack;

    public string $commandSignature = 'slack:sync-emojis';

    public string $commandDescription = 'Keep the custom emoji of the Slack workspace in aiku, with a copy of each image, so Slack messages and reactions show them';

    /**
     * @return array{emojis: int, removed: int, without_copy: int}
     */
    public function handle(): array
    {
        $client = $this->slackClient() ?? throw new RuntimeException('Slack bot token is not set');
        $listed = (array) Arr::get($this->slackCall($client, 'emoji.list', []), 'emoji', []);

        foreach ($listed as $name => $value) {
            StoreSlackEmoji::run((string) $name, (string) $value);
        }

        return [
            'emojis'       => count($listed),
            'removed'      => StoreSlackEmoji::make()->markRemoved(SlackEmoji::whereNotIn('name', array_map('strval', array_keys($listed)))->pluck('name')->all()),
            'without_copy' => SlackEmoji::whereNull('alias_for')->whereNull('media_id')->whereNull('removed_in_slack_at')->count(),
        ];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $totals = SlackSyncRun::record($this->commandSignature, fn () => $this->handle());
        $command->info("Slack custom emoji: {$totals['emojis']}, removed in Slack now: {$totals['removed']}, images not copied: {$totals['without_copy']}");

        return 0;
    }
}
