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
        Schema::create('picking_issues', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->string('reference');
            $table->string('model_type')->comment('DeliveryNote, DeliveryNoteItem');
            $table->unsignedInteger('model_id');

            $table->unsignedInteger('issuer_user_id')->nullable()->index();
            $table->foreign('issuer_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('resolver_user_id')->nullable()->index();
            $table->foreign('resolver_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unsignedInteger('picking_id')->nullable()->index();
            $table->foreign('picking_id')->references('id')->on('pickings')->nullOnDelete();
            $table->unsignedInteger('org_stock_id')->nullable()->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->nullOnDelete();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();

            $table->string('delivery_note_issue')->nullable();
            $table->string('delivery_note_item_issue')->nullable();
            $table->boolean('is_solved')->default(false);

            $table->index(['model_type', 'model_id']);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('picking_issues');
    }
};
