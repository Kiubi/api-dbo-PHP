<?php

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {

	/** @var list<string> */
	private array $tempFiles = [];

	protected function tearDown(): void {
		foreach ($this->tempFiles as $path) {
			if (is_file($path)) {
				unlink($path);
			}
		}
		$this->tempFiles = [];
		parent::tearDown();
	}

	/**
	 * @param string $content
	 * @return string
	 */
	protected function createTempFile(string $content = ''): string {
		$path = tempnam(sys_get_temp_dir(), 'kiubi-test-');

		if ($path === false) {
			throw new RuntimeException('Unable to create temporary file');
		}

		file_put_contents($path, $content);

		$this->tempFiles[] = $path;

		return $path;
	}
	
	/**
	 * @return (\Kiubi\Api&\PHPUnit\Framework\MockObject\MockObject)
	 */
	protected function mockApi() {
		$mock = $this
			->getMockBuilder(\Kiubi\Api::class)
			->setConstructorArgs(['TESTTOKEN'])
			->onlyMethods(['curlQuery'])
			->getMock();
		
		return $mock;
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function mockSuccess() : array {
		return [
			'success' => true,
			'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
			'content' => '{"meta":{"success":true,"status_code":200},"data":{}}'
		];
	}

	/**
	 * @param string $error
	 * @return array<string, mixed>
	 */
	protected function mockFailure(string $error) : array {
		return [
			'success' => true,
			'headers' => 'HTTP/1.1 400 OK'."\r\n".'Content-Type: application/json',
			'content' => '{"meta":{"success":false,"status_code":400},"error":{"code":123,"message":"'.$error.'"}}'
		];
	}

	public function test_simplest_ok_query(): void {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/path'),
				$this->equalTo('GET'),
				$this->equalTo([
					'X-API: Kiubi API PHP Client v3.0',
					'Authorization: token TESTTOKEN'
				]),
				$this->equalTo(''),
			)
			->willReturn($this->mockSuccess());
		
		$result = $api->query('GET', '/path');
		
		$this->assertTrue($result->hasSucceed());
		$this->assertEquals(200, $result->getHttpCode());
		$this->assertEquals([], $result->getData());
		
	}

	public function test_simplest_ko_query(): void {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/path'),
				$this->equalTo('GET'),
				$this->equalTo([
					'X-API: Kiubi API PHP Client v3.0',
					'Authorization: token TESTTOKEN'
				]),
				$this->equalTo(''),
			)
			->willReturn($this->mockFailure('ERROR'));

		$result = $api->query('GET', '/path');

		$this->assertTrue($result->hasFailed());
		$this->assertEquals(400, $result->getHttpCode());
		$this->assertEquals([
			'code' => 123,
			'message' => "ERROR"
		], $result->getError());

	}

	/**
	 * @return array<array{0:array<string, mixed>, 1:string}>
	 */
	public function providerPayload() {
		return [
			
			[
				[
					'foo' => 'FOO',
					'baz' => 123,
				],
				'foo=FOO&baz=123'
			],

			[
				[
					'foo' => 'FOO',
					'bar' => null // will be stripped
				],
				'foo=FOO'  // expect no bar
			],

			[
				[
					'foo' => ['a', 'b', 'c'],
				],
				'foo%5B0%5D=a&foo%5B1%5D=b&foo%5B2%5D=c'
			],

			[
				[
					'foo' => ['a'=>123, 'b'=>234, 'c'=>345],
				],
				'foo%5Ba%5D=123&foo%5Bb%5D=234&foo%5Bc%5D=345'
			],
			
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param string $expected
 	 * @return void
	 * @dataProvider providerPayload
	 */
	public function test_get_query(array $payload, string $expected): void {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/path'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->equalTo($expected),
			)
			->willReturn($this->mockSuccess());

		$result = $api->get('/path', $payload);

		$this->assertTrue($result->hasSucceed());
		$this->assertEquals(200, $result->getHttpCode());
		$this->assertEquals([], $result->getData());

	}

	/**
	 * @param array<string, mixed> $payload
	 * @param string $expected
 	 * @return void
	 * @dataProvider providerPayload
	 */
	public function test_post_query(array $payload, string $expected): void {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/path'),
				$this->equalTo('POST'),
				$this->anything(),
				$this->equalTo($expected),
			)
			->willReturn($this->mockSuccess());

		$result = $api->post('/path', $payload);

		$this->assertTrue($result->hasSucceed());
		$this->assertEquals(200, $result->getHttpCode());
		$this->assertEquals([], $result->getData());

	}

	/**
	 * @param array<string, mixed> $payload
	 * @param string $expected
	 * @return void
	 * @dataProvider providerPayload
	 */
	public function test_put_query(array $payload, string $expected): void {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/path'),
				$this->equalTo('PUT'),
				$this->anything(),
				$this->equalTo($expected),
			)
			->willReturn($this->mockSuccess());

		$result = $api->put('/path', $payload);

		$this->assertTrue($result->hasSucceed());
		$this->assertEquals(200, $result->getHttpCode());
		$this->assertEquals([], $result->getData());

	}

	/**
	 * @param array<string, mixed> $payload
	 * @param string $expected
	 * @return void
	 * @dataProvider providerPayload
	 */
	public function test_delete_query(array $payload, string $expected): void {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/path'),
				$this->equalTo('DELETE'),
				$this->anything(),
				$this->equalTo($expected),
			)
			->willReturn($this->mockSuccess());

		$result = $api->delete('/path', $payload);

		$this->assertTrue($result->hasSucceed());
		$this->assertEquals(200, $result->getHttpCode());
		$this->assertEquals([], $result->getData());

	}

	public function test_post_file(): void {
		
		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/path'),
				$this->equalTo('POST'),
				$this->anything(),
				$this->stringContains(
					"\r\n". "Content-Type: application/octet-stream\r\n\r\n".
					"PEC\r\n".
					"-----KiubiAPIClientBoundary"
				),
			)
			->willReturn($this->mockSuccess());

		$result = $api->post('/path', [
			'file' => new Kiubi\File($this->createTempFile('PEC'))
		]);

		$this->assertTrue($result->hasSucceed());
		$this->assertEquals(200, $result->getHttpCode());
		$this->assertEquals([], $result->getData());

	}
	
}
