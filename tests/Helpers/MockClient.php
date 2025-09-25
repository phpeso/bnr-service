<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests\Helpers;

use GuzzleHttp\Psr7\Response;
use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;

final readonly class MockClient
{
    public static function get(): Client
    {
        $client = new Client();

        $client->on(
            new RequestMatcher('/nbrfxrates.xml', 'curs.bnr.ro', ['GET'], ['https']),
            static function () {
                return new Response(body: fopen(__DIR__ . '/../data/nbrfxrates.xml', 'r'));
            },
        );

        return $client;
    }
}
