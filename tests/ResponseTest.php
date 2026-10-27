<?php

use Kiubi\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase {

	/**
	 * @return array<int, list<array<string, int|string>|int|string>>
	 */
	function providerMalformed(): array {
		return [
			[[], '', 500, 5007],
			[['HTTP 1.1' => 413], 'Too Large', 413, 4013],
			[['HTTP 1.1' => 413], 'Too Large', 413, 4013],
			[['HTTP 1.1' => 500], 'Crash', 500, 5007],
			[['HTTP 1.1' => 200, 'Content-Type' => 'text/html'], '<b>Should not happen</b>', 500, 5007],
			[['HTTP 1.1' => 200, 'Content-Type' => 'application/json'], 'MALFORMED', 500, 5007],
		];
	}

	/**
	 * @param array<string, string> $headers
	 * @param $content
	 * @param $expectedStatus
	 * @param $expectedCode
	 * @return void
	 * @dataProvider providerMalformed
	 */
	function test_malformed(array $headers, string $content, int $expectedStatus, int $expectedCode): void {
		
		$Response = new Kiubi\Response($headers, $content);
		
		$this->assertEquals($expectedStatus, $Response->getHttpCode());
		$this->assertTrue($Response->hasFailed());
		$this->assertEquals($expectedCode, $Response->getError()['code'] ?? null);
	}

	/**
	 * @return array<int, array{array<string, int|string>|int|string}>
	 */
	function providerValid(): array {
		return [
			[['HTTP 1.1' => 200, 'Content-Type' => 'application/json'], '{"meta":{"success": true},"data":["test"]}', 200, ['test']],
			[['HTTP 1.1' => 400, 'Content-Type' => 'application/json'], '{"meta":{"success": false},"data":["test"]}', 400, ['test']],
		];
	}

	/**
	 * @param array<string, string> $headers
	 * @param $content
	 * @param $expectedStatus
	 * @param array<int, string> $expectedData
	 * @return void
	 * @dataProvider providerValid
	 */
	function test_valid(array $headers, string $content, int $expectedStatus, array $expectedData): void {

		$Response = new Kiubi\Response($headers, $content);

		$this->assertEquals($expectedStatus, $Response->getHttpCode());
		$this->assertEquals($expectedStatus === 200, $Response->hasSucceed());
		$this->assertEquals(['status_code' => $expectedStatus, 'success'=> $expectedStatus === 200], $Response->getMeta());
		$this->assertEquals($expectedData, $Response->getData());
	}

}
