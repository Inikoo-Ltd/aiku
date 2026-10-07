<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Enums\Helpers\Import\UploadStateEnum;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Records what staff decide on a preview row: accepting a blocking finding ("I accept responsibility"),
 * confirming a link to an existing trade unit, skipping the row, or typing the SKO name.
 */
class UpdateSupplierProductUploadRecord extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function handle(UploadRecord $record, array $modelData, ?int $userId): UploadRecord
    {
        $data = $record->data ?? [];

        foreach (Arr::get($modelData, 'decisions', []) as $code => $accepted) {
            if ($accepted) {
                $data['decisions'][$code] = ['accepted' => true, 'user_id' => $userId, 'at' => now()->toIso8601String()];
            } else {
                unset($data['decisions'][$code]);
            }
        }

        if (Arr::has($modelData, 'skip')) {
            $data['skip'] = (bool)$modelData['skip'];
        }

        $values = $record->values;
        if (Arr::has($modelData, 'sko_name')) {
            $values['sko_name'] = trim((string)$modelData['sko_name']) ?: null;
        }

        $record->update(['data' => $data, 'values' => $values]);

        return $record;
    }

    public function rules(): array
    {
        return [
            'decisions'   => ['sometimes', 'array'],
            'decisions.*' => ['boolean'],
            'skip'        => ['sometimes', 'boolean'],
            'sko_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function asController(Upload $upload, UploadRecord $record, ActionRequest $request): UploadRecord
    {
        if ($record->upload_id !== $upload->id || $upload->state !== UploadStateEnum::WAITING_CONFIRMATION) {
            throw ValidationException::withMessages(['upload' => __('This upload is not waiting for confirmation.')]);
        }

        $this->initialisationFromGroup($upload->group, $request);

        return $this->handle($record, $this->validatedData, $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
