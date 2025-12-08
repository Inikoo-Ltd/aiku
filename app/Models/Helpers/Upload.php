<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Sep 2023 18:45:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\UploadRecord> $records
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read User|null $user
 * @property-read WebUser|null $webUser
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Upload query()
 *
 * @mixin \Eloquent
 */
class Upload extends Model implements Auditable
{
    use HasFactory;
    use HasHistory;
    use inShop;

    protected $guarded = [];

    protected $casts = [
        'fetched_at' => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    public function generateTags(): array
    {
        return [
            'imports',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function webUser(): BelongsTo
    {
        return $this->belongsTo(WebUser::class);
    }
}
