<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('version_changes')) {
            return;
        }

        if (Schema::hasColumn('version_changes', 'clean_install')) {
            return;
        }

        Schema::table('version_changes', function (Blueprint $table) {
            $table->boolean('clean_install')->default(false)->after('status');
        });
    }

    public function down(): void
    {
    }
};
