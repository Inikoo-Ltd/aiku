<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Models\Helpers\Ticket;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTicketUpload
{
    use AsAction;

    public const string DIRECTORY = 'ticket-uploads';

    private static function disk(): Filesystem
    {
        return Storage::disk(config('media-library.disk_name'));
    }

    public static function path(int $userId, string $uploadId): string
    {
        return self::DIRECTORY.'/'.$userId.'/'.$uploadId;
    }

    /**
     * @return UploadedFile|null the file uploaded through a signed link, if it is still there
     */
    public static function find(int $userId, string $uploadId): ?UploadedFile
    {
        if (!Str::isUlid($uploadId)) {
            return null;
        }
        $files = self::disk()->files(self::path($userId, $uploadId));
        if ($files === []) {
            return null;
        }
        $path = self::disk()->path($files[0]);

        return new UploadedFile($path, basename($path), mime_content_type($path) ?: null, null, true);
    }

    public static function forget(int $userId, string $uploadId): void
    {
        self::disk()->deleteDirectory(self::path($userId, $uploadId));
    }

    public static function pruneAbandoned(): void
    {
        $disk = self::disk();
        foreach ($disk->directories(self::DIRECTORY) as $userDirectory) {
            foreach ($disk->directories($userDirectory) as $uploadDirectory) {
                $files = $disk->files($uploadDirectory);
                if ($files === [] || $disk->lastModified($files[0]) < now()->subDay()->getTimestamp()) {
                    $disk->deleteDirectory($uploadDirectory);
                }
            }
        }
    }

    public function getValidationFailure(Validator $validator): never
    {
        throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
    }

    public function rules(): array
    {
        return ['file' => ['required', ...Ticket::ticketFileRules()]];
    }

    public function asController(int $user, string $upload, ActionRequest $request): JsonResponse
    {
        if (!Str::isUlid($upload) || self::find($user, $upload)) {
            abort(409, 'This upload link has already been used.');
        }
        $file = $request->file('file');
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()) ?: 'file';
        $file->storeAs(self::path($user, $upload), $name, config('media-library.disk_name'));

        return response()->json(['upload_id' => $upload, 'name' => $name, 'size' => $file->getSize()]);
    }
}
