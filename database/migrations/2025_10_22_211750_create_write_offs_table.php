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
        Schema::create('write_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subshop_id')->constrained('sub_shops')->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->text('reason');
            $table->date('write_off_date');
            $table->text('description')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_value', 10, 2);
            $table->string('status');
            $table->timestamps();
        });
        // $this->seedWriteOffsTable();
    }

    private function seedWriteOffsTable(): void
    {
        DB::table('write_offs')->insert([
            [
                'subshop_id' => 1,
                'item_id' => 1,
                'quantity' => 2,
                'reason' => 'Damaged goods',
                'write_off_date' => now(),
                'description' => 'Two items were found damaged during inspection.',
                'unit_price' => 100.00,
                'total_value' => 200.00,
            ],
            [
                'subshop_id' => 1,
                'item_id' => 2,
                'quantity' => 1,
                'reason' => 'Expired',
                'write_off_date' => now(),
                'description' => 'One item was found expired on the shelf.',
                'unit_price' => 50.00,
                'total_value' => 50.00,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('write_offs');
    }
};
