<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = [
            'number_return_delivery_notes',
            'number_return_delivery_notes_state_received',
            'number_return_delivery_notes_state_returning',
            'number_return_delivery_notes_state_returned',
            'number_return_delivery_notes_state_done',
            'number_return_delivery_notes_state_cancelled',
        ];

        Schema::table('customer_stats', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->unsignedInteger($column)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {   
        $columns = [
            'number_return_delivery_notes',
            'number_return_delivery_notes_state_received',
            'number_return_delivery_notes_state_returning',
            'number_return_delivery_notes_state_returned',
            'number_return_delivery_notes_state_done',
            'number_return_delivery_notes_state_cancelled',
        ];

        Schema::table('customer_stats', function (Blueprint $table) use ($columns){
            $table->dropColumn($columns);
        });
    }
};
