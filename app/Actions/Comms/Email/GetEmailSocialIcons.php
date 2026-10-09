<?php

namespace App\Actions\Comms\Email;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\SysAdmin\Group\Seeders\SeedEmailSocialIcons;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetEmailSocialIcons
{
    use AsAction;

    /**
     * @return array<string, string> icon url keyed by "{icon set}/{network}"
     */
    public function handle(Group $group): array
    {
        return Cache::remember(
            self::cacheKey($group),
            now()->addDay(),
            fn () => $group->images()
                ->wherePivot('scope', SeedEmailSocialIcons::MEDIA_SCOPE)
                ->get()
                ->mapWithKeys(fn (Media $media) => [
                    $media->pivot->sub_scope => Arr::get(GetPictureSources::run($media->getImage()), 'original'),
                ])
                ->all()
        );
    }

    public static function cacheKey(Group $group): string
    {
        return "email-social-icons:$group->id";
    }
}
