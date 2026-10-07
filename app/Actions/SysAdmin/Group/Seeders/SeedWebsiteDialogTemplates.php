<?php

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Models\SysAdmin\Group;
use App\Models\Web\WebsiteDialogTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedWebsiteDialogTemplates
{
    use AsAction;

    public string $commandSignature = 'group:seed_website_dialog_templates';

    /**
     * Templates live as json files in the datasets, one per template: adding a file adds a
     * template, removing it removes the template. Dialogs already built keep their own copy of the
     * content, so removing a template never changes a dialog.
     */
    public function handle(Group $group): void
    {
        $codes = [];

        foreach (Storage::disk('datasets')->files('website-dialog-templates') as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') {
                continue;
            }

            $templateData = Storage::disk('datasets')->json($file);
            $code         = Arr::get($templateData, 'code');
            $codes[]      = $code;

            WebsiteDialogTemplate::updateOrCreate(
                [
                    'group_id' => $group->id,
                    'code'     => $code,
                ],
                [
                    'name'      => Arr::get($templateData, 'name'),
                    'component' => Arr::get($templateData, 'component'),
                    'position'  => Arr::get($templateData, 'position', 0),
                    'data'      => Arr::only($templateData, ['fields', 'container_properties']),
                ]
            );
        }

        WebsiteDialogTemplate::where('group_id', $group->id)
            ->whereNotIn('code', $codes)
            ->delete();
    }

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info("Seeding website dialog templates for group: $group->name");
            $this->handle($group);
        }

        return 0;
    }
}
