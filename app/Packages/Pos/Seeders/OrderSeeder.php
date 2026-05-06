<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = DB::table('users')->where('username', 'admin')->first();
        
        if (!$adminUser) {
            if ($this->command) {
                $this->command->warn('Admin user not found. Skip seeding orders.');
            }
            return;
        }
        
        $statuses = DB::table('status_orders')->pluck('id', 'name')->toArray();
        
        if (empty($statuses)) {
            if ($this->command) {
                $this->command->warn('Status orders not found. Skip seeding orders.');
            }
            return;
        }
        
        $products = DB::table('products')->where('enable', true)->get();
        
        if ($products->isEmpty()) {
            if ($this->command) {
                $this->command->warn('Products not found. Skip seeding orders.');
            }
            return;
        }
        
        $today = now();
        
        $orders = [
            [
                'dni' => '12345678',
                'detail' => json_encode([
                    'items' => [
                        ['name' => 'Hamburguesa', 'qty' => 2, 'amount' => 150],
                        ['name' => 'Gaseosa', 'qty' => 2, 'amount' => 50]
                    ]
                ]),
                'total' => 400,
                'operator_id' => $adminUser->id,
                'status_id' => $statuses['completed'] ?? 3,
                'paid' => true,
                'created_at' => $today->copy()->subDays(1)
            ],
            [
                'dni' => '87654321',
                'detail' => json_encode([
                    'items' => [
                        ['name' => 'Papas Fritas', 'qty' => 1, 'amount' => 80],
                        ['name' => 'Agua', 'qty' => 1, 'amount' => 40]
                    ]
                ]),
                'total' => 120,
                'operator_id' => $adminUser->id,
                'status_id' => $statuses['completed'] ?? 3,
                'paid' => true,
                'created_at' => $today->copy()->subHours(5)
            ],
            [
                'dni' => '11223344',
                'detail' => json_encode([
                    'items' => [
                        ['name' => 'Hamburguesa', 'qty' => 1, 'amount' => 150]
                    ]
                ]),
                'total' => 150,
                'operator_id' => $adminUser->id,
                'status_id' => $statuses['in_progress'] ?? 2,
                'paid' => false,
                'created_at' => $today->copy()->subHours(1)
            ],
            [
                'dni' => '44332211',
                'detail' => json_encode([
                    'items' => [
                        ['name' => 'Gaseosa', 'qty' => 3, 'amount' => 50]
                    ]
                ]),
                'total' => 150,
                'operator_id' => $adminUser->id,
                'status_id' => $statuses['completed'] ?? 3,
                'paid' => true,
                'created_at' => $today->copy()
            ],
        ];

        foreach ($orders as $order) {
            $order['updated_at'] = $order['created_at'];
            DB::table('orders')->insert($order);
        }

        if ($this->command) {
            $this->command->info('Seeded ' . count($orders) . ' sample orders');
        }
    }
}