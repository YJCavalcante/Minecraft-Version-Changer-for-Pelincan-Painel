<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('version_changes')) {
            return;
        }

        Schema::create('version_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->index();
            $table->string('software');              // e.g. PAPER, VANILLA, FABRIC
            $table->string('minecraft_version');     // e.g. 1.21.4
            $table->unsignedInteger('build_number')->nullable();
            $table->string('build_name')->nullable();// e.g. "#232" or "0.19.5"
            $table->text('jar_url');
            $table->unsignedBigInteger('jar_size')->nullable();
            $table->string('status')->default('pending'); // pending|changing|done|failed
            $table->text('error_message')->nullable();
            $table->longText('log')->nullable();
            $table->timestamps();

            $table->foreign('server_id')
                ->references('id')
                ->on('servers')
                ->cascadeOnDelete();

            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        // Intentionally left empty to prevent data loss on reinstall.
    }
};
