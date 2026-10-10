<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Packaging;

use App\Actions\OrgAction;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Models\Goods\EprManualLine;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreEprManualLine extends OrgAction
{
    public const array ACTIVITIES = ['SO', 'IM', 'PF'];
    public const array TYPES = ['HH', 'NH', 'CW', 'OW', 'HDC', 'NDC'];
    public const array CLASSES = ['P1', 'P2', 'P3', 'P4'];
    public const array NATIONS = ['EN', 'NI', 'SC', 'WS'];

    public function handle(Organisation $organisation, array $modelData): EprManualLine
    {
        return EprManualLine::create([
            ...$modelData,
            'group_id'        => $organisation->group_id,
            'organisation_id' => $organisation->id,
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-reports.'.$this->organisation->id);
    }

    public function rules(): array
    {
        return [
            'date_from'         => ['required', 'date'],
            'date_to'           => ['required', 'date', 'after_or_equal:date_from'],
            'activity'          => ['required', Rule::in(self::ACTIVITIES)],
            'packaging_type'    => ['required', Rule::in(self::TYPES)],
            'packaging_class'   => ['required', Rule::in(self::CLASSES)],
            'material_category' => ['required', Rule::enum(PackagingMaterialCategoryEnum::class)],
            'from_nation'       => ['nullable', Rule::in(self::NATIONS)],
            'to_nation'         => ['nullable', Rule::in(self::NATIONS)],
            'kg'                => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): EprManualLine
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, [...$this->validatedData, 'user_id' => $request->user()->id]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
