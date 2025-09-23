<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Clock\SystemClock;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\PesoServiceInterface;
use Peso\Core\Services\SDK\Cache\NullCache;
use Peso\Core\Services\SDK\HTTP\DiscoveredHttpClient;
use Peso\Core\Services\SDK\HTTP\DiscoveredRequestFactory;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class NationalBankOfRomaniaService implements PesoServiceInterface
{
    public function __construct(
        private CacheInterface $cache = new NullCache(),
        private DateInterval $currentTtl = new DateInterval('PT1H'),
        private DateInterval $historyTtl = new DateInterval('P60D'),
        private ClientInterface $httpClient = new DiscoveredHttpClient(),
        private RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function send(object $request): ExchangeRateResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            return self::performCurrentRequest($request);
        }
        if ($request instanceof HistoricalExchangeRateRequest) {
            return self::performHistoricalRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function supports(object $request): bool
    {
        return ($request instanceof CurrentExchangeRateRequest || $request instanceof HistoricalExchangeRateRequest)
            && $request->quoteCurrency === 'RON';
    }
}
