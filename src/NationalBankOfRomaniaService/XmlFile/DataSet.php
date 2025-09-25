<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\NationalBankOfRomaniaService\XmlFile;

use Sabre\Xml\Element\KeyValue;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

final readonly class DataSet implements XmlDeserializable
{
    public static function xmlDeserialize(Reader $reader): array
    {
        $reader->pushContext();
        $reader->elementMap = [
            '{http://www.bnr.ro/xsd}Body' => Body::class,
        ];
        $data = KeyValue::xmlDeserialize($reader);
        $reader->popContext();

        return $data['{http://www.bnr.ro/xsd}Body'];
    }
}
