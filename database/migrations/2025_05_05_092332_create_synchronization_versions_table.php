<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('synchronization_versions', function (Blueprint $table) {
            $table->id();
            $table->integer('version')->comment('The version number of the synchronization');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('The ID of the user who initiated the synchronization');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synchronization_versions');
    }
};
