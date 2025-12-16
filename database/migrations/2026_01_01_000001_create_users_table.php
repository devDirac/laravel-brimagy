<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->unique();
            $table->string('usuario');
            $table->string('nombre');
            $table->string('correo', 150)->unique();
            $table->string('telefono')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('tipo_usuario');
            $table->string('foto')->nullable();
            $table->integer('activo');
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('tipo_usuario')
                ->references('id')
                ->on('tipo_usuarios');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('correo', 150)->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });



    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
