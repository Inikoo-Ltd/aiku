<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Apr 2026 09:52:24 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Tag;

use App\Actions\Helpers\Translations\Translate;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use App\Models\Helpers\Tag;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateTag
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function handle(Tag $tag):Tag
    {
        $english          = Language::where('code', 'en')->first();
        $websiteLanguages = Shop::pluck('language_id')->unique()->toArray();

        $translations = [];
        foreach ($websiteLanguages as $languageId) {
            $language = Language::find($languageId);

            $translatedLabel = Translate::run($tag->name, $english, $language);
            $translations[$language->code] = $translatedLabel;
        }
        $tag->setTranslations('label', $translations);
        $tag->save();

        return $tag;
    }

    public function getCommandSignature(): string
    {
        return 'tag:translate {tag?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('tag')) {
            $tag = Tag::where('slug', $command->argument('tag'))->firstOrFail();
            $this->handle($tag);

            return 0;
        }

        foreach (Tag::all() as $tag) {
            $this->handle($tag);
        }

        return 0;
    }

}
