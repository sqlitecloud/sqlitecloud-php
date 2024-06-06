# Driver for SQLite Cloud

<p align="center">
  <img src="https://sqlitecloud.io/social/logo.png" height="300" alt="SQLite Cloud logo">
</p>


**TODO**
<!-- ![Build Status](https://github.com/sqlitecloud/sqlitecloud-py/actions/workflows/deploy.yaml/badge.svg "Build Status")
[![codecov](https://codecov.io/github/sqlitecloud/python/graph/badge.svg?token=38G6FGOWKP)](https://codecov.io/github/sqlitecloud/python)
![PyPI - Version](https://img.shields.io/pypi/v/sqlitecloud?link=https%3A%2F%2Fpypi.org%2Fproject%2FSqliteCloud%2F)
![PyPI - Downloads](https://img.shields.io/pypi/dm/sqlitecloud?link=https%3A%2F%2Fpypi.org%2Fproject%2FSqliteCloud%2F)
![PyPI - Python Version](https://img.shields.io/pypi/pyversions/sqlitecloud?link=https%3A%2F%2Fpypi.org%2Fproject%2FSqliteCloud%2F) -->


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
use SQLiteCloud;

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
