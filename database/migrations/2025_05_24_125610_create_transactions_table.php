<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            
            // Debit Wallet ID (Foreign key linking to the 'wallets' table)
            // Removed unique() constraint to allow a wallet to have multiple debit transactions
            $table->foreignId('dr_wallet_id') 
                  ->nullable()
                  ->constrained('wallets') // Links to the 'wallets' table
                  ->onDelete('restrict'); // Prevents deleting a wallet if transactions exist
            
            // Credit Wallet ID (Foreign key linking to the 'wallets' table)
            // Removed unique() constraint to allow a wallet to have multiple credit transactions
            $table->foreignId('cr_wallet_id') 
                  ->nullable()
                  ->constrained('wallets') // Links to the 'wallets' table
                  ->onDelete('restrict');
                  
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['Debit', 'Credit', 'Deposit', 'Withdrawal', 'Tip']); // Added 'Tip' for completeness
            $table->enum('status', ['Pending', 'Success', 'Failed', 'Cancelled'])->default('Pending');
            $table->string('reference')->unique()->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // The softDeletes migration structure is slightly unusual.
        // It's cleaner to define softDeletes() directly in the Schema::create block.
        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        // Must drop foreign keys before dropping the table if using Schema::dropIfExists
        Schema::dropIfExists('transactions');
    }
};