<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Helpers\Calculator;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\NationalBankOfRomaniaService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;

final class WrappedServicesTest extends TestCase
{
    public function testReversible(): void
    {
        $http = MockClient::get();

        $baseService = new NationalBankOfRomaniaService(httpClient: $http);
        $service = NationalBankOfRomaniaService::reversible(httpClient: $http);

        $request = new CurrentExchangeRateRequest('RON', 'EUR');

        // base service doesn't support
        self::assertInstanceOf(ErrorResponse::class, $baseService->send($request));

        $response = $service->send($request);
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        // ignore calculator changes
        self::assertEquals('0.19703', Calculator::instance()->round($response->rate, 5)->value);
    }

    public function testUniversal(): void
    {
        $http = MockClient::get();

        $baseService = new NationalBankOfRomaniaService(httpClient: $http);
        $service = NationalBankOfRomaniaService::universal(httpClient: $http);

        $request = new CurrentExchangeRateRequest('AUD', 'NZD');

        // base service doesn't support
        self::assertInstanceOf(ErrorResponse::class, $baseService->send($request));

        $response = $service->send($request);
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        // ignore calculator changes
        self::assertEquals('1.13278', Calculator::instance()->round($response->rate, 5)->value);
    }
}
