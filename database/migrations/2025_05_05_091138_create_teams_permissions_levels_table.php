<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams_permissions_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('The name of the permission level');
            $table->timestamps();
        });

        DB::table('teams_permissions_levels')->insert([
            ['id' => 2, 'name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 1, 'name' => 'member', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'owner', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams_permissions_levels');
    }
};
