<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->unsignedBigInteger('wallet_id'); // receiver wallet
            $table->unsignedBigInteger('user_id');   // creator
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('memo')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active'); // active|expired|revoked
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
