<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up()
    {
        Schema::create('role_blueprints', function (Blueprint $table) {
            $table->id();

            $this->groupOrgRelationship($table);
            $table->string('code')->unique()->index();
            $table->string('name');
            $table->string('type');
            $table->string('org_type');
            $table->string('scope');

            $table->timestampsTz();
        });
    }

    public function down()
    {
        Schema::dropIfExists('role_blueprints');
    }
};
