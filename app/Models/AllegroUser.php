<?php

namespace App\Models;

use App\Actions\Dropshipping\Allegro\Traits\WithAllegroApiServices;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegroUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegroUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegroUser query()
 * @mixin \Eloquent
 */
class AllegroUser extends Model
{
    use WithAllegroApiServices;
}
