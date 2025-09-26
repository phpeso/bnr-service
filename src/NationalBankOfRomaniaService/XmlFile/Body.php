<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\NationalBankOfRomaniaService\XmlFile;

use Error;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

final readonly class Body implements XmlDeserializable
{
    public static function xmlDeserialize(Reader $reader): array
    {
        $dates = [];
        $data = $reader->parseInnerTree([
            '{http://www.bnr.ro/xsd}Cube' => Cube::class,
        ]);

        foreach ($data as $date) {
            if ($date['name'] !== '{http://www.bnr.ro/xsd}Cube') {
                continue;
            }
            $key = $date['attributes']['date'] ?? throw new Error('Invalid data returned');
            $value = $date['value'];

            $dates[$key] = $value;
        }

        krsort($dates); // year archives are sorted in ascending order, we need to reverse it

        return $dates;
    }
}
