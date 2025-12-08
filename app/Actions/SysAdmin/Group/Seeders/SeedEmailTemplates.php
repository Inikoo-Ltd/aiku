<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Comms\EmailTemplate\StoreEmailTemplate;
use App\Actions\Comms\EmailTemplate\UpdateEmailTemplate;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Comms\EmailTemplate;
use App\Models\Helpers\Language;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedEmailTemplates
{
    use AsAction;

    public function handle(Group $group): void
    {
        $templates = json_decode(file_get_contents(database_path('seeders/datasets/email-templates/email-templates.json')), true);
        foreach ($templates as $template) {
            $basePath = 'seeders/datasets/email-templates/'.explode('.', Arr::get($template, 'content'))[0];

            $filePath = database_path($basePath.'/'.Arr::get($template, 'content'));

            $languageCode = Arr::get($template, 'language', 'en');
            /** @var Language $language */
            $language = Language::where('code', $languageCode)->firstOrFail();

            $data = [
                'outboxes' => Arr::get($template, 'outboxes', []),
            ];

            $builder = Arr::get($template, 'builder');

            $layout = [];
            if (in_array($builder, ['unlayer', 'beefree'])) {
                $layout = json_decode(file_get_contents($filePath), true);
            } elseif ($builder == 'blade') {
                $layout = [
                    'blade_template' => file_get_contents($filePath),
                ];
            }

            if ($emailTemplate = EmailTemplate::where('name', Arr::get($template, 'name'))->where('is_seeded', true)->first()) {
                UpdateEmailTemplate::make()->action(
                    $emailTemplate,
                    [
                        'name' => Arr::get($template, 'name'),
                        'layout' => $layout,
                        'arguments' => Arr::get($template, 'arguments', []),
                        'data' => $data,
                    ]
                );
            } else {
                $emailTemplate = StoreEmailTemplate::make()->action(
                    $group,
                    [
                        'name' => Arr::get($template, 'name'),
                        'layout' => $layout,
                        'is_seeded' => true,
                        'builder' => $builder,
                        'state' => EmailTemplateStateEnum::ACTIVE,
                        'active_at' => now(),
                        'language_id' => $language->id,
                        'data' => $data,
                    ],
                    strict: false
                );
            }

            $imagesPath = database_path($basePath.'/images');

            if (File::exists($imagesPath)) {
                $encodedLayout = json_encode($layout);

                if (preg_match_all('/{{media\(([a-z._\-0-9]+)\)}}/i', $encodedLayout, $matches)) {
                    $imageNames = array_unique($matches[1]);

                    foreach ($imageNames as $imageName) {
                        try {
                            $file = File::get($imagesPath.'/'.$imageName);
                        } catch (\Exception) {
                            continue;
                        }
                        $checksum = md5($file);

                        if (! $media = $emailTemplate->images()->where('checksum', $checksum)->where('collection_name', 'content')->first()) {
                            /** @var Media $media */
                            $media = StoreMediaFromFile::run(
                                $emailTemplate,
                                [
                                    'path' => $imagesPath.'/'.$imageName,
                                    'checksum' => $checksum,
                                    'originalName' => $imageName,
                                ],
                                'content'
                            );
                        }
                        if ($media) {
                            $pictureSrc = Arr::get(GetPictureSources::run($media->getImage()), 'original').'?id='.$media->ulid;
                            $encodedLayout = preg_replace('/{{media\('.$imageName.'\)}}/i', $pictureSrc, $encodedLayout);
                        }
                    }

                    $emailTemplate->update([
                        'layout' => json_decode($encodedLayout, true),
                    ]);
                }

                $checksum = md5_file(database_path($basePath).'/'.Arr::get($template, 'image'));

                if ($emailTemplate->screenshot && $emailTemplate->screenshot->checksum === $checksum) {
                    continue;
                }

                $media = StoreMediaFromFile::run(
                    $emailTemplate,
                    [
                        'path' => database_path($basePath).'/'.Arr::get($template, 'image'),
                        'checksum' => $checksum,
                        'originalName' => Arr::get($template, 'image'),
                    ],
                    'screenshot'
                );

                if ($media) {
                    $emailTemplate->updateQuietly([
                        'screenshot_id' => $media->id,
                    ]);
                }
            }
        }

        Artisan::call('shop:seed_outboxes');
        Artisan::call('fulfilment:seed_outboxes');
        Artisan::call('website:seed_outboxes');
        Artisan::call('org:seed_outboxes');
    }

    public string $commandSignature = 'group:seed_email_templates';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info("Seeding email templates for group: $group->name");
            $this->handle($group);
        }

        return 0;
    }
}
