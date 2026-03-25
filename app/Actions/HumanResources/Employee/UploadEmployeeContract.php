<?php

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\Helpers\Media;
use App\Models\HumanResources\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UploadEmployeeContract extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function rules(): array
    {
        return [
            'contract_document' => ['required', File::types(['pdf'])->max(5 * 1024)],
        ];
    }

    public function handle(Employee $employee, UploadedFile $contractDocument): Media
    {
        $media = $employee->addMedia($contractDocument)
            ->withProperties([
                'group_id' => $employee->group_id,
                'type'     => 'attachment',
                'ulid'     => (string) Str::ulid(),
            ])
            ->toMediaCollection('contracts');

        return $media;
    }

    public function asController(Employee $employee, ActionRequest $request): Media
    {
        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee, $this->validatedData['contract_document']);
    }

    public function htmlResponse(Media $media): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title' => __('Success!'),
            'description' => __('Contract document uploaded successfully.'),
        ]);
    }

    public function jsonResponse(Media $media): JsonResponse
    {
        return new JsonResponse([
            'employee_id' => $media->model_id,
            'contract_document' => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'url' => route('grp.media.show', ['media' => $media->ulid]),
                'size' => $media->size,
            ],
        ]);
    }
}
