<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\NationalBankOfRomaniaService\XmlFile;

use Error;
use Peso\Core\Helpers\Calculator;
use Peso\Core\Types\Decimal;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

final readonly class Cube implements XmlDeserializable
{
    public static function xmlDeserialize(Reader $reader): array
    {
        $currencies = [];
        $data = $reader->parseInnerTree([]); // empty map
        $calculator = Calculator::instance();

        foreach ($data as $currency) {
            $key = $currency['attributes']['currency'] ?? throw new Error('Invalid data returned');
            $value = $currency['value'] ?? throw new Error('Invalid data returned');
            if (isset($currency['attributes']['multiplier'])) {
                $per = $calculator->trimZeros($calculator->invert(new Decimal($currency['attributes']['multiplier'])));
                $value = $calculator->multiply(Decimal::init($value), $per)->value;
            }
            $currencies[$key] = $value;
        }

        return $currencies;
    }
}
