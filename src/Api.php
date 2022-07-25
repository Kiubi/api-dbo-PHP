<?php 

/**
 * Kiubi Client API Developers
 * @copyright Copyright (c) Kiubi 2021
 */

namespace Kiubi;


class Api {
	
	protected $api_url			= 'https://api.kiubi.com';
	protected $version			= '2.0';
	protected $api_version		= 'v1';
	protected $access_token		= '';
	protected $rate_remaining	= 0;
	protected $timeout			= 3;
	protected $autoThrottling	= false;
	protected $maxRetry			= 0; // Disabled by default
	protected $currentRetry		= 0;

	/**
	 * Constructor
	 * @param String $access_token
	 */
	public function __construct($access_token = '') {		
		$this->setAccessToken($access_token);
	}

	/**
	 * Max retries on connection errors
	 * @param int $retry
	 */
	public function setAutoRetry($retry) {
		$this->maxRetry = $retry;
	}

	/**
	 * Enable or disable auto throttling
	 *
	 * @param boolean $enabled
	 */
	public function setAutoThrottling($enabled) {
		$this->autoThrottling = (boolean) $enabled;
	}

	/**
	 * Set access token
	 * @param String $access_token
	 */
	public function setAccessToken($access_token) {
		$this->access_token = $access_token;
	}
	
	/**
	 * Retrieve access token
	 * @return String
	 */
	public function getAccessToken() {
		return $this->access_token;
	}

	/**
	 * Set connection timeout
	 *
	 * @param int $timeout
	 */
	public function setTimeout($timeout) {
		$this->timeout = (int) $timeout;
	}

	/**
	 * Set API main URL. Default is https://api.kiubi.com
	 *
	 * @param string $api_url
	 */
	public function setUrl($api_url) {
		$this->api_url = $api_url;
	}

	/**
	 * Build query
	 * @param String $method
	 * @param String $endpoint
	 * @param array $params
	 * @param array $additional_headers
	 * @return Response
	 */
	public function query($method, $endpoint, $params = array(), $additional_headers = array()) {

		if ($endpoint !== 'rate' && $this->rate_remaining == 0 && $this->autoThrottling) {
			do {
				$remaining = $this->getRateRemaining(true); // force check
				if ($remaining == 0) {
					sleep(5);
				}
			} while($remaining == 0);
		}

		$endpoint = ltrim($endpoint, '/');		
		if (substr($endpoint, 0, strlen($this->api_version)+1)!=$this->api_version.'/') {
			$endpoint = $this->api_version.'/'.$endpoint;
		}
		list($headers, $content) = $this->performQuery($method, $this->api_url.'/'.$endpoint, $params, $additional_headers);		
		$ct = isset($headers['Content-Type']) ? $headers['Content-Type'] : '';
		switch($ct) {			
			default:
			case 'application/json':
				$response = $this->getJsonResponse($headers, $content);
				if ($response instanceof Response) {
					$meta = $response->getMeta();
					if (isset($meta['rate_remaining'])) $this->rate_remaining = (int) $meta['rate_remaining'];
				}
				return $response;
		}
	}
	
	/**
	 * Return Json response
	 * @param array $headers
	 * @param String $content
	 * @return Response
	 */
	protected function getJsonResponse($headers, $content) {
		return new Response($headers, $content);
	}

