<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Date;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Services\NationalBankOfRomaniaService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SupportTest extends TestCase
{
    public function testRequests(): void
    {
        $service = new NationalBankOfRomaniaService();

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('EUR', 'USD')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('EUR', 'USD', Date::today())));
        self::assertFalse($service->supports(new CurrentExchangeRateRequest('USD', 'EUR')));
        self::assertFalse($service->supports(new HistoricalExchangeRateRequest('USD', 'EUR', Date::today())));
        self::assertFalse($service->supports(new stdClass()));
    }
}
