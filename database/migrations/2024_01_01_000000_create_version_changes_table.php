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
            $table->string('software');
            $table->string('minecraft_version');
            $table->unsignedInteger('build_number')->nullable();
            $table->string('build_name')->nullable();
            $table->text('jar_url');
            $table->unsignedBigInteger('jar_size')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('clean_install')->default(false);
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
    }
};