	/**
	 * Perform query
	 * @param String $method
	 * @param String $url
	 * @param array $params
	 * @param array $additional_headers
	 * @return array[array, String] Table of headers and content as string
	 */
	protected function performQuery($method, $url, $params = array(), $additional_headers = array()) {

		$curl = curl_init($url);
		
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

		$headers = (array) $additional_headers;
		$headers['X-API'] = 'Kiubi API PHP Client v'.$this->version;
		if ($this->access_token) {
			$headers['Authorization'] = 'token '.$this->access_token;
		}
		
		if (count($params)) {		
			// Allow datas on HTTP PUT/DELETE methods
			if (!isset($params['method'])) $params['method'] = $method;
			$method = "POST";
			
			$payload = $this->preparePayload($params);
			$headers = array_merge($headers, $payload['headers']);
			
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload['body']);
		}
		
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->prepareHeaders($headers));

		$response = curl_exec($curl);

		if ($response===false) {
			if ($this->currentRetry < $this->maxRetry) {
				$this->currentRetry++;
				return $this->performQuery($method, $url, $params, $additional_headers);
			}
			$header = 'Content-Type: application/json';
			$content = json_encode(array(
				'meta'=>array('success'=>false,'status_code'=>500),
				'error'=>array('code'=>5007,'message'=>curl_error($curl)), // 5007 == UNEXPECTED ERROR
			)); // Mimic API Error
		} else {
			$header_size = curl_getinfo($curl,CURLINFO_HEADER_SIZE);
			$header = substr($response, 0, $header_size);
			$content = substr($response, $header_size);
		}
		$this->currentRetry = 0;
		curl_close($curl);

		$headers = array();
		foreach(explode("\r\n", $header) as $h) {
			if (strlen($h) == 0) {
				continue;
			}
			if (strpos($h, 'HTTP/') === 0) {
				$h = explode(' ', $h, 3);
				if (count($h) < 2) {
					continue;
				}
				$h[1] = (int) $h[1];
			} else {
				$h = explode(':', $h, 2);
				if (count($h) != 2) {
					continue;
				}	
			}
			$headers[trim($h[0])] = trim($h[1]);
		}
		return array($headers, $content);
	}
	
	/**
	 * Prepare headers for curl request
	 * @param array $headers
	 * @return array
	 */
	protected function prepareHeaders($headers){
		
		$http_headers = array();
		
		foreach($headers as $name => $value)
		{
			$http_headers[] = $name.": ".$value;
		}
		
		return $http_headers;
		
	}
	
	/**
	 * Create request payload, with files if any
	 * 
	 * @param array $params
	 * @return array Returns an array with 2 keys
	 *   "headers" array List of headers to add to the request
	 *   "body"   string Body of the request
	 */
	protected function preparePayload($params){
		
		$flat_params = $this->flattenParams($params);
		$contains_file = false;
		
		foreach($flat_params as $value) {
			if ($value instanceof File) {
				$contains_file = true;
				break;
			}
		}
		
		if ($contains_file) {
			$boundary = '---KiubiAPIClientBoundary-' . md5(microtime());
			$headers = array("Content-Type" => "multipart/form-data; boundary={$boundary}");
			
			$body = "";
			
			foreach($flat_params as $name => $value) {
				
				$body .= "--{$boundary}\r\n" . 'Content-Disposition: form-data; name="' . $name . '"';
				
				if ($value instanceof File) {
					$body .= '; filename="' . $value->getFilename() . '"' . "\r\n";
					$body .= 'Content-Type: '. $value->getContentType() . "\r\n\r\n";
					$body .= $value->getContent();
				}
				else {
					$body .= "\r\n\r\n";
					$body .= $value;
				}
				
				$body .= "\r\n";
			}
			
			$body .= "--{$boundary}--\r\n";
		}
		else {
			$headers = array("Content-Type" => "application/x-www-form-urlencoded");
			$body = http_build_query($params);
		}
		
		return array(
			"headers" => $headers,
			"body" => $body,
		);
	}

	/**
	 * Converts multidimensional array of params in a simple array
	 *
	 * Brackets [] are added to keys which contains an array
	 *
	 * @param array $params
	 * @param string $prefix Prefix used for recursion
	 * @return array
	 */
	protected function flattenParams($params, $prefix = null)
	{
		$flat_params = array();

		foreach ($params as $name => $value) {

			if ($prefix) {
				$name = $prefix . "[".(is_int($name) ? "" : $name)."]";
			}

			if (is_array($value)) {
				$flat_params = array_merge($flat_params, $this->flattenParams($value, $name));
			}
			else {
				$flat_params[$name] = $value;
			}
		}

		return $flat_params;
	}

	/**
	 * Perform GET query
	 * @param String $endpoint
	 * @param array $params
	 * @param array $additional_headers
	 * @return Response
	 */
	public function get($endpoint, $params = array(), $additional_headers = array()) {
		return $this->query('GET', $endpoint, $params, $additional_headers);
	}
	
	/**
	 * Perform POST query
	 * @param String $endpoint
	 * @param array $params
	 * @param array $additional_headers
	 * @return Response
	 */
	public function post($endpoint, $params = array(), $additional_headers = array()) {
		return $this->query('POST', $endpoint, $params, $additional_headers);
	}
	
	/**
	 * Perform PUT query
	 * @param String $endpoint
	 * @param array $params
	 * @param array $additional_headers
	 * @return Response
	 */
	public function put($endpoint, $params = array(), $additional_headers = array()) {
		return $this->query('PUT', $endpoint, $params, $additional_headers);
	}
	
	/**
	 * Perform DELETE query
	 * @param String $endpoint
	 * @param array $params
	 * @param array $additional_headers
	 * @return Response
	 */
	public function delete($endpoint, $params = array(), $additional_headers = array()) {
		return $this->query('DELETE', $endpoint, $params, $additional_headers);
	}
	
	/**
	 * Returns number of remaining query allowed
	 * @param Boolean $remote_check
	 * @return Integer
	 */
	public function getRateRemaining($remote_check = false) {
		if ($remote_check || !$this->rate_remaining) {			
			$response = $this->get('rate');
			if ($response instanceof Response) {
				$meta = $response->getMeta();
				if (isset($meta['rate_remaining'])) $this->rate_remaining = (int) $meta['rate_remaining'];
			}
		}
		return $this->rate_remaining;
	}
	
	/**
	 * Determine if a request got a next page result
	 * @param Response $response
	 * @return boolean
	 */
	public function hasNextPage($response) {
		if ($response instanceof Response) {
			$meta = $response->getMeta();
			if (isset($meta['link']) && isset($meta['link']['next_page']) && $meta['link']['next_page']) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Determine if a request got a previous page result
	 * @param Response $response
	 * @return boolean
	 */
	public function hasPreviousPage($response) {
		if ($response instanceof Response) {
			$meta = $response->getMeta();
			if (isset($meta['link']) && isset($meta['link']['previous_page']) && $meta['link']['previous_page']!=false) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Retrieve a specific page of a resultset
	 * @param Response $response
	 * @param Integer $num
	 * @return Response|false
	 */
	public function getPage($response, $num) {
		if ($response instanceof Response) {
			$meta = $response->getMeta();
			if (isset($meta['link']) && isset($meta['link']['first_page']) && $meta['link']['first_page']!=false) {
				return $this->get($meta['link']['first_page'].'&page='.(int)$num);
			}
		}
		return false;
	}

	/**
	 * Retrieve next page of a resultset
	 * @param Response $response
	 * @return Response
	 */
	public function getNextPage($response) {
		return $this->getNavigationPage($response, 'next_page');
	}
	
	/**
	 * Retrieve previous page of a resultset
	 * @param Response $response
	 * @return Response
	 */
	public function getPreviousPage($response) {
		return $this->getNavigationPage($response, 'previous_page');
	}
	
	/**
	 * Retrieve first page of a resultset
	 * @param Response $response
	 * @return Response
	 */
	public function getFirstPage($response) {
		return $this->getNavigationPage($response, 'first_page');
	}
	
	/**
	 * Retrieve last page of a resultset
	 * @param Response $response
	 * @return Response
	 */
	public function getLastPage($response) {
		return $this->getNavigationPage($response, 'last_page');
	}
	
	/**
	 * Perform a request on a page of a resultset
	 * @param Response $response
	 * @return Response|false
	 */
	protected function getNavigationPage($response, $page) {
		if ($response instanceof Response) {
			$meta = $response->getMeta();
			if (isset($meta['link']) && isset($meta['link'][$page]) && $meta['link'][$page]!=false) {
				return $this->get($meta['link'][$page]);
			}
		}
		return false;
	}
}
