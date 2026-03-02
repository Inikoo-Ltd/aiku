<?php

namespace App\Models;

use App\Actions\Dropshipping\Allegro\Traits\WithAllegroApiServices;
use Illuminate\Database\Eloquent\Model;

class AllegroUser extends Model
{
    use WithAllegroApiServices;
}
