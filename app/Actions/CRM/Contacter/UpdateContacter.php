<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Prospect;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithProspectPrepareForValidation;
use App\Http\Resources\Lead\ProspectResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Contacter;
use App\Models\CRM\Prospect;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateContacter extends OrgAction
{
    use WithActionUpdate;
    use WithProspectPrepareForValidation;

    private Contacter $contacter;

    public function handle(Contacter $contacter, array $modelData): Contacter
    {


        return $contacter;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        // $rules = [
        //     'contacted_state'   => ['sometimes', Rule::enum(ProspectContactedStateEnum::class)],
        //     'fail_status'       => ['sometimes', 'nullable', Rule::enum(ProspectFailStatusEnum::class)],
        //     'success_status'    => ['sometimes', 'nullable', Rule::enum(ProspectSuccessStatusEnum::class)],
        //     'dont_contact_me'   => ['sometimes', 'boolean'],
        //     'last_contacted_at' => 'sometimes|nullable|date',
        //     'contact_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
        //     'company_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
        //     'address'           => ['sometimes', 'nullable', new ValidAddress()],
        //     'email'             => [
        //         'sometimes',
        //         $this->strict ? 'email' : 'string:500',
        //         new IUnique(
        //             table: 'prospects',
        //             extraConditions: [
        //                 ['column' => 'shop_id', 'value' => $this->shop->id],
        //                 ['column' => 'id', 'operator' => '!=', 'value' => $this->prospect->id]

        //             ]
        //         ),

        //     ],
        //     'phone'             => [
        //         'sometimes',
        //         'nullable',
        //         $this->strict ? new Phone() : 'string:255',
        //         new IUnique(
        //             table: 'prospects',
        //             extraConditions: [
        //                 ['column' => 'shop_id', 'value' => $this->shop->id],
        //                 ['column' => 'id', 'operator' => '!=', 'value' => $this->prospect->id]

        //             ]
        //         ),
        //     ],
        //     'contact_website'   => [
        //         'sometimes',
        //         'nullable',
        //         $this->strict ? 'url:http,https' : 'string:255',
        //         new IUnique(
        //             table: 'prospects',
        //             extraConditions: [
        //                 ['column' => 'shop_id', 'value' => $this->shop->id],
        //                 ['column' => 'id', 'operator' => '!=', 'value' => $this->prospect->id]

        //             ]
        //         ),
        //     ],
        // ];

        // if (!$this->strict) {
        //     $rules['last_fetched_at'] = ['sometimes', 'date'];
        // }


        return [];
    }

    public function asController(Organisation $organisation, Shop $shop, Contacter $contacter, ActionRequest $request): Contacter
    {
        $this->initialisationFromShop($contacter->shop, $request);
        // $this->prospect = $prospect;

        return $this->handle($contacter, $this->validatedData);
    }

    public function action(Contacter $contacter, $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Contacter
    {
        $this->strict = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        // $this->prospect       = $prospect;
        $this->initialisationFromShop($contacter->shop, $modelData);

        return $this->handle($contacter, $this->validatedData);
    }

    public function jsonResponse(Prospect $prospect): ProspectResource
    {
        return new ProspectResource($prospect);
    }
}
