<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Brief;

use App\Models\Chat\ChatAiBriefSection;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sets one part of the AI customer service brief by adding a new version of it; the routine reads
 * the newest version through customer-service-brief-tool on its next run.
 */
class SetChatAiBriefSection
{
    use AsAction;

    public string $commandSignature = 'chat:brief-set {key : core, or appendix_2 to appendix_12} {file : Text file with the section} {--t|title= : Title, kept from the last version when left out} {--g|group= : Group slug, the first group when left out}';

    public function handle(Group $group, string $key, string $body, ?string $title = null, ?int $userId = null): ChatAiBriefSection
    {
        $title ??= ChatAiBriefSection::where('group_id', $group->id)->where('key', $key)->latest('id')->value('title') ?? $key;

        return ChatAiBriefSection::create([
            'group_id'       => $group->id,
            'key'            => $key,
            'title'          => $title,
            'body'           => $body,
            'set_by_user_id' => $userId,
        ]);
    }

    public function asCommand(Command $command): int
    {
        $group = $command->option('group') ? Group::where('slug', $command->option('group'))->firstOrFail() : Group::firstOrFail();
        $file  = $command->argument('file');

        if (!is_readable($file) || trim((string) file_get_contents($file)) === '') {
            $command->error("Cannot read $file, or it is empty. Nothing was set.");

            return 1;
        }

        $section = $this->handle($group, $command->argument('key'), trim((string) file_get_contents($file)), $command->option('title'));
        $command->info("{$section->key} set: version {$section->id}, ".mb_strlen($section->body).' characters');

        return 0;
    }
}
