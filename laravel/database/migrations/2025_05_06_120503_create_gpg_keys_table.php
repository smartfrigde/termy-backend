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
        Schema::create('gpg_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ssh_connection_id')->constrained('ssh_connections')->onDelete('cascade')->comment('ID połączenia SSH, do którego należy ten klucz GPG');
            $table->text('public_key')->comment('Klucz publiczny GPG');
            $table->text('private_key')->nullable()->comment('Klucz prywatny GPG (opcjonalny)');
            $table->boolean('revoked')->default(false)->comment('Czy klucz został unieważniony');
            $table->timestamp('expires_at')->nullable()->comment('Data wygaśnięcia klucza');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpg_keys');
    }
};
