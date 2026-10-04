<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log of price changes: who changed which price, when, and from
     * what to what. Rows are only ever added.
     *
     * price_setup_id has no foreign key on purpose: the log must survive the
     * deletion of the price_setups row it describes.
     */
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('price_setup_id')->nullable()->index();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('price_category');
            $table->string('action', 10); // created | updated | deleted
            $table->decimal('old_amount', 15, 2)->nullable();
            $table->decimal('new_amount', 15, 2)->nullable();
            $table->date('old_effective_date')->nullable();
            $table->date('new_effective_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
