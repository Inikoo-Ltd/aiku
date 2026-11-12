<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementVersionStateEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * One version of the group's Supplier Agreement, the general conditions every supplier accepts. A draft can be
 * changed; once published its text is frozen (here and by a database trigger), and changing it means a new version.
 *
 * @property int $id
 * @property int $group_id
 * @property int $version
 * @property string $title
 * @property string $body
 * @property string|null $change_summary
 * @property bool $requires_reacceptance
 * @property SupplierAgreementVersionStateEnum $state
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int|null $published_by_user_id
 * @property int|null $created_by_user_id
 * @property \Illuminate\Support\Carbon|null $retired_at
 */
class SupplierAgreementVersion extends Model
{
    use InGroup;

    public const array FROZEN_FIELDS = ['group_id', 'version', 'title', 'body', 'change_summary', 'requires_reacceptance', 'published_at'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state'                 => SupplierAgreementVersionStateEnum::class,
            'requires_reacceptance' => 'boolean',
            'published_at'          => 'datetime',
            'retired_at'            => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SupplierAgreementVersion $version) {
            $wasDraft = $version->getOriginal('state') === SupplierAgreementVersionStateEnum::DRAFT;
            if (!$wasDraft && ($version->isDirty(self::FROZEN_FIELDS) || $version->state === SupplierAgreementVersionStateEnum::DRAFT)) {
                throw new LogicException('A published supplier agreement version cannot be edited');
            }
            if ($version->getOriginal('state') === SupplierAgreementVersionStateEnum::RETIRED && $version->state !== SupplierAgreementVersionStateEnum::RETIRED) {
                throw new LogicException('A retired supplier agreement version cannot be published again');
            }
        });
        static::deleting(function (SupplierAgreementVersion $version) {
            if ($version->state !== SupplierAgreementVersionStateEnum::DRAFT) {
                throw new LogicException('A published supplier agreement version cannot be deleted');
            }
        });
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->whereIn('state', [SupplierAgreementVersionStateEnum::PUBLISHED, SupplierAgreementVersionStateEnum::RETIRED]);
    }

    public function bodyHash(): string
    {
        return hash('sha256', $this->body);
    }

    public function bodyHtml(): string
    {
        return Str::markdown($this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(SupplierAgreementAcceptance::class);
    }
}
