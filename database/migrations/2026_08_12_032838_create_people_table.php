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

            // 🟢 Relacionamento com o usuário dono do registro
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Papel da pessoa no sistema
            $table->enum('category', ['client', 'supplier'])->default('client');

            // Tipo de documento: pessoa física ou jurídica
            $table->enum('type', ['individual', 'company']);

            // Identificação
            $table->string('name'); // nome ou razão social
            $table->string('trade_name')->nullable(); // nome fantasia (jurídica)
            $table->string('document'); // CPF ou CNPJ
            $table->string('state_registration')->nullable(); // Inscrição Estadual (jurídica)
            $table->enum('gender', ['male', 'female', 'other'])->nullable(); // física
            $table->date('birth_date')->nullable(); // física
            $table->string('contact_person')->nullable(); // pessoa de contato (jurídica)

            // Contato e endereço
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
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

            // 🟢 CORREÇÃO: Garante que o CPF/CNPJ só não pode se repetir DENTRO da conta do MESMO usuário
            $table->unique(['user_id', 'document']);
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