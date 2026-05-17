<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create(['ticker' => 'AAPL', 'name' => 'Apple Inc.', 'country' => 'US', 'exchange' => 'NASDAQ', 'sector' => 'Technology', 'is_active' => true]);
//        Company::create(['ticker' => 'GOOGL', 'name' => 'Alphabet Inc.', 'country' => 'US', 'exchange' => 'NASDAQ', 'sector' => 'Technology', 'is_active' => true]);
//        Company::create(['ticker' => 'MSFT', 'name' => 'Microsoft Corporation', 'country' => 'US', 'exchange' => 'NASDAQ', 'sector' => 'Technology', 'is_active' => true]);
//
        Company::create(['ticker' => 'TSLA', 'name' => 'Tesla Inc.', 'country' => 'US', 'exchange' => 'NASDAQ', 'sector' => 'Consumer Cyclical', 'is_active' => true]);

//        Company::create(['ticker' => 'AMZN', 'name' => 'Amazon.com Inc.', 'country' => 'US', 'exchange' => 'NASDAQ', 'sector' => 'Consumer Cyclical', 'is_active' => true]);
    }
}
