<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Clock\StaticClock;
use Arokettu\Date\Calendar;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\NationalBankOfRomaniaService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class HistoricalRequestTest extends TestCase
{
    public function testRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $date = Calendar::parse('2025-05-15');

        $service = new NationalBankOfRomaniaService(cache: $cache, httpClient: $http, clock: $clock);

        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'RON', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('4.5554', $response->rate->value);
        self::assertEquals('2025-05-15', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('JPY', 'RON', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.031228', $response->rate->value);
        self::assertEquals('2025-05-15', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('RUB', 'RON', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.0566', $response->rate->value);
        self::assertEquals('2025-05-15', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testNoRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $date = Calendar::parse('2025-05-15');

        $service = new NationalBankOfRomaniaService(cache: $cache, httpClient: $http, clock: $clock);

        // unknown currency
        $response = $service->send(new HistoricalExchangeRateRequest('KZT', 'RON', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals(
            'Unable to find exchange rate for KZT/RON on 2025-05-15',
            $response->exception->getMessage(),
        );

        // reverse rate
        $response = $service->send(new HistoricalExchangeRateRequest('RON', 'USD', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals(
            'Unable to find exchange rate for RON/USD on 2025-05-15',
            $response->exception->getMessage(),
        );
    }
}
