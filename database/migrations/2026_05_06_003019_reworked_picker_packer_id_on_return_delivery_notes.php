<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            foreach (['picker_id', 'picker_user_id', 'packer_id', 'packer_user_id'] as $column) {
                $table->dropForeign([$column]);
            }
            $table->dropColumn([
                'picker_id',
                'picker_user_id',
                'packer_id',
                'packer_user_id'
            ]);

            $table->unsignedSmallInteger('handler_id')->nullable()->index()->comment('Main handler');
            $table->foreign('handler_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('handler_user_id')->nullable()->index();
            $table->foreign('handler_user_id')->references('id')->on('users');
        });
    }


    public function down(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->dropForeign(['handler_id']);
            $table->dropForeign(['handler_user_id']);
            $table->dropColumn([
                'handler_id',
                'handler_user_id',
            ]);  

            $table->unsignedSmallInteger('picker_id')->nullable()->index()->comment('Main picker');
            $table->foreign('picker_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('picker_user_id')->nullable()->index();
            $table->foreign('picker_user_id')->references('id')->on('users');

            $table->unsignedSmallInteger('packer_id')->nullable()->index()->comment('Main packer');
            $table->foreign('packer_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('packer_user_id')->nullable()->index();
            $table->foreign('packer_user_id')->references('id')->on('users');
        });
    }
};
