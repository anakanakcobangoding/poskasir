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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->enum('type', ['DLV', 'DI', 'TA']);
            $table->enum('status', ['new', 'preparing', 'ready', 'completed', 'cancelled'])->default('new');
            $table->string('customer_name')->nullable();
            $table->string('table_number')->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->enum('payment_method', ['cash', 'qris'])->nullable();
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->decimal('change', 12, 2)->nullable();
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
