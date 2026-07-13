<?php

use Kiubi\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase {
	
	function providerMalformed() {
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
	 * @param $headers
	 * @param $content
	 * @param $expectedStatus
	 * @param $expectedCode
	 * @return void
	 * @dataProvider providerMalformed
	 */
	function test_malformed($headers, $content, $expectedStatus, $expectedCode) {
		
		$Response = new Kiubi\Response($headers, $content);
		
		$this->assertEquals($expectedStatus, $Response->getHttpCode());
		$this->assertTrue($Response->hasFailed());
		$this->assertEquals($expectedCode, $Response->getError()['code']);
	}

	function providerValid() {
		return [
			[['HTTP 1.1' => 200, 'Content-Type' => 'application/json'], '{"meta":{"success": true},"data":["test"]}', 200, ['test']],
			[['HTTP 1.1' => 400, 'Content-Type' => 'application/json'], '{"meta":{"success": false},"data":["test"]}', 400, ['test']],
		];
	}

	/**
	 * @param $headers
	 * @param $content
	 * @param $expectedStatus
	 * @param $expectedCode
	 * @return void
	 * @dataProvider providerValid
	 */
	function test_valid($headers, $content, $expectedStatus, $expectedData) {

		$Response = new Kiubi\Response($headers, $content);

		$this->assertEquals($expectedStatus, $Response->getHttpCode());
		$this->assertEquals($expectedStatus === 200, $Response->hasSucceed());
		$this->assertEquals(['status_code' => $expectedStatus, 'success'=> $expectedStatus === 200], $Response->getMeta());
		$this->assertEquals($expectedData, $Response->getData());
	}

}
