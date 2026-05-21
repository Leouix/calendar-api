<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanySearchRuTest extends TestCase
{
    public function test_search_ru_returns_company_from_moex_description(): void
    {
        Http::fake([
            'https://iss.moex.com/iss/securities/SBER.json*' => Http::response([
                'description' => [
                    'columns' => ['name', 'title', 'value'],
                    'data' => [
                        ['SECID', 'Security ID', 'SBER'],
                        ['SHORTNAME', 'Short name', 'Sberbank'],
                        ['NAME', 'Name', 'Sberbank PJSC'],
                    ],
                ],
            ], 200),
        ]);

        $this->getJson('/api/company/search-ru/SBER')
            ->assertOk()
            ->assertJson([
                'ticker' => 'SBER',
                'name' => 'Sberbank',
                'exchange' => 'MOEX',
                'country' => 'RU',
                'sector' => null,
            ]);
    }

    public function test_search_ru_returns_404_when_moex_responds_not_found(): void
    {
        Http::fake([
            'https://iss.moex.com/iss/securities/NOPE.json*' => Http::response([], 404),
        ]);

        $this->getJson('/api/company/search-ru/NOPE')
            ->assertStatus(404)
            ->assertJsonStructure(['error']);
    }
}
