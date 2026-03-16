<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        if (!Schema::hasTable('overtime_types')) {
            Schema::create('overtime_types', function (Blueprint $table) {
                $table->id();
                $table = $this->groupOrgRelationship($table);

                $table->string('code', 32);
                $table->string('name', 128);
                $table->string('color', 128)->nullable();
                $table->text('description')->nullable();

                $table->string('category', 32)->default('overtime');
                $table->string('compensation_type', 32)->default('paid');
                $table->decimal('multiplier', 5, 2)->nullable();

                $table->jsonb('settings')->nullable();

                $table->boolean('is_active')->default(true);

                $table->timestampsTz();

                $table->unique(['organisation_id', 'code']);
                $table->index(['organisation_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_types');
    }
};
