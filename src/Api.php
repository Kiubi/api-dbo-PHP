<?php declare(strict_types=1);

/**
 * Kiubi Client API Developers
 * @copyright Copyright (c) Kiubi 2026
 */

namespace Kiubi;


class Api {
	
	/** @var string */
	protected $api_url			= 'https://api.kiubi.com';
	
	/** @var string */
	protected $version			= '3.0';
	
	/** @var string  */
	protected $api_version		= 'v1';
	
	/** @var string  */
	protected $access_token		= '';
	
	/** @var int|null  */
	protected $rate_remaining	= null;
	
	/** @var int  */
	protected $timeout			= 3;
	
	/** @var bool  */
	protected $autoThrottling	= false;
	
	/** @var int  */
	protected $maxRetry			= 0; // Disabled by default
	
	/** @var int  */
	protected $currentRetry		= 0;

	/**
	 * Constructor
	 * @param string $access_token
	 * @return void
	 */
	public function __construct(string $access_token = '') {		
		$this->setAccessToken($access_token);
	}

	/**
	 * Max retries on connection errors
	 * @param int $retry
	 * @return void
	 */
	public function setAutoRetry(int $retry) {
		$this->maxRetry = $retry;
	}

	/**
	 * Enable or disable auto throttling
	 *
	 * @param boolean $enabled
	 * @return void
	 */
	public function setAutoThrottling(bool $enabled) {
		$this->autoThrottling = $enabled;
	}

	/**
	 * Set access token
	 * @param string $access_token
	 * @return void
	 */
	public function setAccessToken(string $access_token) {
		$this->access_token = $access_token;
	}
	
	/**
	 * Retrieve access token
	 * @return string
	 */
	public function getAccessToken() {
		return $this->access_token;
	}

	/**
	 * Set connection timeout
	 *
	 * @param int $timeout
	 * @return void
	 */
	public function setTimeout(int $timeout) {
		$this->timeout = $timeout;
	}

	/**
	 * Set API main URL. Default is https://api.kiubi.com
	 *
	 * @param string $api_url
	 * @return void
	 */
	public function setUrl(string $api_url) {
		$this->api_url = $api_url;
	}

	/**
	 * Build query
	 * @param string $method
	 * @param string $endpoint
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $additional_headers
	 * @return Response
	 */
	public function query(string $method, string $endpoint, $params = array(), $additional_headers = array()) {

		$endpoint = ltrim($endpoint, '/');		
		if (substr($endpoint, 0, strlen($this->api_version)+1)!=$this->api_version.'/') {
			$endpoint = $this->api_version.'/'.$endpoint;
		}
		$response = $this->performQuery($method, $this->api_url.'/'.$endpoint, $params, $additional_headers);
		
		$meta = $response->getMeta();
		if (isset($meta['rate_remaining'])) $this->rate_remaining = (int) $meta['rate_remaining'];
		
		return $response;
	}
	
	/**
	 * Return Json response
	 * @param array<string, mixed> $headers
	 * @param string $content
	 * @return Response
	 */
	protected function getJsonResponse(array $headers, string $content) {
		return new Response($headers, $content);
	}

	/**
	 * Perform query
	 * @param string $method
	 * @param string $url
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $additional_headers
	 * @return Response
	 */
	protected function performQuery(string $method, string $url, $params = array(), $additional_headers = array()) {

		if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'])) $method = 'GET';

		$headers = (array) $additional_headers;
		$headers['X-API'] = 'Kiubi API PHP Client v'.$this->version;
		if ($this->access_token) {
			$headers['Authorization'] = 'token '.$this->access_token;
		}

		if (count($params)) {
			$payload = $this->preparePayload($params);
			$headers = array_merge($headers, $payload['headers']);
		}
		
		while(true) {
			
			$result = $this->curlQuery($url, $method, $this->prepareHeaders($headers), $payload['body'] ?? '');

			// Handle Network error and retry
			if ($result['success'] === false) {
				if ($this->currentRetry < $this->maxRetry) {
					$this->currentRetry++;
					continue;
				}
				$rawHeaders = 'Content-Type: application/json';
				$rawContent = (string) json_encode(array(
					'meta'=>array('success'=>false,'status_code'=>500),
					'error'=>array('code'=>5007,'message'=>$result['error']) // 5007 == UNEXPECTED ERROR
				)); // Mimic API Error
			} else {
				$rawHeaders = $result['headers'];
				$rawContent = $result['content'];
			}
			$this->currentRetry = 0;
			
			// Parse Response
			$headers = array();
			foreach(explode("\r\n", $rawHeaders) as $h) {
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
				$headers[trim((string) $h[0])] = trim((string) $h[1]);
			}

			$response = $this->getJsonResponse($headers, $rawContent);
			
			// Quota error
			$retryOnQuota = false;
			if  ((
					$response->getHttpCode() === 429 ||
					($response->getHttpCode() === 403 && ($response->getError()['code'] ?? null) === 4300)
				) && $this->autoThrottling) {
				$this->wait();
				$retryOnQuota = true;
			}

			if (!$retryOnQuota) return $response;
			
		}
		
	}

	/**
	 * @param string $url
	 * @param 'GET'|'POST'|'PUT'|'DELETE' $method
	 * @param string[] $headers
	 * @param string $body
	 * @return array{success: true, headers: string, content: string}|array{success: false, error: string}
	 * @codeCoverageIgnore 
	 */
	protected function curlQuery(string $url, string $method, array $headers, string $body) {
	
		if ($method === 'GET' && strlen($body) > 0) {
			$url .= '?'.$body;
		}
		
		$curl = curl_init($url);

		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
		curl_setopt($curl, CURLOPT_NOSIGNAL, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

		if ($method !== 'GET' && strlen($body) > 0) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		}

		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($curl);

		if ($response === false) {
			return [
				'success' => false,
				'error' => curl_error($curl)
			];
		}
		
		$response = (string) $response;
		$header_size = curl_getinfo($curl,CURLINFO_HEADER_SIZE);
		$headers = substr($response, 0, $header_size);
		$body = substr($response, $header_size);
	
		curl_close($curl);
		
		return [
			'success' => true,
			'headers' => $headers,
			'content' => $body
		];
	}

