<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Sep 2023 18:45:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\Helpers\Import\UploadStateEnum;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * App\Models\ExcelUpload
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int|null $user_id
 * @property string $model
 * @property string $original_filename
 * @property string $filename
 * @property int $filesize
 * @property string|null $path
 * @property int $number_rows
 * @property int $number_success
 * @property int $number_fails
 * @property string|null $uploaded_at Date the file was finished store/update actions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property int|null $web_user_id
 * @property int|null $customer_id
 * @property string|null $parent_type
 * @property int|null $parent_id
 * @property UploadStateEnum|null $state
 * @property array<array-key, mixed> $data
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read Model|\Eloquent|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\UploadRecord> $records
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read User|null $user
 * @property-read WebUser|null $webUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload query()
 * @mixin \Eloquent
 */
class Upload extends Model implements Auditable
{
    use HasFactory;
    use HasHistory;
    use inShop;

    protected $guarded = [];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $casts = [
        'state'           => UploadStateEnum::class,
        'data'            => 'array',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    public function generateTags(): array
    {
        return [
            'imports'
        ];
    }

    protected array $auditInclude = [
        'model',
        'original_filename',
    ];

    public function getFullPath(): string
    {
        return $this->path.'/'.$this->filename;
    }

    public function records(): HasMany
    {
        return $this->hasMany(UploadRecord::class);
    }

    /**
     * Why rows failed, most frequent first, each with how often it happened and the first rows it hit.
     *
     * @return array<int, array{message: string, count: int, rows: array<int, int>}>
     */
    public function failReasons(int $maxReasons = 5, int $exampleRows = 5): array
    {
        if ($this->number_fails === 0) {
            return [];
        }

        return $this->records()
            ->where('status', UploadRecordStatusEnum::FAILED)
            ->selectRaw('errors::text as reason, count(*) as count, (array_agg(row_number ORDER BY row_number))[1:'.$exampleRows.'] as rows')
            ->groupByRaw('errors::text')
            ->orderByDesc('count')
            ->limit($maxReasons)
            ->toBase()
            ->get()
            ->map(fn (object $reason) => [
                'message' => implode(' ', Arr::flatten(json_decode($reason->reason, true) ?: [__('Unknown error')])),
                'count'   => (int) $reason->count,
                'rows'    => array_map('intval', array_filter(explode(',', trim((string) $reason->rows, '{}')), 'is_numeric')),
            ])
            ->all();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->morphTo();
    }

    public function webUser(): BelongsTo
    {
        return $this->belongsTo(WebUser::class);
    }
}
