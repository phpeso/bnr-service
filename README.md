# National Bank of Romania Service for Peso

[![Packagist]][Packagist Link]
[![PHP]][Packagist Link]
[![License]][License Link]
[![GitHub Actions]][GitHub Actions Link]
[![Codecov]][Codecov Link]

[Packagist]: https://img.shields.io/packagist/v/peso/bnr-service.svg?style=flat-square
[PHP]: https://img.shields.io/packagist/php-v/peso/bnr-service.svg?style=flat-square
[License]: https://img.shields.io/packagist/l/peso/bnr-service.svg?style=flat-square
[GitHub Actions]: https://img.shields.io/github/actions/workflow/status/phpeso/bnr-service/ci.yml?style=flat-square
[Codecov]: https://img.shields.io/codecov/c/gh/phpeso/bnr-service?style=flat-square

[Packagist Link]: https://packagist.org/packages/peso/bnr-service
[GitHub Actions Link]: https://github.com/phpeso/bnr-service/actions
[Codecov Link]: https://codecov.io/gh/phpeso/bnr-service
[License Link]: LICENSE.md

This is an exchange data provider for Peso that retrieves data from
[the National Bank of Romania](https://www.bnr.ro/).

## Installation

```bash
composer require peso/bnr-service
```

Install the service with all recommended dependencies:

```bash
composer install peso/bnr-service php-http/discovery guzzlehttp/guzzle symfony/cache
```

## Example

```php
<?php

use Peso\Peso\CurrencyConverter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

require __DIR__ . '/vendor/autoload.php';

$cache = new Psr16Cache(new FilesystemAdapter(directory: __DIR__ . '/cache'));
$service = new \Peso\Services\NationalBankOfRomaniaService($cache);
$converter = new CurrencyConverter($service);
```

## Documentation

Read the full documentation here: <https://phpeso.org/v1.x/services/bnr.html>

## Support

Please file issues on our main repo at GitHub: <https://github.com/phpeso/bnr-service/issues>

## License

The library is available as open source under the terms of the [MIT License][License Link].
