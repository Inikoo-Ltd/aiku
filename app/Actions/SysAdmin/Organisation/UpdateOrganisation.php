<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\Organisation;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrganisation extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Organisation $organisation, array $modelData): Organisation
    {
        if (Arr::has($modelData, 'ui_name')) {
            data_set($modelData, "settings.ui.name", Arr::pull($modelData, 'ui_name'));
        }
        if (Arr::has($modelData, 'google_client_id')) {
            data_set($modelData, "settings.google.id", Arr::pull($modelData, 'google_client_id'));
        }
        if (Arr::has($modelData, 'google_client_secret')) {
            data_set($modelData, "settings.google.secret", Arr::pull($modelData, 'google_client_secret'));
        }
        if (Arr::has($modelData, 'google_drive_folder_key')) {
            data_set($modelData, "settings.google.drive.folder", Arr::pull($modelData, 'google_drive_folder_key'));
        }
        if (Arr::has($modelData, 'show_isdoc')) {
            data_set($modelData, "settings.invoice_export.show_isdoc", Arr::pull($modelData, 'show_isdoc'));
        }

        if (Arr::has($modelData, 'show_omega')) {
            data_set($modelData, "settings.invoice_export.show_omega", Arr::pull($modelData, 'show_omega'));
        }

        if (Arr::has($modelData, 'attach_isdoc_to_pdf')) {
            data_set($modelData, "settings.invoice_export.attach_isdoc_to_pdf", Arr::pull($modelData, 'attach_isdoc_to_pdf'));
        }


        if (Arr::has($modelData, 'address')) {
            $addressData = Arr::get($modelData, 'address');
            Arr::forget($modelData, 'address');
            UpdateAddress::run($organisation->address, $addressData);
            $organisation->updateQuietly(
                [
                    'location' => $organisation->address->getLocation()
                ]
            );
        }

        if (Arr::has($modelData, 'logo')) {
            /** @var UploadedFile $image */
            $image = Arr::get($modelData, 'logo');
            data_forget($modelData, 'logo');
            $imageData    = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $organisation = SaveModelImage::run(
                model: $organisation,
                imageData: $imageData,
                scope: 'logo'
            );
        }


        $organisation = $this->update($organisation, $modelData, ['data', 'settings']);

        $organisation->refresh();


        return $organisation;
    }


    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(
            [
                'org-admin.'.$this->organisation->id
            ]
        );
    }

    public function rules(): array
    {
        $rules = [
            'name'                    => ['sometimes', 'required', 'string', 'max:255'],
            'ui_name'                 => ['sometimes', 'required', 'string', 'max:32'],
            'contact_name'            => ['sometimes', 'string', 'max:255'],
            'google_client_id'        => ['sometimes', 'string'],
            'google_client_secret'    => ['sometimes', 'string'],
            'show_omega'              => ['sometimes', 'boolean'],
            'attach_isdoc_to_pdf'     => ['sometimes', 'boolean'],
            'google_drive_folder_key' => ['sometimes', 'string'],
            'address'                 => ['sometimes', 'required', new ValidAddress()],
            'language_id'             => ['sometimes', 'exists:languages,id'],
            'timezone_id'             => ['sometimes', 'exists:timezones,id'],
            'currency_id'             => ['sometimes', 'exists:currencies,id'],
            'email'                   => ['sometimes', 'nullable', 'email'],
            'phone'                   => ['sometimes', 'nullable', new Phone()],
            'logo'                    => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'colour'                  => ['sometimes', 'string'],
        ];

        if (!$this->strict) {
            $rules['source'] = ['sometimes', 'array'];
            $rules           = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle(
            organisation: $organisation,
            modelData: $this->validatedData
        );
    }

    public function action(Organisation $organisation, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Organisation
    {
        $this->strict = $strict;
        if (!$audit) {
            Organisation::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }
}
