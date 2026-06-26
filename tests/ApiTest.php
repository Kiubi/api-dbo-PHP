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
			->onlyMethods(['curlQuery', 'wait'])
			->getMock();
		
		return $mock;
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function mockSuccess(int $rate_remaining = 123) : array {
		return [
			'success' => true,
			'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
			'content' => '{"meta":{"success":true,"status_code":200,"rate_remaining":'.$rate_remaining.'},"data":{}}'
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

	/**
	 * @return array<string, mixed>
	 */
	protected function mockQuotaFailure() : array {
		return [
			'success' => true,
			'headers' => 'HTTP/1.1 403 OK'."\r\n".'Content-Type: application/json',
			'content' => '{"meta":{"success":false,"status_code":403,"rate_remaining":0},"error":{"code":4300,"message":"Quota squandered"}}'
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
		$this->assertNull($result->getError());
		
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

	public function test_set_url(): void {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.exemple.org/api/v1/path'),
				$this->equalTo('GET'),
				$this->equalTo([
					'X-API: Kiubi API PHP Client v3.0',
					'Authorization: token TESTTOKEN'
				]),
				$this->equalTo(''),
			)
			->willReturn($this->mockSuccess());

		$api->setUrl('https://api.exemple.org/api');
		
		$result = $api->query('GET', '/path');

		$this->assertTrue($result->hasSucceed());

	}
	
	
	public function test_request_rate_remaining() : void {

		$api = $this->mockApi();
		$api->expects($this->once()) // only one req
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/rate'),
				$this->equalTo('GET')
			)
			->willReturn($this->mockSuccess()); // 123 as remaining

		$this->assertEquals(123, $api->getRateRemaining()); // expect run GET /rate

		$this->assertEquals(123, $api->getRateRemaining()); // expect value from cache
	}

	public function test_rate_remaining_persistence() : void  {

		$api = $this->mockApi();
		$api->expects($this->once()) // only one req
		->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test'),
				$this->equalTo('GET')
			)
			->willReturn([
				'success' => true,
				'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
				'content' => '{"meta":{"success":true,"status_code":200,"rate_remaining":123},"data":{}}'
			]);

		$result = $api->get('/test');
		$this->assertTrue($result->hasSucceed());
		
		$this->assertEquals(123, $api->getRateRemaining()); // expect value from previous request
	}
	
	public function test_access_token() : void  {
		
		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test'),
				$this->equalTo('GET'),
				$this->equalTo([
					'X-API: Kiubi API PHP Client v3.0',
					'Authorization: token NEWTOKEN'
				]),
				$this->equalTo(''),
			)
			->willReturn($this->mockSuccess());
		
		$api->setAccessToken('NEWTOKEN');
		
		$this->assertEquals('NEWTOKEN', $api->getAccessToken());
		
		$result = $api->get('/test');
		
		$this->assertTrue($result->hasSucceed());
		
	}
	
	public function test_navigation() : void  {

		$api = $this->mockApi();
		$api->expects($this->once())
		->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->equalTo('limit=10&page=2')
			)
			->willReturn([
				'success' => true,
				'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
				'content' => '{"meta":{"success":true,"status_code":200,"rate_remaining":123,"link":{"next_page":"/test?limit=10&page=3","previous_page":"/test?limit=10&page=1","first_page":"/test?limit=10","last_page":"/test?limit=10&page=4"}},"data":{}}'
			]);

		$response = $api->get('/test', ['limit' => 10, 'page' => 2]);
		$this->assertTrue($response->hasSucceed());
		
		$this->assertTrue($api->hasNextPage($response));
		$this->assertTrue($api->hasPreviousPage($response));
		
	}


	/**
	 * @return array<array{0:string}>
	 */
	function providerNavigation() {
		return [
			['{"meta":{"success":true,"status_code":200,"rate_remaining":123,"link":{"next_page":"/test?limit=10&page=3","previous_page":"/test?limit=10&page=1","first_page":"/test?limit=10","last_page":"/test?limit=10&page=4"}},"data":{}}']
		];
	}

	/**
	 * @param string $content
	 * @return void
	 * @dataProvider providerNavigation
	 */
	public function test_navigation_next(string $content) : void  {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test?limit=10&page=3'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->equalTo('')
			)
			->willReturn([
				'success' => true,
				'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
				'content' => '{"meta":{"success":true,"status_code":200},"data":{}}'
			]);

		$response = new \Kiubi\Response(['HTTP/1.1'=>'200', 'Content-Type' => 'application/json'], $content);
		
		$responseNav = $api->getNextPage($response);
		$this->assertInstanceOf(\Kiubi\Response::class, $responseNav);
		$this->assertTrue($responseNav->hasSucceed());

	}

	/**
	 * @param string $content
	 * @return void
	 * @dataProvider providerNavigation
	 */
	public function test_navigation_previous(string $content) : void  {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test?limit=10&page=1'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->equalTo('')
			)
			->willReturn([
				'success' => true,
				'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
				'content' => '{"meta":{"success":true,"status_code":200},"data":{}}'
			]);

		$response = new \Kiubi\Response(['HTTP/1.1'=>'200', 'Content-Type' => 'application/json'], $content);

		$responsePrev = $api->getPreviousPage($response);
		$this->assertInstanceOf(\Kiubi\Response::class, $responsePrev);
		$this->assertTrue($responsePrev->hasSucceed());

	}

	/**
	 * @param string $content
	 * @return void
	 * @dataProvider providerNavigation
	 */
	public function test_navigation_first(string $content) : void  {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test?limit=10'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->equalTo('')
			)
			->willReturn([
				'success' => true,
				'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
				'content' => '{"meta":{"success":true,"status_code":200},"data":{}}'
			]);

		$response = new \Kiubi\Response(['HTTP/1.1'=>'200', 'Content-Type' => 'application/json'], $content);

		$responseFirst = $api->getFirstPage($response);
		$this->assertInstanceOf(\Kiubi\Response::class, $responseFirst);
		$this->assertTrue($responseFirst->hasSucceed());

	}

	/**
	 * @param string $content
	 * @return void
	 * @dataProvider providerNavigation
	 */
	public function test_navigation_last(string $content) : void  {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test?limit=10&page=4'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->equalTo('')
			)
			->willReturn([
				'success' => true,
				'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
				'content' => '{"meta":{"success":true,"status_code":200},"data":{}}'
			]);

		$response = new \Kiubi\Response(['HTTP/1.1'=>'200', 'Content-Type' => 'application/json'], $content);

		$responseLast = $api->getLastPage($response);
		$this->assertInstanceOf(\Kiubi\Response::class, $responseLast);
		$this->assertTrue($responseLast->hasSucceed());

	}

	/**
	 * @param string $content
	 * @return void
	 * @dataProvider providerNavigation
	 */
	public function test_navigation_page(string $content) : void  {

		$api = $this->mockApi();
		$api->expects($this->once())
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test?limit=10&page=123'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->equalTo('')
			)
			->willReturn([
				'success' => true,
				'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
				'content' => '{"meta":{"success":true,"status_code":200},"data":{}}'
			]);

		$response = new \Kiubi\Response(['HTTP/1.1'=>'200', 'Content-Type' => 'application/json'], $content);

		$responseLast = $api->getPage($response, 123);
		$this->assertInstanceOf(\Kiubi\Response::class, $responseLast);
		$this->assertTrue($responseLast->hasSucceed());

	}
	
	public function test_no_navigation() : void  {

		$api = $this->mockApi();
		$api->expects($this->never())
			->method('curlQuery');

		$response = new \Kiubi\Response(['HTTP/1.1'=>'200', 'Content-Type' => 'application/json'], '{"meta":{"success":true,"status_code":200,"rate_remaining":123,"link":{}},"data":{}}');

		$this->assertFalse($api->hasPreviousPage($response));
		$this->assertFalse($api->hasNextPage($response));
		
		$this->assertFalse($api->getFirstPage($response));
		$this->assertFalse($api->getLastPage($response));
		$this->assertFalse($api->getNextPage($response));
		$this->assertFalse($api->getPreviousPage($response));
		$this->assertFalse($api->getPage($response, 1));

	}
	
	public function test_auto_retry_ok() : void  {
		
		$api = $this->mockApi();

		$api->expects($this->exactly(2))
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->anything(),
			)
			->willReturnOnConsecutiveCalls(
				[
					'success' => false,
					'error' => 'NETWORK ERROR',
				],
				[
					'success' => true,
					'headers' => 'HTTP/1.1 200 OK'."\r\n".'Content-Type: application/json',
					'content' => '{"meta":{"success":true,"status_code":200},"data":{}}'
				]
			);
		
		$api->setAutoRetry(1);
		
		$response = $api->get('/test');
		
		$this->assertTrue($response->hasSucceed());
		
	}

	public function test_auto_retry_fail() : void  {

		$api = $this->mockApi();

		$api->expects($this->exactly(3))
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/test'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->anything(),
			)
			->willReturnOnConsecutiveCalls(
				[
					'success' => false,
					'error' => 'NETWORK ERROR',
				],
				[
					'success' => false,
					'error' => 'NETWORK ERROR',
				],
				[
					'success' => false,
					'error' => 'NETWORK ERROR',
				],
			);

		$api->setAutoRetry(2);

		$response = $api->get('/test');

		$this->assertTrue($response->hasFailed());
		$this->assertEquals([
			'code' => 5007,
    		'message' => 'NETWORK ERROR'
		], $response->getError());

	}

	function test_fail_on_quota() : void  {
		
		$api = $this->mockApi();
		$api->setAutoThrottling(false); // no throttle

		$api->expects($this->exactly(1))
			->method('curlQuery')
			->with(
				$this->anything(),
				$this->equalTo('GET'),
				$this->anything(),
				$this->anything(),
			)
			->willReturn($this->mockQuotaFailure());
		
		$response = $api->get('/test');

		$this->assertFalse($response->hasSucceed());
		$this->assertEquals(4300, $response->getError()['code'] ?? null);
		
	}
	
	function test_manual_throttling() : void  {

		$api = $this->mockApi();
		$api->setAutoThrottling(false);

		$api->expects($this->exactly(3))
			->method('curlQuery')
			->with(
				$this->equalTo('https://api.kiubi.com/v1/rate'),
				$this->equalTo('GET'),
				$this->anything(),
				$this->anything(),
			)
			->willReturnOnConsecutiveCalls(
				$this->mockSuccess(0), // attentempt #1 fail no quota
				$this->mockSuccess(0), // attentempt #2 fail no quota
				$this->mockSuccess(123),	   // attentempt #3 success, response has rate_remaining of 123
			);
		
		$watchDog = 0;
		while($api->getRateRemaining(true) === 0) {
			$watchDog++;
			$this->assertLessThanOrEqual(2, $watchDog);
		}
		
		$this->assertEquals(123, $api->getRateRemaining());
		
	}
	
	function test_auto_throttling() : void  {
		
		$api = $this->mockApi();

		$api->expects($this->exactly(3))
			->method('curlQuery')
			->with(
				$this->anything(),
				$this->equalTo('GET'),
				$this->anything(),
				$this->anything(),
			)
			->willReturnOnConsecutiveCalls(
				$this->mockQuotaFailure(), // attentempt #1 fail no quota
				$this->mockQuotaFailure(), // attentempt #2 fail no quota
				$this->mockSuccess(),	   // attentempt #3 success, response has rate_remaining of 123
			);

		$api->expects($this->exactly(2))->method('wait');

		$api->setAutoRetry(0);
		$api->setAutoThrottling(true);
		
		$response = $api->get('/test');

		$this->assertTrue($response->hasSucceed());
		$this->assertEquals(123, $api->getRateRemaining()); // from $this->mockSuccess()
		
	}

	function test_auto_throttling_exhaustion() : void {
		
		$api = $this->mockApi();
		$api->setAutoThrottling(true);
		$api->expects($this->exactly(4))
			->method('curlQuery')
			->with(
				$this->anything(),
				$this->equalTo('GET'),
				$this->anything(),
				$this->anything(),
			)
			->willReturnOnConsecutiveCalls(
				$this->mockSuccess(0), // query #1 ok but no more remaining query
				$this->mockQuotaFailure(), // query #2 attentempt #1 fail no quota
				$this->mockQuotaFailure(), // query #2 attentempt #2 fail no quota
				$this->mockSuccess(),	   // query #2 attentempt #3 success, response has rate_remaining of 123
			);
		$api->expects($this->exactly(2))->method('wait');

		$response = $api->get('/test'); // ok but no remaining query
		$this->assertTrue($response->hasSucceed());

		$response = $api->get('/test2'); // will throttle
		$this->assertTrue($response->hasSucceed());

	}
	
}
