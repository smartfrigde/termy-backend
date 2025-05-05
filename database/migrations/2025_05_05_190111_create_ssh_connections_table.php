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
        Schema::create('ssh_connections', function (Blueprint $table) {
            $table->id();
            $table->string('hostname', 512)->comment('The hostname or IP address of the SSH server');
            $table->integer('port')->default(22)->comment('The port number for SSH connection');
            $table->string('login', 255)->comment('The username for SSH authentication');
            $table->longText("password")->nullable()->comment('The password for SSH authentication');
            $table->longText('private_key')->nullable()->comment('The private key for SSH authentication');
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade')->comment('The ID of the team that owns this SSH connection');
            $table->boolean('revoked')->default(false)->comment('Indicates if the SSH connection has been revoked');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ssh_connections');
    }
};
