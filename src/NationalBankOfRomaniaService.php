<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Clock\SystemClock;
use Arokettu\Date\Calendar;
use Arokettu\Date\Date;
use DateInterval;
use Override;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Exceptions\RuntimeException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\IndirectExchangeService;
use Peso\Core\Services\PesoServiceInterface;
use Peso\Core\Services\ReversibleService;
use Peso\Core\Services\SDK\Cache\NullCache;
use Peso\Core\Services\SDK\Exceptions\CacheFailureException;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Services\SDK\HTTP\DiscoveredHttpClient;
use Peso\Core\Services\SDK\HTTP\DiscoveredRequestFactory;
use Peso\Core\Services\SDK\HTTP\UserAgentHelper;
use Peso\Core\Types\Decimal;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class NationalBankOfRomaniaService implements PesoServiceInterface
{
    private const LAST_RECORD = 'https://curs.bnr.ro/nbrfxrates.xml';
    private const LAST_10_RECORDS = 'https://curs.bnr.ro/nbrfxrates10days.xml';
    private const YEAR_RECORD = 'https://curs.bnr.ro/files/xml/years/nbrfxrates%s.xml';

    public function __construct(
        private CacheInterface $cache = new NullCache(),
        private DateInterval $currentTtl = new DateInterval('PT1H'),
        private DateInterval $historyTtl = new DateInterval('P7D'),
        private ClientInterface $httpClient = new DiscoveredHttpClient(),
        private RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    public static function reversible(
        CacheInterface $cache = new NullCache(),
        DateInterval $currentTtl = new DateInterval('PT1H'),
        DateInterval $historyTtl = new DateInterval('P7D'),
        ClientInterface $httpClient = new DiscoveredHttpClient(),
        RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        ClockInterface $clock = new SystemClock(),
    ): PesoServiceInterface {
        return new ReversibleService(new self($cache, $currentTtl, $historyTtl, $httpClient, $requestFactory, $clock));
    }

    public static function universal(
        CacheInterface $cache = new NullCache(),
        DateInterval $currentTtl = new DateInterval('PT1H'),
        DateInterval $historyTtl = new DateInterval('P7D'),
        ClientInterface $httpClient = new DiscoveredHttpClient(),
        RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        ClockInterface $clock = new SystemClock(),
    ): PesoServiceInterface {
        return new IndirectExchangeService(
            self::reversible($cache, $currentTtl, $historyTtl, $httpClient, $requestFactory, $clock),
            'RON',
        );
    }


    /**
     * @inheritDoc
     */
    #[Override]
    public function send(object $request): ExchangeRateResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            return $this->performCurrentRequest($request);
        }
        if ($request instanceof HistoricalExchangeRateRequest) {
            return $this->performHistoricalRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    public function performCurrentRequest(CurrentExchangeRateRequest $request): ExchangeRateResponse|ErrorResponse
    {
        if ($request->quoteCurrency !== 'RON') { // ROL and earlier are not supported
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }

        $ratesXml = $this->getXmlData(self::LAST_RECORD, $this->currentTtl);

        $date = array_key_first($ratesXml); // there is only one date
        $rates = $ratesXml[$date];

        return isset($rates[$request->baseCurrency]) ?
            new ExchangeRateResponse(new Decimal($rates[$request->baseCurrency]), Calendar::parse($date)) :
            new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
    }

    private function performHistoricalRequest(
        HistoricalExchangeRateRequest $request,
    ): ErrorResponse|ExchangeRateResponse {
        if ($request->quoteCurrency !== 'RON') { // ROL and earlier are not supported
            return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
        }
        $today = Calendar::fromDateTime($this->clock->now());

        $rates = null;
        $date = null;
        if ($today->sub($request->date) < 0) {
            return new ErrorResponse(new ExchangeRateNotFoundException('Date seems to be in future'));
        }
        if ($today->sub($request->date) <= 15) { // up to 15 days we can find in the short response
            $ratesXml = $this->getXmlData(self::LAST_10_RECORDS, $this->currentTtl);
            [$rates, $date] = $this->findDayRates($request->date, $ratesXml);
        }
        if ($rates === null) { // not found or not in the last 15 days
            $endpoint = \sprintf(self::YEAR_RECORD, $request->date->getYear());
            $ratesXml = $this->getXmlData($endpoint, $this->historyTtl);
            [$rates, $date] = $this->findDayRates($request->date, $ratesXml);
        }
        if ($rates === null) { // edge case: no data from the beginning of the year
            $endpoint = \sprintf(self::YEAR_RECORD, $request->date->getYear() - 1);
            $ratesXml = $this->getXmlData($endpoint, $this->historyTtl); // always historical ttl
            [$rates, $date] = $this->findDayRates($request->date, $ratesXml);
        }

        return isset($rates[$request->baseCurrency]) ?
            new ExchangeRateResponse(new Decimal($rates[$request->baseCurrency]), $date) :
            new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
    }

    /**
     * @param array<string, array<string, numeric-string>> $ratesXml
     * @return array{0: array<string, numeric-string>, 1: Date}|null
     */
    private function findDayRates(Date $date, array $ratesXml): array|null
    {
        $dateStr = $date->toString();

        if (isset($ratesXml[$dateStr])) { // easy mode
            return [$ratesXml[$dateStr], $date];
        }

        foreach ($ratesXml as $dateKey => $rates) {
            if (strcmp($dateKey, $dateStr) > 0) { // skip bigger values
                continue;
            }
            return [$rates, Calendar::parse($dateKey)];
        }

        return null;
    }

    /**
     * @return array<string, array<string, numeric-string>>
     * @throws RuntimeException
     */
    private function getXmlData(string $url, DateInterval $ttl): array
    {
        $cacheKey = 'peso|bnr|' . hash('sha1', $url);

        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $request = $request->withHeader('User-Agent', UserAgentHelper::buildUserAgentString(
            'BNR-Client',
            'peso/bnr-service',
            $request->hasHeader('User-Agent') ? $request->getHeaderLine('User-Agent') : null,
        ));
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw HttpFailureException::fromResponse($request, $response);
        }

        $data = NationalBankOfRomaniaService\XmlFile::parse((string)$response->getBody());

        $this->cache->set($cacheKey, $data, $ttl) ?: throw new CacheFailureException('Cache service error');

        return $data;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function supports(object $request): bool
    {
        if ($request instanceof CurrentExchangeRateRequest && $request->quoteCurrency === 'RON') {
            return true;
        }

        if (
            $request instanceof HistoricalExchangeRateRequest &&
            $request->quoteCurrency === 'RON' &&
            $request->date->compare(Calendar::parse('2005-01-03')) >= 0
        ) {
            return true;
        }

        return false;
    }
}
