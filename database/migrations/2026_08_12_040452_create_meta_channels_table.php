<?php

use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('meta_channels', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code')->unique();
            $table->string('name');
            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_channels');
    }
};
