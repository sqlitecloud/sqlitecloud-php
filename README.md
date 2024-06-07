# Driver for SQLite Cloud

<p align="center">
  <img src="https://sqlitecloud.io/social/logo.png" height="300" alt="SQLite Cloud logo">
</p>


[![Test and QA](https://github.com/sqlitecloud/sqlitecloud-php/actions/workflows/deploy.yaml/badge.svg?branch=main)](https://github.com/sqlitecloud/sqlitecloud-php/actions/workflows/deploy.yaml)
[![codecov](https://codecov.io/gh/sqlitecloud/sqlitecloud-php/graph/badge.svg?token=3FFHULGCOY)](https://codecov.io/gh/sqlitecloud/sqlitecloud-php)
[![Packagist Version](https://img.shields.io/packagist/v/sqlitecloud/sqlitecloud)](https://packagist.org/packages/sqlitecloud/sqlitecloud)
[![PHP](https://img.shields.io/packagist/dependency-v/sqlitecloud/sqlitecloud/php)](https://packagist.org/packages/sqlitecloud/sqlitecloud)
[![Downloads](https://img.shields.io/packagist/dt/sqlitecloud/sqlitecloud)](https://packagist.org/packages/sqlitecloud/sqlitecloud)



- [Driver for SQLite Cloud](#driver-for-sqlite-cloud)
- [Example](#example)

---

[SQLite Cloud](https://sqlitecloud.io) is a powerful PHP package that allows you to interact with the SQLite Cloud database seamlessly. It provides methods for various database operations. This package is designed to simplify database operations in PHP applications, making it easier than ever to work with SQLite Cloud.


- Documentation: [https://docs.sqlitecloud.io/docs/sdk/php](https://docs.sqlitecloud.io/docs/sdk/php/connect)
- Source: [https://github.com/sqlitecloud/sqlitecloud-php](https://github.com/sqlitecloud/sqlitecloud-php)
- Site: [https://sqlitecloud.io](https://sqlitecloud.io/developers)

## Example

```bash
$ composer require sqlitecloud/sqlitecloud
```

```php
<?php

require_once 'vendor/autoload.php';

use SQLiteCloud\SQLiteCloudClient;
use SQLiteCloud\SQLiteCloudRowset;

// Open the connection to SQLite Cloud
$sqlite = new SQLiteCloudClient();
$sqlite->connectWithString('sqlitecloud://myhost.sqlite.cloud:8860?apikey=myapikey');

// You can autoselect the database during the connect call
// by adding the database name as path of the SQLite Cloud
// connection string, eg:
// $sqlite->connectWithString("sqlitecloud://myhost.sqlite.cloud:8860/mydatabase?apikey=myapikey");
$db_name = 'chinook.sqlite';
$sqlite->execute("USE DATABASE {$db_name}");

 /** @var SQLiteCloudRowset */
$rowset = $sqlite->execute('SELECT * FROM albums WHERE ArtistId = 2');

printf('%d rows' . PHP_EOL, $rowset->nrows);
printf('%s | %s | %s' . PHP_EOL, $rowset->name(0), $rowset->name(1), $rowset->name(2));
for ($i = 0; $i < $rowset->nrows; $i++) {
  printf('%s | %s | %s' . PHP_EOL, $rowset->value($i, 0), $rowset->value($i, 1), $rowset->value($i, 2));
}

$sqlite->disconnect();
```
