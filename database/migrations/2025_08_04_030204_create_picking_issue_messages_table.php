<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('picking_issue_messages', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('picking_issue_id')->index();
            $table->foreign('picking_issue_id')->references('id')->on('picking_issues');
            
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');

            $table->string('type')->comment('Issuer|Resolver');
            $table->text('message');

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('picking_issue_messages');
    }
};
