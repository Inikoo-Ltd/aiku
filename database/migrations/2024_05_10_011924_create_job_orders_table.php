<?php

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up()
    {
        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->nullable()->unique()->collation('und_ns');
            $table->string('reference')->nullable()->index()->collation('und_ci');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->string('state')->index()->default(JobOrderStateEnum::IN_PROCESS->value);
            foreach (JobOrderStateEnum::cases() as $state) {
                $table->dateTimeTz("{$state->snake()}_at")->nullable();
            }
            $table->dateTimeTz('date')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
            $table = $this->softDeletes($table);
        });
    }
    // pls review

    public function down()
    {
        Schema::dropIfExists('job_orders');
    }
};
