<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 16:40:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Models\Dispatching;

use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $delivery_note_id
 * @property int|null $model_has_leaflet_id
 * @property LeafletTypeEnum $type
 * @property string $name
 * @property int|null $media_id
 * @property string|null $message
 * @property int $copies
 * @property DeliveryNoteLeafletStateEnum $state
 * @property \Illuminate\Support\Carbon|null $printed_at
 * @property int|null $printed_by_user_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read DeliveryNote $deliveryNote
 * @property-read Media|null $media
 * @property-read ModelHasLeaflet|null $modelHasLeaflet
 * @property-read User|null $printedBy
 * @mixin \Eloquent
 */
class DeliveryNoteLeaflet extends Model
{
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'type'       => LeafletTypeEnum::class,
        'state'      => DeliveryNoteLeafletStateEnum::class,
        'printed_at' => 'datetime',
        'data'       => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function modelHasLeaflet(): BelongsTo
    {
        return $this->belongsTo(ModelHasLeaflet::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }

    /**
     * What this insert will print: its own artwork, or its own text. Never the customer's
     * current preference — that can change after checkout, and this row has to keep a record
     * of what the order was actually sold with. Artwork missing here is copied over by an
     * explicit action instead, which leaves the copy recorded on this row.
     */
    public function isPrintable(): bool
    {
        return $this->media_id !== null || filled($this->message);
    }

    /**
     * Inserts that have something to put on paper: their own artwork, or their own text.
     * The counterpart of isPrintable() for queries, so a guard and a print run always
     * agree on which rows count.
     */
    public function scopePrintable(Builder $query): Builder
    {
        return $query->where(
            fn (Builder $printable) => $printable->whereNotNull('media_id')->orWhereNotNull('message')
        );
    }

    /**
     * The customer's artwork that this insert could be given, if any exists.
     *
     * First choice is the preference row this insert was created from. When the order was
     * placed before the customer had uploaded anything for this packaging, that row does not
     * exist, so the closest current match is offered instead: same shop, same insert type,
     * preferring the family of packaging being shipped.
     */
    public function preferenceMediaCandidate(): ?ModelHasLeaflet
    {
        if ($this->modelHasLeaflet?->media_id !== null) {
            return $this->modelHasLeaflet;
        }

        $deliveryNote = $this->deliveryNote;

        if (!$deliveryNote) {
            return null;
        }

        $query = ModelHasLeaflet::where('model_type', 'Customer')
            ->where('model_id', $deliveryNote->customer_id)
            ->where('shop_id', $this->shop_id)
            ->whereNotNull('media_id')
            ->whereHas('leaflet', fn ($leaflet) => $leaflet->where('type', $this->type));

        $familyCode = $deliveryNote->packaging?->family_code;

        $sameFamily = $familyCode
            ? (clone $query)->whereHas('packaging', fn ($packaging) => $packaging->where('family_code', $familyCode))->first()
            : null;

        return $sameFamily ?? $query->first();
    }

    /**
     * Whether the customer has since uploaded artwork this row could be given.
     */
    public function canPullMediaFromPreference(): bool
    {
        return $this->media_id === null
            && blank($this->message)
            && $this->preferenceMediaCandidate() !== null;
    }
}
