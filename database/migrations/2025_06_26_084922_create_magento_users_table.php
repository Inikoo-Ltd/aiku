<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('magento_users', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('slug')->unique()->collation('und_ns');
            $table->boolean('status')->default(true)->index();
            $table->string('name')->index();
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('magento_users');
    }
};
