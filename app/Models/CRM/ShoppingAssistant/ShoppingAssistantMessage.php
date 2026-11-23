<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM\ShoppingAssistant;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantAnswerKindEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\Web\Webpage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\Helpers\Media;

/**
 * @property int $id
 * @property int $shopping_assistant_conversation_id
 * @property ShoppingAssistantMessageRoleEnum $role
 * @property string|null $body
 * @property array<int, array{name: string, arguments: mixed, product_ids: array<int>, error: ?string, ms: int}>|null $tool_calls
 * @property array<int, string>|null $follow_ups
 * @property array<int, string>|null $flags
 * @property string|null $model
 * @property int $model_calls
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property string $cost
 * @property int|null $latency_ms
 * @property string|null $feedback
 * @property \Illuminate\Support\Carbon|null $feedback_at
 * @property ShoppingAssistantAnswerKindEnum|null $answer_kind
 * @property string|null $source_kind
 * @property array<int, array{id: int, kind: string, source_type: string, source_id: ?string, title: string, url: ?string}>|null $sources
 * @property ShoppingAssistantHandOverReasonEnum|null $hand_over_offer
 * @property string|null $staff_rating
 * @property int|null $staff_rated_by_user_id
 * @property \Illuminate\Support\Carbon|null $staff_rated_at
 * @property int|null $webpage_id
 * @property array<int, int>|null $trail_webpage_ids
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ShoppingAssistantConversation $conversation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShoppingAssistantEvent> $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Media> $media
 * @mixin \Eloquent
 */
class ShoppingAssistantMessage extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const string IMAGES = 'shopping_assistant_images';

    public const int MAX_IMAGES = 3;

    public const int MODEL_IMAGE_SIDE = 1568;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role'       => ShoppingAssistantMessageRoleEnum::class,
            'tool_calls' => 'array',
            'follow_ups' => 'array',
            'flags'      => 'array',
            'cost'       => 'decimal:6',
            'answer_kind'     => ShoppingAssistantAnswerKindEnum::class,
            'sources'         => 'array',
            'hand_over_offer' => ShoppingAssistantHandOverReasonEnum::class,
            'staff_rated_at'  => 'datetime',
            'feedback_at'     => 'datetime',
            'trail_webpage_ids' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ShoppingAssistantConversation::class, 'shopping_assistant_conversation_id');
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShoppingAssistantEvent::class);
    }

    /**
     * @return Collection<int, Media>
     */
    public function images(): Collection
    {
        return $this->media->where('collection_name', self::IMAGES)->sortBy('order_column')->values();
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    public function imageLinks(): array
    {
        return $this->images()->map(fn (Media $image) => ['name' => $image->name, 'url' => GetPictureSources::run($image->getImage()->resize(0, 0))['original']])->all();
    }

    /**
     * Copies of the images as uploads, for the chat they are passed on to; the caller deletes the files.
     *
     * @return array<int, UploadedFile>
     */
    public function imagesAsUploads(): array
    {
        return $this->images()->map(fn (Media $image) => new UploadedFile(self::localCopyOf($image), $image->name, $image->mime_type, null, true))->all();
    }

    /**
     * The images as data URIs no longer than MODEL_IMAGE_SIDE on either side, for the model.
     *
     * @return array<int, string>
     */
    public function imagesForModel(): array
    {
        return $this->images()->map(function (Media $image) {
            $original = self::localCopyOf($image);
            $resized  = $original.'.jpg';
            Image::load($original)->fit(Fit::Max, self::MODEL_IMAGE_SIDE, self::MODEL_IMAGE_SIDE)->quality(85)->save($resized);
            $dataUri = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($resized));
            @unlink($original);
            @unlink($resized);

            return $dataUri;
        })->all();
    }

    /**
     * A temporary file with the image; the caller deletes it.
     */
    public static function localCopyOf(Media $image): string
    {
        $path = tempnam(sys_get_temp_dir(), 'merlin');
        $stream = $image->stream();
        file_put_contents($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $path;
    }
}
