<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PurposeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $purposes = [
            ['name' => 'Enrollment', 'is_active' => true],
            ['name' => 'Grades Inquiry', 'is_active' => true],
            ['name' => 'Document Request', 'is_active' => true],
            ['name' => 'Payments', 'is_active' => true],
            ['name' => 'Clearance', 'is_active' => true],
            ['name' => 'Other', 'is_active' => true],
        ];

        foreach ($purposes as $purpose) {
            \App\Models\Purpose::updateOrCreate(['name' => $purpose['name']], $purpose);
        }
    }
}
