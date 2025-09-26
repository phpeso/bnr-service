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
        $client->on(
            new RequestMatcher('/nbrfxrates10days.xml', 'curs.bnr.ro', ['GET'], ['https']),
            static function () {
                return new Response(body: fopen(__DIR__ . '/../data/nbrfxrates10days.xml', 'r'));
            },
        );
        foreach ([2011, 2012, 2025] as $year) {
            $client->on(
                new RequestMatcher("/files/xml/years/nbrfxrates{$year}.xml", 'curs.bnr.ro', ['GET'], ['https']),
                static function () use ($year) {
                    return new Response(body: fopen(__DIR__ . "/../data/nbrfxrates{$year}.xml", 'r'));
                },
            );
        }

        return $client;
    }
}
