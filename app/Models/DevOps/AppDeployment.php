<?php

namespace App\Models\DevOps;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $semantic_version
 * @property string|null $commit_hash
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $change_log
 * @property array<array-key, mixed>|null $committers
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppDeployment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppDeployment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppDeployment query()
 * @mixin \Eloquent
 */
class AppDeployment extends Model
{
    use HasFactory;

    protected $fillable = ['commit_hash', 'semantic_version', 'change_log', 'committers'];

    protected function casts(): array
    {
        return [
            'committers' => 'array',
        ];
    }
}
