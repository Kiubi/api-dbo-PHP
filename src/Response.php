<?php

/**
 * @copyright Copyright (c) Kiubi 2021
 */
 
namespace Kiubi;

class Response {
	
	private $headers = array();
	private $error = array();
	private $meta = array();
	private $data = array();

	/**
	 * Constructor
	 * @param array $headers
	 * @param String $content
	 */
	public function __construct($headers, $content) {
		
		$this->headers = $headers;
		reset($headers);
		$http_code = key($headers);
		if (preg_match("=^HTTP/[\d].[\d] ([\d]+) =", $http_code, $regs)) {
			if ($regs[1] == '500') {
				$this->meta = array(
					'success' => false,
					'status_code'=>500
				);
				return;
			}
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
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Return error if occurs
	 * @return array
	 */
	public function getError() {
		if ($this->hasFailed()) {
			return $this->error;
		}
		return null;
	}
	
	/**
	 * Return response meta data
	 * @return array
	 */
	public function getMeta() {
		return $this->meta;
	}
	
	/**
	 * Returns response data
	 * @return array
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
	 * Determine if request has succeed
	 * @return Boolean
	 */
	public function hasSucceed() {
		return $this->meta['success'] == true;
	}
	
	/**
	 * Returns response HTTP code
	 * @return Integer
	 */
	public function getHttpCode() {
		return $this->meta['status_code'];
	}
 }