<?php

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Comms\Email\GetEmailSocialIcons;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedEmailSocialIcons
{
    use AsAction;
    use WithAttachMediaToModel;

    public const string MEDIA_SCOPE = 'email_social_icon';

    public function handle(Group $group): int
    {
        $existingIcons = $group->images()->wherePivot('scope', self::MEDIA_SCOPE)->pluck('model_has_media.sub_scope')->all();
        $storedIcons   = 0;

        foreach (glob(resource_path('art/email_social_icons/*/*.png')) as $filename) {
            $iconSet  = basename(dirname($filename));
            $network  = basename($filename, '.png');
            $iconKey  = "$iconSet/$network";

            if (in_array($iconKey, $existingIcons, true)) {
                continue;
            }

            $media = StoreMediaFromFile::run(
                $group,
                [
                    'path'         => $filename,
                    'originalName' => "$iconSet-$network.png",
                    'extension'    => 'png',
                    'checksum'     => md5_file($filename),
                ],
                self::MEDIA_SCOPE
            );

            $this->attachMediaToModel($group, $media, self::MEDIA_SCOPE, $iconKey);
            $storedIcons++;
        }

        Cache::forget(GetEmailSocialIcons::cacheKey($group));

        return $storedIcons;
    }

    public string $commandSignature = 'group:seed_email_social_icons';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $storedIcons = $this->handle($group);
            $command->info("$group->name: $storedIcons email social icons stored");
        }

        return 0;
    }
}
