<?php declare(strict_types=1);

/**
 * @copyright Copyright (c) Kiubi 2021
 */
 
namespace Kiubi;

class Response {

	/**
	 * @var array<string, mixed> 
	 */
	private $headers;

	/**
	 * @var array<string, mixed>
	 */
	private $error;
	
	/**
	 * @var array<string, mixed>
	 */
	private $meta;

	/**
	 * @var mixed|null 
	 */
	private $data;

	/**
	 * Constructor
	 * @param array<string, mixed> $headers
	 * @param string $content
	 */
	public function __construct(array $headers, string $content) {
		
		$this->headers = $headers;
		reset($headers);
		$http_code = (int) current($headers);
		if ($http_code == 500 || $http_code == 413) { // payload are probably not well formed
			$this->meta = array(
				'success' => false,
				'status_code' => $http_code
			);
			return;
		}
	
		$content = json_decode($content, true);

		if (!is_array($content)) {
			$this->meta = array(
				'success' => false,
				'status_code'=>500
			);
			$this->error = array(
				'code' => 5007, // 5007 == UNEXPECTED ERROR
				'message' => 'Unexpected error, payload not found in response'
			);
			$this->data = null;
			return;
		}
        
		$this->error = isset($content['error']) ? $content['error'] : array();
		$this->meta = isset($content['meta']) ? $content['meta'] : array();
		$this->data = isset($content['data']) ? $content['data'] : array();
	}
	
	/**
	 * Returns HTTP headers
	 * @return array<string, mixed>
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Return error if occurs
	 * @return array<string, mixed>|null
	 */
	public function getError() {
		if ($this->hasFailed()) {
			return $this->error;
		}
		return null;
	}
	
	/**
	 * Return response meta data
	 * @return array<string, mixed>
	 */
	public function getMeta() {
		return $this->meta;
	}
	
	/**
	 * Returns response data
	 * @return mixed|null
	 */
	public function getData() {
		return $this->data;
	}
	
	/**
	 * Determine if request has failed
	 * @return Boolean
	 */
	public function hasFailed() {
		return $this->meta['success'] == false;
	}
	
	/**
	 * Determine if request has succeeded
	 * @return Boolean
	 */
	public function hasSucceed() {
		return $this->meta['success'] == true;
	}
	
	/**
	 * Returns response HTTP code
	 * @return int
	 */
	public function getHttpCode() {
		return (int) $this->meta['status_code'];
	}
 }