<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('leave_concurrency_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rule_type')->default(LeaveConcurrencyRuleTypeEnum::QUOTA->value);
            $table->integer('limit')->nullable()->default(1);
            $table->integer('max_overlap_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_concurrency_rules');
    }
};
