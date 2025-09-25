<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
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

        // supported
        self::assertTrue($service->supports(new CurrentExchangeRateRequest('EUR', 'RON')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('EUR', 'RON', Date::today())));

        // too old
        self::assertFalse($service->supports(
            new HistoricalExchangeRateRequest('EUR', 'RON', Calendar::parse('2004-06-06')),
        ));

        // wrong quote
        self::assertFalse($service->supports(new CurrentExchangeRateRequest('RON', 'EUR')));
        self::assertFalse($service->supports(new HistoricalExchangeRateRequest('RON', 'EUR', Date::today())));

        // wrong object
        self::assertFalse($service->supports(new stdClass()));
    }
}
