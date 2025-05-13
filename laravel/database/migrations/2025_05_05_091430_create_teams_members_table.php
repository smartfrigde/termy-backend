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
        Schema::create('teams_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('The ID of the user who is a member of the team');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade')->comment('The ID of the team to which the user belongs');
            $table->foreignId('permission_level_id')->constrained('teams_permissions_levels')->onDelete('cascade')->comment('The ID of the permission level assigned to the user in the team')->default(1);
            $table->boolean("revoked")->default(false)->comment('Indicates if the user\'s membership has been revoked');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams_members');
    }
};
