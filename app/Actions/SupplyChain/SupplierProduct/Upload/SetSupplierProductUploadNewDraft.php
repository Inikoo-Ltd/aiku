<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Models\Helpers\Upload;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Per order column, chooses a new draft purchase order instead of adding the lines to the open draft.
 */
class SetSupplierProductUploadNewDraft extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function handle(Upload $upload, string $key, bool $newDraft): Upload
    {
        $data              = $upload->data;
        $data['new_draft'] = array_merge($data['new_draft'] ?? [], [$key => $newDraft]);
        $upload->update(['data' => $data]);

        return $upload;
    }

    public function rules(): array
    {
        return [
            'key'       => ['required', 'string', 'max:64'],
            'new_draft' => ['required', 'boolean'],
        ];
    }

    public function asController(Upload $upload, ActionRequest $request): Upload
    {
        $this->initialisationFromGroup($upload->group, $request);

        return $this->handle($upload, $this->validatedData['key'], $this->validatedData['new_draft']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