	/**
	 * @return void
	 * @codeCoverageIgnore
 	*/
	protected function wait() {
		sleep(5);	
	}
	
	/**
	 * Prepare headers for curl request
	 * @param array<string, mixed> $headers
	 * @return string[]
	 */
	protected function prepareHeaders(array $headers){
		
		$http_headers = array();
		
		foreach($headers as $name => $value)
		{
			$http_headers[] = $name.": ".((string) $value);
		}
		
		return $http_headers;
		
	}
	
	/**
	 * Create request payload, with files if any
	 * 
	 * @param array<string, mixed> $params
	 * @return array{headers: array<string, mixed>, body: string} Returns an array with 2 keys
	 *   "headers" array List of headers to add to the request
	 *   "body"   string Body of the request
	 */
	protected function preparePayload(array $params){
		
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
					$body .= (string) $value;
				}
				
				$body .= "\r\n";
			}
			
			$body .= "--{$boundary}--\r\n";
		}
		else {
			$headers = array("Content-Type" => "application/x-www-form-urlencoded");
			$body = http_build_query($params); // http_build_query($params, '', '&', PHP_QUERY_RFC3986);
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
	 * @param array<string, mixed> $params
	 * @param string $prefix Prefix used for recursion
	 * @return array<string, mixed>
	 */
	protected function flattenParams(array $params, $prefix = null)
	{
		$flat_params = array();

		foreach ($params as $name => $value) {

			if ($prefix) {
				$name = $prefix . "[".(is_numeric($name) ? "" : $name)."]";
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
	 * @param string $endpoint
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $additional_headers
	 * @return Response
	 */
	public function get(string $endpoint, $params = array(), $additional_headers = array()) {
		return $this->query('GET', $endpoint, $params, $additional_headers);
	}
	
	/**
	 * Perform POST query
	 * @param string $endpoint
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $additional_headers
	 * @return Response
	 */
	public function post(string $endpoint, $params = array(), $additional_headers = array()) {
		return $this->query('POST', $endpoint, $params, $additional_headers);
	}
	
	/**
	 * Perform PUT query
	 * @param string $endpoint
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $additional_headers
	 * @return Response
	 */
	public function put(string $endpoint, $params = array(), $additional_headers = array()) {
		return $this->query('PUT', $endpoint, $params, $additional_headers);
	}
	
	/**
	 * Perform DELETE query
	 * @param string $endpoint
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $additional_headers
	 * @return Response
	 */
	public function delete(string $endpoint, $params = array(), $additional_headers = array()) {
		return $this->query('DELETE', $endpoint, $params, $additional_headers);
	}
	
	/**
	 * Returns number of remaining query allowed
	 * @param Boolean $remote_check
	 * @return int
	 */
	public function getRateRemaining(bool $remote_check = false) {
		if ($remote_check || $this->rate_remaining === 0 || $this->rate_remaining === null) {			
			$this->get('rate');
		}
		return (int) $this->rate_remaining;
	}
	
	/**
	 * Determine if a request got a next page result
	 * @param Response $response
	 * @return boolean
	 */
	public function hasNextPage(Response $response) {
		$meta = $response->getMeta();
		if (isset($meta['link']['next_page']) && $meta['link']['next_page']) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Determine if a request got a previous page result
	 * @param Response $response
	 * @return boolean
	 */
	public function hasPreviousPage(Response $response) {
		$meta = $response->getMeta();
		if (isset($meta['link']['previous_page']) && $meta['link']['previous_page'] != false) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Retrieve a specific page of a resultset
	 * @param Response $response
	 * @param int $num
	 * @return Response|false
	 */
	public function getPage(Response $response, int $num) {
		$meta = $response->getMeta();
		if (isset($meta['link']['first_page']) && $meta['link']['first_page'] != false) {
			return $this->get($meta['link']['first_page'].'&page=' . $num);
		}
		
		return false;
	}

	/**
	 * Retrieve next page of a resultset
	 * @param Response $response
	 * @return Response|false
	 */
	public function getNextPage(Response $response) {
		return $this->getNavigationPage($response, 'next_page');
	}
	
	/**
	 * Retrieve previous page of a resultset
	 * @param Response $response
	 * @return Response|false
	 */
	public function getPreviousPage(Response $response) {
		return $this->getNavigationPage($response, 'previous_page');
	}
	
	/**
	 * Retrieve first page of a resultset
	 * @param Response $response
	 * @return Response|false
	 */
	public function getFirstPage(Response $response) {
		return $this->getNavigationPage($response, 'first_page');
	}
	
	/**
	 * Retrieve last page of a resultset
	 * @param Response $response
	 * @return Response|false
	 */
	public function getLastPage(Response $response) {
		return $this->getNavigationPage($response, 'last_page');
	}
	
	/**
	 * Perform a request on a page of a resultset
	 * @param Response $response
	 * @param string $page
	 * @return Response|false
	 */
	protected function getNavigationPage(Response $response, string $page) {
		$meta = $response->getMeta();
		if (isset($meta['link'][$page]) && $meta['link'][$page] != false) {
			return $this->get($meta['link'][$page]);
		}

		return false;
	}
}
