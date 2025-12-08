<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Models\AnnouncementTemplate;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedAnnouncementTemplates
{
    use AsAction;

    public function handle(Group $group): void
    {
        $files = Storage::disk('datasets')->files('announcement/screenshot');

        foreach ($files as $file) {
            $announcementTemplateCode = pathinfo($file, PATHINFO_FILENAME);

            $announcementTemplate = AnnouncementTemplate::where('code', $announcementTemplateCode)->first();
            if ($announcementTemplate) {
                $announcementTemplate->update([
                    'code' => $announcementTemplateCode,
                ]);

                $announcementTemplate->refresh();
            } else {
                $announcementTemplate = AnnouncementTemplate::create([
                    'group_id' => $group->id,
                    'code' => $announcementTemplateCode,
                ]);
            }

            if (Storage::disk('datasets')->exists($file)) {
                SaveModelImage::run(
                    $announcementTemplate,
                    [
                        'path' => Storage::disk('datasets')->path($file),
                        'originalName' => $announcementTemplate->code.'.png',

                    ],
                    'screenshot',
                    'screenshot_id'
                );
            }
        }

        $diff = array_diff(AnnouncementTemplate::all()->pluck('code')->toArray(), array_map(function ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files));

        AnnouncementTemplate::whereIn('code', $diff)->delete();
    }

    public string $commandSignature = 'group:seed_announcement_templates';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $this->handle($group);
        }

        return 0;
    }
}
