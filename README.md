# PHP Web Server

A simple web server written in PHP. Created as a pet project to improve understanding of web servers and server protocols.

## Documentation
- [Hosts Configuration](docs/configuration-hosts.md)

## Highlights

- Written entirely in PHP
- Framework and package agnostic
- [PSR-2][] and [PSR-4][] compliant

## System Requirements

You need:

- **PHP >= 8.4.0** but the latest stable version of PHP is recommended
- **ext-dom**
- **ext-openssl**
- **ext-pcntl** (for prefork model)

## Install

Via Git

``` bash
$ git clone https://github.com/cryonighter/web-server
```

## Usage

``` bash
$ php server.php 
```

The following parameters are available:
- `--log-level` - Logging level; available options: `debug`, `info`, `notice`, `warning`, `error` (default: info)
- `--model` - Server operation model; available options: `single`, `prefork`
- `--ipc` - IPC operation model; available options: `shared_memory`, `socket_pair`

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ php vendor/phpunit/phpunit/phpunit tests
```

## Security

If you discover any security related issues, please email `cryonighter@yandex.ru` instead of using the issue tracker.

## Credits

- [Andrey Reshetchenko][link-author]

[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/

[link-author]: https://github.com/cryonighter
