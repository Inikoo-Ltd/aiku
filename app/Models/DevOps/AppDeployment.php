<?php

namespace App\Models\DevOps;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppDeployment extends Model
{
    use HasFactory;

    protected $fillable = ['commit_hash'];
}
