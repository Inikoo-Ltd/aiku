<?php

namespace App\Models\DevOps;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $commit_hash
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppDeployment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppDeployment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppDeployment query()
 * @mixin \Eloquent
 */
class AppDeployment extends Model
{
    use HasFactory;

    protected $fillable = ['commit_hash'];
}
