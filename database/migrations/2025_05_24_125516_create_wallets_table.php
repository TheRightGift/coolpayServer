<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletsTable extends Migration
{
    public function up()
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            
            // CORRECTED LINE: Added unique() constraint
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('tipping_url')->unique();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallets');
    }
};