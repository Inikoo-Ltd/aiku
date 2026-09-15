<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('task_production_stats');
        Schema::dropIfExists('task_stats');
        Schema::dropIfExists('users_has_tasks');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_types');
    }

    public function down(): void
    {
    }
};
