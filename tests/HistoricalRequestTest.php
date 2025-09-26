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


    public function testDateDiscovery(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-09-26'); // 'now'

        $service = new NationalBankOfRomaniaService(cache: $cache, httpClient: $http, clock: $clock);

        // last 10 records test

        // exact date
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'RON', Calendar::parse('2025-09-24')));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('5.0784', $response->rate->value);
        self::assertEquals('2025-09-24', $response->date->toString());
        self::assertCount(1, $http->getRequests());

        // discovery
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'RON', Calendar::parse('2025-09-21')));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('5.0719', $response->rate->value);
        self::assertEquals('2025-09-19', $response->date->toString());
        self::assertCount(1, $http->getRequests());

        // miss
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'RON', Calendar::parse('2025-09-14')));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('5.0694', $response->rate->value);
        self::assertEquals('2025-09-12', $response->date->toString());
        self::assertCount(2, $http->getRequests()); // loaded the year 2025

        // year data

        // exact date
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'RON', Calendar::parse('2012-06-13')));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('4.4593', $response->rate->value);
        self::assertEquals('2012-06-13', $response->date->toString());
        self::assertCount(3, $http->getRequests()); // loaded the year 2012

        // discovery
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'RON', Calendar::parse('2012-08-19')));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('4.4852', $response->rate->value);
        self::assertEquals('2012-08-17', $response->date->toString());
        self::assertCount(3, $http->getRequests());

        // miss
        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'RON', Calendar::parse('2012-01-02')));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('4.3197', $response->rate->value);
        self::assertEquals('2011-12-30', $response->date->toString());
        self::assertCount(4, $http->getRequests()); // loaded the year 2011
    }
}
