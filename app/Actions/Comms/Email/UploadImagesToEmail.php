<?php

namespace App\Actions\Comms\Email;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Email;
use App\Models\Comms\EmailTemplate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToEmail extends OrgAction
{
    use WithAttachMediaToModel;

    public const string MEDIA_SCOPE = 'email';

    /**
     * @param  array{images: array<int, UploadedFile>}  $modelData
     */
    public function handle(Shop $shop, array $modelData): Collection
    {
        $medias = [];

        foreach ($modelData['images'] as $imageFile) {
            $media = StoreMediaFromFile::run(
                $shop,
                [
                    'path'         => $imageFile->getPathName(),
                    'originalName' => $imageFile->getClientOriginalName(),
                    'extension'    => $imageFile->guessClientExtension(),
                    'checksum'     => md5_file($imageFile->getPathName()),
                ],
                self::MEDIA_SCOPE
            );

            $this->attachMediaToModel($shop, $media, self::MEDIA_SCOPE);
            $medias[] = $media;
        }

        return collect($medias);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $shopId = $this->shop->id;

        return $request->user()->authTo([
            "shop-admin.$shopId",
            "marketing.$shopId.edit",
            "supervisor-marketing.$shopId",
            "crm.$shopId.edit",
            "crm.$shopId.prospects.edit",
            "web.$shopId.edit",
        ]);
    }

    public function rules(): array
    {
        return [
            'images'   => ['required', 'array', 'max:10'],
            'images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,gif', 'max:10240'],
        ];
    }

    public function asController(Email $email, ActionRequest $request): Collection
    {
        abort_unless($email->shop, 404);

        $this->initialisationFromShop($email->shop, $request);

        return $this->handle($email->shop, $this->validatedData);
    }

    public function inEmailTemplate(EmailTemplate $emailTemplate, ActionRequest $request): Collection
    {
        abort_unless($emailTemplate->shop, 404);

        $this->initialisationFromShop($emailTemplate->shop, $request);

        return $this->handle($emailTemplate->shop, $this->validatedData);
    }

    public function jsonResponse(Collection $medias): AnonymousResourceCollection
    {
        return ImageResource::collection($medias);
    }
}
