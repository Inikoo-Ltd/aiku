<?php

use App\Stubs\Migrations\HasAssetCodeDescription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasAssetCodeDescription;

    public function up(): void
    {
        Schema::create('master_collections', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->boolean('status')->index()->default(true);
            $table->jsonb('data');
            $table->timestampstz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_collections');
    }
};
