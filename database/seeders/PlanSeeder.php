<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free Trial',
                'slug' => 'free-trial',
                'description' => '14-day free trial to explore DukaBase features',
                'price' => 0.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'features' => [
                    'Up to 2 subshops',
                    'Basic inventory management',
                    'Sales and purchase tracking',
                    'Basic reporting',
                    'Email support'
                ],
                'limits' => [
                    'max_subshops' => 2,
                    'max_users' => 3,
                    'max_items' => 500,
                    'storage_gb' => 1
                ],
                'is_popular' => false,
                'sort_order' => 0
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small businesses just getting started',
                'price' => 29.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'features' => [
                    'Up to 3 subshops',
                    'Basic inventory management',
                    'Sales and purchase tracking',
                    'Basic reporting',
                    'Email support'
                ],
                'limits' => [
                    'max_subshops' => 3,
                    'max_users' => 5,
                    'max_items' => 1000,
                    'storage_gb' => 5
                ],
                'is_popular' => false,
                'sort_order' => 1
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing businesses with multiple locations',
                'price' => 79.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'features' => [
                    'Up to 10 subshops',
                    'Advanced inventory management',
                    'Point of sale system',
                    'Advanced reporting & analytics',
                    'Multi-user management',
                    'API access',
                    'Priority email support'
                ],
                'limits' => [
                    'max_subshops' => 10,
                    'max_users' => 25,
                    'max_items' => 10000,
                    'storage_gb' => 25
                ],
                'is_popular' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Complete solution for large enterprises',
                'price' => 199.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'features' => [
                    'Unlimited subshops',
                    'Full inventory management',
                    'Advanced POS with integrations',
                    'Real-time analytics & dashboards',
                    'Advanced user management',
                    'Full API access',
                    'White-label options',
                    'Dedicated support manager',
                    'Custom integrations'
                ],
                'limits' => [
                    'max_subshops' => null, // unlimited
                    'max_users' => null, // unlimited
                    'max_items' => null, // unlimited
                    'storage_gb' => 100
                ],
                'is_popular' => false,
                'sort_order' => 3
            ],
            [
                'name' => 'Professional Yearly',
                'slug' => 'professional-yearly',
                'description' => 'Professional plan billed annually (save 20%)',
                'price' => 767.88, // 79.99 * 12 * 0.8
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'status' => 'active',
                'features' => [
                    'Up to 10 subshops',
                    'Advanced inventory management',
                    'Point of sale system',
                    'Advanced reporting & analytics',
                    'Multi-user management',
                    'API access',
                    'Priority email support',
                    '20% annual discount'
                ],
                'limits' => [
                    'max_subshops' => 10,
                    'max_users' => 25,
                    'max_items' => 10000,
                    'storage_gb' => 25
                ],
                'is_popular' => false,
                'sort_order' => 4
            ],
            [
                'name' => 'Starter 3 Months',
                'slug' => 'starter-3-months',
                'description' => 'Starter plan for 3 months (save 10%)',
                'price' => 80.97, // 29.99 * 3 * 0.9
                'currency' => 'USD',
                'billing_cycle' => '3_months',
                'status' => 'active',
                'features' => [
                    'Up to 3 subshops',
                    'Basic inventory management',
                    'Sales and purchase tracking',
                    'Basic reporting',
                    'Email support',
                    '10% quarterly discount'
                ],
                'limits' => [
                    'max_subshops' => 3,
                    'max_users' => 5,
                    'max_items' => 1000,
                    'storage_gb' => 5
                ],
                'is_popular' => false,
                'sort_order' => 5
            ],
            [
                'name' => 'Professional 6 Months',
                'slug' => 'professional-6-months',
                'description' => 'Professional plan for 6 months (save 15%)',
                'price' => 407.91, // 79.99 * 6 * 0.85
                'currency' => 'USD',
                'billing_cycle' => '6_months',
                'status' => 'active',
                'features' => [
                    'Up to 10 subshops',
                    'Advanced inventory management',
                    'Point of sale system',
                    'Advanced reporting & analytics',
                    'Multi-user management',
                    'API access',
                    'Priority email support',
                    '15% semi-annual discount'
                ],
                'limits' => [
                    'max_subshops' => 10,
                    'max_users' => 25,
                    'max_items' => 10000,
                    'storage_gb' => 25
                ],
                'is_popular' => false,
                'sort_order' => 6
            ],
            [
                'name' => 'Enterprise 2 Months',
                'slug' => 'enterprise-2-months',
                'description' => 'Enterprise plan for 2 months',
                'price' => 359.98, // 199.99 * 2 * 0.9 (10% discount)
                'currency' => 'USD',
                'billing_cycle' => '2_months',
                'status' => 'active',
                'features' => [
                    'Unlimited subshops',
                    'Full inventory management',
                    'Advanced POS with integrations',
                    'Real-time analytics & dashboards',
                    'Advanced user management',
                    'Full API access',
                    'White-label options',
                    'Dedicated support manager',
                    'Custom integrations',
                    '10% bi-monthly discount'
                ],
                'limits' => [
                    'max_subshops' => null, // unlimited
                    'max_users' => null, // unlimited
                    'max_items' => null, // unlimited
                    'storage_gb' => 100
                ],
                'is_popular' => false,
                'sort_order' => 7
            ]
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']], // Find by slug
                $planData // Update with this data
            );
        }
    }
}
