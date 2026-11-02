<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-15h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Helpers\Media\AttachAttachmentToModel;
use App\Actions\Helpers\Media\DetachAttachmentFromModel;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentFile;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UploadProductDevelopmentFiles extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string ACCEPTED_EXTENSIONS = 'pdf,ai,psd,jpg,jpeg,png,webp,heic,heif,zip,mp4,mov,webm';

    public const int MAX_FILES = 20;

    public const int MAX_KILOBYTES = 51200;

    private ?ProductDevelopment $productDevelopment = null;

    private function target(): ProductDevelopment
    {
        return $this->productDevelopment ?? request()->route('productDevelopment');
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->target())
            && $this->productDevelopmentAccess()->canUpload($request->user(), $this->target());
    }

    /**
     * @param array<int, UploadedFile> $files
     *
     * @return array<int, ProductDevelopmentFile>
     */
    public function handle(ProductDevelopment $productDevelopment, User $user, array $files, ProductDevelopmentFileScopeEnum $scope, ?ProductDevelopmentItem $item, ?int $checklistItemId = null): array
    {
        return DB::transaction(function () use ($productDevelopment, $user, $files, $scope, $item, $checklistItemId) {
            $current = $productDevelopment->files()
                ->where('scope', $scope->value)
                ->where('product_development_item_id', $item?->id)
                ->whereNull('superseded_at');

            $version = 1;
            if ($scope->isVersioned()) {
                $version = (int) $productDevelopment->files()
                    ->where('scope', $scope->value)
                    ->where('product_development_item_id', $item?->id)
                    ->max('version') + 1;
                $this->retirePrevious($current->get(), $item, $scope);
            }

            $stored = [];
            foreach ($files as $file) {
                $media    = $this->storeMedia($productDevelopment, $file, $scope, $item);
                $stored[] = $productDevelopment->files()->create([
                    'product_development_item_id' => $item?->id,
                    'checklist_item_id'           => $checklistItemId,
                    'media_id'                    => $media->id,
                    'scope'                       => $scope,
                    'version'                     => $version,
                    'uploaded_by_id'              => $user->id,
                ]);
            }

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::FILES_UPLOADED, [], [
                'scope'   => $scope->value,
                'sku'     => $item?->tradeUnit?->code,
                'version' => $scope->isVersioned() ? $version : null,
                'files'   => collect($files)->map(fn (UploadedFile $file) => $file->getClientOriginalName())->all(),
            ]);

            $this->notifyWhoNeedsIt($productDevelopment, $user, $scope, $item, count($files));

            return $stored;
        });
    }

    /**
     * Photos of a sample or a packaging sample, kept on the development so they also show on the Files tab.
     *
     * @param array<int, UploadedFile> $photos
     *
     * @return array<int, ProductDevelopmentFile>
     */
    public function attachPhotos(Model $model, ProductDevelopmentItem $item, User $user, array $photos, ProductDevelopmentFileScopeEnum $scope): array
    {
        $productDevelopment = $item->productDevelopment;

        return DB::transaction(function () use ($model, $item, $user, $photos, $scope, $productDevelopment) {
            $stored = [];
            foreach ($photos as $photo) {
                $stored[] = $productDevelopment->files()->create([
                    'product_development_item_id' => $item->id,
                    'media_id'                    => $this->storeMedia($productDevelopment, $photo, $scope, null)->id,
                    'scope'                       => $scope,
                    'version'                     => 1,
                    'uploaded_by_id'              => $user->id,
                    'model_type'                  => $model->getMorphClass(),
                    'model_id'                    => $model->getKey(),
                ]);
            }

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::FILES_UPLOADED, [], [
                'scope' => $scope->value,
                'sku'   => $item->tradeUnit?->code,
                'files' => collect($photos)->map(fn (UploadedFile $photo) => $photo->getClientOriginalName())->all(),
            ]);

            return $stored;
        });
    }

    /**
     * @return array{id: int, name: string|null, url: string|null, preview_url: string|null, remove_route: array{name: string, parameters: array<string, int>}|null}
     */
    public static function photoRow(ProductDevelopmentFile $file, bool $canRemove): array
    {
        return [
            'id'           => $file->id,
            'name'         => $file->media?->name,
            'url'          => $file->media ? route('grp.media.download', ['media' => $file->media->ulid]) : null,
            'preview_url'  => $file->media ? route('grp.media.download', ['media' => $file->media->ulid, 'inline' => 1]) : null,
            'remove_route' => $canRemove ? ['name' => 'grp.models.product_development.photo.remove', 'parameters' => ['productDevelopmentFile' => $file->id]] : null,
        ];
    }

    private function notifyWhoNeedsIt(ProductDevelopment $productDevelopment, User $uploader, ProductDevelopmentFileScopeEnum $scope, ?ProductDevelopmentItem $item, int $count): void
    {
        $notifier   = NotifyProductDevelopmentUsers::make();
        $recipients = collect($scope->neededBy())
            ->flatMap(fn (ProductDevelopmentMemberRoleEnum $role) => $notifier->usersWithRole($productDevelopment, $role))
            ->whenEmpty(fn ($users) => $users->push($productDevelopment->owner));

        $label = ProductDevelopmentFileScopeEnum::labels()[$scope->value];
        $notifier->handle(
            $productDevelopment,
            $recipients,
            UserNotificationEnum::PRODUCT_DEVELOPMENT_FILES,
            __(':label uploaded on :code', ['label' => $label, 'code' => $productDevelopment->code]),
            trans_choice(':name uploaded :count file|:name uploaded :count files', $count, ['name' => $uploader->contact_name ?: $uploader->username, 'count' => $count]).($item ? ' · '.$item->tradeUnit->code : ''),
            'files',
            except: $uploader,
        );
    }

    private function retirePrevious($previousFiles, ?ProductDevelopmentItem $item, ProductDevelopmentFileScopeEnum $scope): void
    {
        $tradeUnitScope = $scope->tradeUnitAttachmentScope();

        if ($tradeUnitScope && $item) {
            $tradeUnit     = $item->tradeUnit;
            $onTradeUnit   = $tradeUnit->attachments()->wherePivot('scope', $tradeUnitScope->value)->get();
            $knownMediaIds = $item->productDevelopment->files()->where('product_development_item_id', $item->id)->where('scope', $scope->value)->pluck('media_id');

            foreach ($onTradeUnit->reject(fn (Media $media) => $knownMediaIds->contains($media->id)) as $earlierMedia) {
                $item->productDevelopment->files()->create([
                    'product_development_item_id' => $item->id,
                    'media_id'                    => $earlierMedia->id,
                    'scope'                       => $scope,
                    'version'                     => 0,
                    'superseded_at'               => now(),
                ]);
            }

            $onTradeUnit->each(fn (Media $media) => DetachAttachmentFromModel::make()->handle($tradeUnit, $media));
        }

        ProductDevelopmentFile::whereIn('id', collect($previousFiles)->pluck('id'))->update(['superseded_at' => now()]);
    }

    public function storeMedia(ProductDevelopment $productDevelopment, UploadedFile $file, ProductDevelopmentFileScopeEnum $scope, ?ProductDevelopmentItem $item): Media
    {
        $checksum       = md5_file($file->getPathname());
        $tradeUnitScope = $scope->tradeUnitAttachmentScope();

        if ($tradeUnitScope && $item) {
            AttachAttachmentToModel::make()->handle($item->tradeUnit, [
                'attachments' => [$file],
                'scope'       => $tradeUnitScope->value,
            ]);

            return Media::where('group_id', $productDevelopment->group_id)->where('type', 'attachment')->where('checksum', $checksum)->firstOrFail();
        }

        return Media::where('group_id', $productDevelopment->group_id)->where('type', 'attachment')->where('checksum', $checksum)->first()
            ?? StoreMediaFromFile::run(
                $productDevelopment,
                [
                    'path'         => $file->getPathname(),
                    'originalName' => $file->getClientOriginalName(),
                    'extension'    => $file->getClientOriginalExtension(),
                    'checksum'     => $checksum,
                    'scope'        => $scope->value,
                ],
                'attachment',
                'attachment'
            );
    }

    public function rules(): array
    {
        return [
            'files'                       => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*'                     => ['file', 'max:'.self::MAX_KILOBYTES, 'extensions:'.self::ACCEPTED_EXTENSIONS],
            'scope'                       => ['required', Rule::enum(ProductDevelopmentFileScopeEnum::class)->except([ProductDevelopmentFileScopeEnum::DESIGN_IMAGE, ProductDevelopmentFileScopeEnum::SAMPLE_PHOTO, ProductDevelopmentFileScopeEnum::PACKAGING_SAMPLE_PHOTO])],
            'product_development_item_id' => ['nullable', 'integer', Rule::exists('product_development_items', 'id')->where('product_development_id', $this->target()->id)],
            'checklist_item_id'           => ['nullable', 'integer', Rule::exists('product_development_checklist_items', 'id')->where('product_development_id', $this->target()->id)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $scope = ProductDevelopmentFileScopeEnum::tryFrom((string) $this->get('scope'));
        if ($scope?->isPerSku() && !$this->get('product_development_item_id')) {
            $validator->errors()->add('product_development_item_id', __('Choose the SKU this file is for'));
        }
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): array
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle(
            $productDevelopment,
            $request->user(),
            $request->file('files', []),
            ProductDevelopmentFileScopeEnum::from($this->validatedData['scope']),
            ($itemId = Arr::get($this->validatedData, 'product_development_item_id')) ? ProductDevelopmentItem::find($itemId) : null,
            Arr::get($this->validatedData, 'checklist_item_id'),
        );
    }

    public function action(ProductDevelopment $productDevelopment, User $user, array $modelData): array
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        return $this->handle(
            $productDevelopment,
            $user,
            $modelData['files'],
            ProductDevelopmentFileScopeEnum::from($this->validatedData['scope']),
            ($itemId = Arr::get($this->validatedData, 'product_development_item_id')) ? ProductDevelopmentItem::find($itemId) : null,
            Arr::get($this->validatedData, 'checklist_item_id'),
        );
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
