<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        if (!Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table = $this->groupOrgRelationship($table);

                $table->string('code', 32);
                $table->string('name', 128);
                $table->string('color', 128)->nullable();
                $table->text('description')->nullable();

                $table->string('category', 32)->default('personal');
                $table->boolean('requires_approval')->default(true);
                $table->decimal('max_days_per_year', 5, 2)->nullable();

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
        Schema::dropIfExists('leave_types');
    }
};
