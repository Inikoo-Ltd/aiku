<?php

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetBlogWebpagesCategoryBulk extends OrgAction
{
    private Website $website;

    /**
     * A blog made from a mailshot keeps its sub type, it decides which builder the workshop opens,
     * so it is left out of the bulk change instead of failing it for every other selected blog.
     */
    public function handle(Website $website, array $modelData): int
    {
        $subType = WebpageSubTypeEnum::from(Arr::get($modelData, 'sub_type'));

        $webpages = Webpage::whereIn('id', Arr::pluck(Arr::get($modelData, 'webpages', []), 'id'))
            ->where('website_id', $website->id)
            ->where('type', WebpageTypeEnum::BLOG)
            ->get();

        $numberUpdated = 0;

        foreach ($webpages as $webpage) {
            if ($webpage->sub_type == WebpageSubTypeEnum::MAILSHOT || $webpage->sub_type == $subType) {
                continue;
            }

            UpdateWebpage::make()->action($webpage, ['sub_type' => $subType->value]);
            $numberUpdated++;
        }

        return $numberUpdated;
    }

    public function rules(): array
    {
        return [
            'webpages'      => ['required', 'array'],
            'webpages.*.id' => ['required', Rule::exists('webpages', 'id')],
            'sub_type'      => ['required', Rule::in(WebpageSubTypeEnum::blogCategoryValues($this->website->shop?->type))],
        ];
    }

    public function asController(Website $website, ActionRequest $request): void
    {
        $this->website = $website;
        $this->initialisationFromShop($website->shop, $request);

        $this->handle($website, $this->validatedData);
    }
}
