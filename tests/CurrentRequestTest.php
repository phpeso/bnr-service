<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\NationalBankOfRomaniaService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class CurrentRequestTest extends TestCase
{
    public function testRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new NationalBankOfRomaniaService(cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('USD', 'RON'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('4.3210', $response->rate->value);
        self::assertEquals('2025-09-25', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('JPY', 'RON'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.029045', $response->rate->value);
        self::assertEquals('2025-09-25', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('RUB', 'RON'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.0516', $response->rate->value);
        self::assertEquals('2025-09-25', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testNoRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new NationalBankOfRomaniaService(cache: $cache, httpClient: $http);

        // unknown currency
        $response = $service->send(new CurrentExchangeRateRequest('KZT', 'RON'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals('Unable to find exchange rate for KZT/RON', $response->exception->getMessage());

        // reverse rate
        $response = $service->send(new CurrentExchangeRateRequest('RON', 'USD'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertEquals('Unable to find exchange rate for RON/USD', $response->exception->getMessage());
    }
}
