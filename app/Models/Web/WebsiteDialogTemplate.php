<?php

namespace App\Models\Web;

use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Web\WebsiteDialogTemplate
 *
 * @property int $id
 * @property int $group_id
 * @property string $code
 * @property string $name
 * @property string $component
 * @property int $position
 * @property array<array-key, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Group $group
 * @method static Builder<static>|WebsiteDialogTemplate newModelQuery()
 * @method static Builder<static>|WebsiteDialogTemplate newQuery()
 * @method static Builder<static>|WebsiteDialogTemplate query()
 * @mixin \Eloquent
 */
class WebsiteDialogTemplate extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
