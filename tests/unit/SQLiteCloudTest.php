<?php

namespace SQLiteCloud\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SQLiteCloud\SQLiteCloudClient;

class SQLiteCloudTest extends TestCase
{
    public function testConnectWithStringWithPort()
    {
        /** @var MockObject|SQLiteCloudClient */
        $sqlite = $this->getMockBuilder(SQLiteCloudClient::class)
            ->setMethods(['connect'])
            ->getMock();

        $sqlite->expects($this->once())
            ->method('connect')
            ->with('disney.sqlite.cloud', 9972)
            ->willReturn(true);

        $connectionString = 'sqlitecloud://disney.sqlite.cloud:9972';

        $sqlite->connectWithString($connectionString);
    }

    public function testConnectWithStringWithBothApiKeyAndCredentials()
    {
        /** @var MockObject|SQLiteCloudClient */
        $sqlite = $this->getMockBuilder(SQLiteCloudClient::class)
            ->setMethods(['connect'])
            ->getMock();

        $sqlite->expects($this->once())
            ->method('connect')
            ->willReturn(true);

        $connectionString = 'sqlitecloud://pippo:pluto@disney.sqlite.cloud:8860?apikey=abc12345';

        $sqlite->connectWithString($connectionString);

        $this->assertEmpty($sqlite->username);
        $this->assertEmpty($sqlite->password);
        $this->assertSame('abc12345', $sqlite->apikey);
    }

    public function testConnectWithStringWithOptions()
    {
        /** @var MockObject|SQLiteCloudClient */
        $sqlite = $this->getMockBuilder(SQLiteCloudClient::class)
            ->setMethods(['connect'])
            ->getMock();

        $sqlite->expects($this->once())
            ->method('connect')
            ->with('disney.sqlite.cloud')
            ->willReturn(true);

        $connectionString = 'sqlitecloud://disney.sqlite.cloud/mydb?apikey=abc12345&insecure=true&timeout=100';

        $sqlite->connectWithString($connectionString);

        $this->assertSame('mydb', $sqlite->database);
        $this->assertSame('abc12345', $sqlite->apikey);
        $this->assertSame(true, $sqlite->insecure);
        $this->assertSame(100, $sqlite->timeout);
    }

    public function testConnectWithStringWithoutOptionals()
    {
        /** @var MockObject|SQLiteCloudClient */
        $sqlite = $this->getMockBuilder(SQLiteCloudClient::class)
            ->setMethods(['connect'])
            ->getMock();

        $sqlite->expects($this->once())
            ->method('connect')
            ->with('disney.sqlite.cloud')
            ->willReturn(true);

        $connectionString = 'sqlitecloud://disney.sqlite.cloud';

        $sqlite->connectWithString($connectionString);

        $this->assertEmpty($sqlite->username);
        $this->assertEmpty($sqlite->password);
        $this->assertEmpty($sqlite->database);
    }

    public function parameterssDataProvider()
    {
        return [
            'timeout' => ['timeout', 11],
            'compression /true' => ['compression', true],
            'compression /false' => ['compression', false],
            'zerotext' => ['zerotext', true],
            'memory' => ['memory', true],
            'create' => ['create', true],
            'non_linearizable' => ['non_linearizable', true],
            'nonlinearizable' => ['nonlinearizable', true, 'non_linearizable'],
            'insecure' => ['insecure', true],
            'no_verify_certificate' => ['no_verify_certificate', true],
            'noblob' => ['noblob', true],
            'maxdata' => ['maxdata', 12],
            'maxrows' => ['maxdata', 14],
            'maxrowset' => ['maxdata', 16],
            'tls_root_certificate' => ['root_certificate', '123abc', 'tls_root_certificate'],
            'tls_certificate' => ['certificate', '123abc', 'tls_certificate'],
            'tls_certificate_key' => ['certificate_key', '123abc', 'tls_certificate_key']
        ];
    }

    /**
     * @dataProvider parameterssDataProvider
     */
    public function testParameterToBeSet(string $param, $value, string $paramAlias = null)
    {
        /** @var MockObject|SQLiteCloudClient */
        $sqlite = $this->getMockBuilder(SQLiteCloudClient::class)
            ->setMethods(['connect'])
            ->getMock();

        $sqlite->expects($this->once())
            ->method('connect')
            ->willReturn(true);

        $connectionString = "sqlitecloud://myhost.sqlite.cloud?{$param}={$value}";

        $sqlite->connectWithString($connectionString);

        if ($paramAlias) {
            $this->assertSame($value, $sqlite->{$paramAlias});
        } else {
            $this->assertSame($value, $sqlite->{$param});
        }
    }

    public function testTlsParameters()
    {
        /** @var MockObject|SQLiteCloudClient */
        $sqlite = $this->getMockBuilder(SQLiteCloudClient::class)
            ->setMethods(['connect'])
            ->getMock();

        $sqlite->expects($this->once())
            ->method('connect')
            ->willReturn(true);

        $connectionString = "sqlitecloud://myhost.sqlite.cloud?root_certificate=a%25cd&certificate=b%25de&certificate_key=c%25ef";

        $sqlite->connectWithString($connectionString);

        $this->assertSame('a%cd', $sqlite->tls_root_certificate);
        $this->assertSame('b%de', $sqlite->tls_certificate);
        $this->assertSame('c%ef', $sqlite->tls_certificate_key);
    }
}
