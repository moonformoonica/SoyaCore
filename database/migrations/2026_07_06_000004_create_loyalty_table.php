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
        Schema::create('loyalty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customer'); // 1:1 dengan customer
            $table->unsignedInteger('stempel')->default(0); // reset tiap 10
            $table->unsignedInteger('total_gratis')->default(0); // akumulasi item gratis
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty');
    }
};
