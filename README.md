# Driver for SQLite Cloud

<p align="center">
  <img src="https://sqlitecloud.io/social/logo.png" height="300" alt="SQLite Cloud logo">
</p>


[![Test and QA](https://github.com/sqlitecloud/sqlitecloud-php/actions/workflows/deploy.yaml/badge.svg?branch=main)](https://github.com/sqlitecloud/sqlitecloud-php/actions/workflows/deploy.yaml)
[![codecov](https://codecov.io/gh/sqlitecloud/sqlitecloud-php/graph/badge.svg?token=3FFHULGCOY)](https://codecov.io/gh/sqlitecloud/sqlitecloud-php)
![PHP](https://img.shields.io/packagist/dependency-v/sqlitecloud/sqlitecloud/php)


- [Driver for SQLite Cloud](#driver-for-sqlite-cloud)
- [Example](#example)

---

[SQLite Cloud](https://sqlitecloud.io) is a powerful Python package that allows you to interact with the SQLite Cloud database seamlessly. It provides methods for various database operations. This package is designed to simplify database operations in PHP applications, making it easier than ever to work with SQLite Cloud.


- Documentation: [https://docs.sqlitecloud.io/docs/sdk/php/admin](https://docs.sqlitecloud.io/docs/sdk/php/admin)
- Source: [https://github.com/sqlitecloud/sqlitecloud-php](https://github.com/sqlitecloud/sqlitecloud-php)
- Site: [https://sqlitecloud.io](https://sqlitecloud.io/developers)

## Example

```bash
$ composer install sqlitecloud/sqlitecloud
```

```php
use use SQLiteCloud\SQLiteCloudClient;
use SQLiteCloud\SQLiteCloudRowset;

# Open the connection to SQLite Cloud
$sqlite = new SQLiteCloud();
$sqlite->connectWithString('sqlitecloud://myhost.sqlite.cloud:8860?apikey=myapikey');

# You can autoselect the database during the connect call
# by adding the database name as path of the SQLite Cloud
# connection string, eg:
# $sqlite->connectWithString("sqlitecloud://myhost.sqlite.cloud:8860/mydatabase?apikey=myapikey");
$db_name = 'chinook.sqlite';
$sqlite->execute("USE DATABASE {$db_name}");

 /** @var SQLiteCloudRowset */
$rowset = $sqlite->execute('SELECT * FROM albums WHERE AlbumId = 1');

print('First colum, first row: ' . $rowset->value(0, 0));
print('Second colum, first row: ' . $rowset->value(0, 1));
print('column name: ' . $rowset->name(1));

$sqlite->disconnect()
```
