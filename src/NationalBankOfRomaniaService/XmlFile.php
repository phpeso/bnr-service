<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\NationalBankOfRomaniaService;

use Sabre\Xml\Reader;

final readonly class XmlFile
{
    public static function parse(string $xml): array
    {
        $reader = new Reader();
        $reader->elementMap = [
        ];
        $reader->XML($xml);

        return $reader->parse()['value'];
    }
}
