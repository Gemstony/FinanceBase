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
        Schema::table('customers', function (Blueprint $table) {
            // Check if shop_id column doesn't exist before adding
            if (!Schema::hasColumn('customers', 'shop_id')) {
                // Add shop_id foreign key after subshop_id (nullable initially for existing data)
                $table->foreignId('shop_id')->after('subshop_id')->nullable()->constrained('shops')->onDelete('cascade');
            }
            
            // Check if customer_code column doesn't exist before adding
            if (!Schema::hasColumn('customers', 'customer_code')) {
                // Add customer_code after shop_id (nullable initially for existing data)
                $table->string('customer_code')->after('shop_id')->nullable()->unique();
            }
        });
        
        // Update existing customers to populate shop_id based on subshop relationship
        DB::statement('UPDATE customers c INNER JOIN sub_shops ss ON c.subshop_id = ss.id SET c.shop_id = ss.shop_id WHERE c.shop_id IS NULL');
        
        // Generate customer_code for existing customers
        $customers = DB::table('customers')->whereNull('customer_code')->get();
        foreach ($customers as $customer) {
            $shop = DB::table('shops')->where('id', $customer->shop_id)->first();
            if ($shop) {
                $registrationNumber = $shop->registration_number;
                $yearMonth = now()->format('ym');
                $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $randomString = '';
                for ($i = 0; $i < 4; $i++) {
                    $randomString .= $characters[random_int(0, strlen($characters) - 1)];
                }
                $customerCode = "{$registrationNumber}-{$yearMonth}-{$randomString}";
                
                // Check if code already exists, regenerate if it does
                while (DB::table('customers')->where('customer_code', $customerCode)->exists()) {
                    $randomString = '';
                    for ($i = 0; $i < 4; $i++) {
                        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
                    }
                    $customerCode = "{$registrationNumber}-{$yearMonth}-{$randomString}";
                }
                
                DB::table('customers')->where('id', $customer->id)->update(['customer_code' => $customerCode]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Check if foreign key exists before dropping
            if (Schema::hasColumn('customers', 'shop_id')) {
                $table->dropForeign(['shop_id']);
            }
            
            // Drop columns if they exist
            $columnsToDrop = [];
            if (Schema::hasColumn('customers', 'shop_id')) {
                $columnsToDrop[] = 'shop_id';
            }
            if (Schema::hasColumn('customers', 'customer_code')) {
                $columnsToDrop[] = 'customer_code';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
