<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Identificação
            $table->string('name'); // Razão Social / Nome principal
            $table->string('trade_name')->nullable(); // Nome Fantasia
            $table->string('document')->nullable(); // CNPJ ou CPF
            $table->string('state_registration')->nullable(); // Inscrição Estadual (importante para NFe)

            // Contato
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_person')->nullable(); // Nome da pessoa de contato

            // Endereço (útil para emissão e dados da NFe)
            $table->string('zip_code', 9)->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();

            $table->boolean('active')->default(true);
            $table->text('notes')->nullable(); // Observações gerais

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};