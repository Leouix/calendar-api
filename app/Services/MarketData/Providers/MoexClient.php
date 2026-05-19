<?php

namespace App\Services\MarketData\Providers;

use App\ClientProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MoexClient implements ClientProviderInterface
{
    private const BASE_URL = 'https://iss.moex.com/iss';

    public function hasKey(): bool
    {
        return true; // API Мосбиржи работает без ключа
    }

    /**
     * История дивидендов по тикеру
     * @throws ConnectionException
     */
    public function dividends(string $ticker): Response
    {
        // MOEX требует тикеры в верхнем регистре (например, SBER)
        $ticker = strtoupper($ticker);

        return Http::timeout(15)
            ->retry(3, 1000)
            ->get(self::BASE_URL . "/securities/{$ticker}/dividends.json", [
                'iss.meta' => 'off',
            ]);
    }

    /**
     * Исторические котировки (цены закрытия, объемы и т.д.)
     */
    public function history(string $ticker, ?string $dateFrom = null): Response
    {
        $ticker = strtoupper($ticker);

        $params = [];
        if ($dateFrom) {
            $params['from'] = $dateFrom; // Формат: YYYY-MM-DD
        }

        // TQBR - основной режим торгов для акций (Т+1)
        return Http::timeout(15)
            ->retry(3, 1000)
            ->get(self::BASE_URL . "/history/engines/stock/markets/shares/boards/TQBR/securities/{$ticker}.json", $params);
    }

    /**
     * Даты выхода корпоративной отчетности (Earnings)
     *
     * ВАЖНО: Метод выбросит исключение, так как MOEX ISS API не
     * содержит календаря выхода отчетов МСФО/РСБУ.
     */
    public function earnings(string $ticker)
    {
        throw new \RuntimeException(
            "В публичном API Мосбиржи нет календаря отчетностей для тикера {$ticker}. " .
            "Используйте другой провайдер (например, парсинг smart-lab.ru/q/{$ticker}/f/y/ " .
            "или официальный сайт центра раскрытия информации)."
        );
    }
}
