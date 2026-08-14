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
        Schema::create('people', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Papel da pessoa no sistema
            $table->enum('category', ['client', 'supplier'])->default('client');

            // Tipo de documento: pessoa física ou jurídica
            $table->enum('type', ['individual', 'company']);

            // Identificação
            $table->string('name'); // nome ou razão social
            $table->string('trade_name')->nullable(); // nome fantasia (jurídica)
            $table->string('document'); // CPF ou CNPJ — unicidade é definida por usuário na migration seguinte 🔴 AQUI
            $table->string('state_registration')->nullable(); // Inscrição Estadual (jurídica)
            $table->enum('gender', ['male', 'female', 'other'])->nullable(); // física
            $table->date('birth_date')->nullable(); // física
            $table->string('contact_person')->nullable(); // pessoa de contato (jurídica)

            // Contato e endereço
            $table->string('phone')->nullable(); // 🔴 AQUI: opcional, pessoa física pode não ter
            $table->string('email')->nullable(); // 🔴 AQUI: opcional, pessoa física pode não ter
            $table->string('zip_code')->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('address')->nullable(); // endereço livre (cadastro simples de cliente)

            // Status
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};