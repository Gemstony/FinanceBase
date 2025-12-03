<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Shop;
use App\Models\SubShop;
use App\Models\Item;
use App\Models\SalesOrders;
use App\Models\SalesOrdersItems;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        // Create a demo user if not exists
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo Shop Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create a shop for the user if not exists
        $shop = Shop::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Demo Shop',
                'description' => 'A demo shop for testing KPI dashboard',
                'status' => 'active',
                'max_subshops' => 5,
            ]
        );

        // Create subshops
        $subshops = [];
        $subshopNames = ['Main Store', 'Branch A', 'Branch B'];

        foreach ($subshopNames as $name) {
            $subshop = SubShop::firstOrCreate(
                ['name' => $name, 'shop_id' => $shop->id],
                [
                    'location' => 'Demo Location',
                    'description' => "Demo subshop: {$name}",
                ]
            );
            $subshops[] = $subshop;
        }

        // Create payment methods if not exist
        $paymentMethod = PaymentMethod::firstOrCreate(
            ['name' => 'Cash'],
            ['is_active' => true]
        );

        // Create sample items for each subshop
        $itemNames = [
            ['name' => 'Apple', 'price' => 2000, 'min_qty' => 10],
            ['name' => 'Banana', 'price' => 1500, 'min_qty' => 15],
            ['name' => 'Orange', 'price' => 2500, 'min_qty' => 8],
            ['name' => 'Bread', 'price' => 3000, 'min_qty' => 5],
            ['name' => 'Milk', 'price' => 4000, 'min_qty' => 12],
            ['name' => 'Rice', 'price' => 8000, 'min_qty' => 20],
            ['name' => 'Sugar', 'price' => 5000, 'min_qty' => 10],
            ['name' => 'Cooking Oil', 'price' => 12000, 'min_qty' => 6],
        ];

        $items = [];
        foreach ($subshops as $subshop) {
            foreach ($itemNames as $itemData) {
                $item = Item::firstOrCreate(
                    [
                        'name' => $itemData['name'],
                        'subshop_id' => $subshop->id
                    ],
                    [
                        'price' => $itemData['price'],
                        'quantity' => rand($itemData['min_qty'], $itemData['min_qty'] * 3),
                        'min_quantity' => $itemData['min_qty'],
                        'unit' => 'pcs',
                        'category' => 'Demo Category',
                        'description' => "Demo item: {$itemData['name']}",
                    ]
                );
                $items[] = $item;
            }
        }

        // Create sample sales orders and transactions for the last 30 days
        for ($i = 0; $i < 30; $i++) {
            $date = now()->subDays($i);

            // Create 3-8 sales per day
            $salesPerDay = rand(3, 8);

            for ($j = 0; $j < $salesPerDay; $j++) {
                $subshop = $subshops[array_rand($subshops)];

                // Select 1-5 random items for this sale
                $saleItems = array_rand(array_flip($items), rand(1, 5));
                if (!is_array($saleItems)) {
                    $saleItems = [$saleItems];
                }

                $subtotal = 0;
                $orderItems = [];

                foreach ($saleItems as $item) {
                    $quantity = rand(1, 5);
                    $itemTotal = $item->price * $quantity;
                    $subtotal += $itemTotal;

                    $orderItems[] = [
                        'item_id' => $item->id,
                        'quantity' => $quantity,
                        'unit_price' => $item->price,
                        'total_price' => $itemTotal,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ];
                }

                // Calculate totals
                $vatRate = 0.18; // 18% VAT
                $vatTotal = $subtotal * $vatRate;
                $grandTotal = $subtotal + $vatTotal;

                // Create sales order
                $salesOrder = SalesOrders::create([
                    'subshop_id' => $subshop->id,
                    'customer_id' => null, // Walk-in customer
                    'created_by' => $user->id,
                    'order_no' => 'ORD-' . $date->format('Ymd') . '-' . str_pad($j + 1, 3, '0', STR_PAD_LEFT),
                    'subtotal' => $subtotal,
                    'vat_total' => $vatTotal,
                    'discount_percent' => 0,
                    'discount_cash' => 0,
                    'discount_total' => 0,
                    'grand_total' => $grandTotal,
                    'payment_method' => 'Cash',
                    'amount_paid' => $grandTotal,
                    'change_amount' => 0,
                    'status' => 'completed',
                    'notes' => 'Demo sale',
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                // Create sales order items
                foreach ($orderItems as $orderItem) {
                    $orderItem['sales_order_id'] = $salesOrder->id;
                    SalesOrdersItems::create($orderItem);
                }

                // Create transaction record
                Transaction::create([
                    'customer_id' => null,
                    'order_id' => $salesOrder->id,
                    'created_by' => $user->id,
                    'transaction_type' => 'sale',
                    'payment_method' => 'Cash',
                    'total_amount' => $grandTotal,
                    'transaction_date' => $date,
                    'reference_number' => 'TXN-' . $salesOrder->order_no,
                    'notes' => 'Demo transaction',
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                // Update item quantities (reduce stock)
                foreach ($saleItems as $item) {
                    $soldQuantity = rand(1, 3);
                    $item->quantity = max(0, $item->quantity - $soldQuantity);
                    $item->save();
                }
            }
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- 1 Demo User');
        $this->command->info('- 1 Demo Shop');
        $this->command->info('- 3 Subshops');
        $this->command->info('- 24 Items (8 per subshop)');
        $this->command->info('- ~90-240 Sales Orders (last 30 days)');
        $this->command->info('- ~90-240 Transactions');
        $this->command->info('');
        $this->command->info('Login credentials:');
        $this->command->info('Email: demo@example.com');
        $this->command->info('Password: password');
    }
}
