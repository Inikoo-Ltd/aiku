<?php
/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateTag extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tag, $this->validatedData);
    }

    public function inCustomer(Customer $customer, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisation($customer->organisation, $request);

        return $this->handle($tag, $this->validatedData);
    }

    public function asController(Organisation $organisation, Tag $tag, ActionRequest $request): Tag
    {
        $this->initialisation($organisation, $request);

        return $this->handle($tag, $this->validatedData);
    }

    public function htmlResponse(Tag $tag=null): RedirectResponse|null
    {
        if (!$tag) {
            return null;
        }

        return Redirect::route('grp.org.tags.show', [$this->organisation->slug])->with('notification', [
            'status'  => 'success',
            'title'   => __('Success'),
            'description' => __('Tag successfully updated.'),
        ]);
    }

    public function handle(Tag $tag, array $modelData): Tag
    {
        $image = Arr::pull($modelData, 'image');

        if ($image) {
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];

            $tag = SaveModelImage::run(
                model: $tag,
                imageData: $imageData,
                scope: 'image',
            );
        }

        $tag->update($modelData);

        return $tag;
    }

    public function rules(): array
    {
        return [
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'scope' => [
                'sometimes',
                'nullable',
                'string',
                'in:' . implode(',', array_column(TagScopeEnum::cases(), 'value')),
            ],
            'image' => [
                'sometimes',
                'nullable',
                File::image()->max(12 * 1024),
            ],
        ];
    }
}
