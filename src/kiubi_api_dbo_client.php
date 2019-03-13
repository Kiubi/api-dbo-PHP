<?php
/**
 * Kiubi Client API Developers
 * @category Kiubi
 * @package  API_DBO
 * @copyright Copyright (c) Kiubi 2019
 */

class Kiubi_API_DBO_Client {
	
	protected $version			= '1.1';
	protected $api_url			= 'https://api.kiubi.com';
	protected $api_version		= 'v1';
	protected $access_token		= '';
	protected $rate_remaining	= 0;
	protected $timeout			= 3;
	
	/**
	 * Kiubi_API_DBO_Client 
	 * @param String $access_token
	 */
	public function __construct($access_token = '') {		
		$this->setAccessToken($access_token);
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
	 * Build query
	 * @param String $method
	 * @param String $endpoint
	 * @param Array $params
	 * @param Array $addionnal_headers
	 * @return Kiubi_API_DBO_Client_Response
	 */
	public function query($method, $endpoint, $params = array(), $addionnal_headers = array()) {
		
		$endpoint = ltrim($endpoint, '/');		
		if(substr($endpoint, 0, strlen($this->api_version)+1)!=$this->api_version.'/') {
			$endpoint = $this->api_version.'/'.$endpoint;
		}
		list($headers, $content) = $this->performQuery($method, $this->api_url.'/'.$endpoint, $params, $addionnal_headers);		
		switch($headers['Content-Type']) {			
			default:
			case 'application/json':
				$response = $this->getJsonResponse($headers, $content);
				if($response instanceof Kiubi_API_DBO_Client_Response) {
					$meta = $response->getMeta();
					$this->rate_remaining = $meta['rate_remaining'];
				}
				return $response;
			break;
		}
	}
	
	/**
	 * Return Json response
	 * @param Array $headers
	 * @param String $content
	 * @return Kiubi_API_DBO_Client_Response
	 */
	protected function getJsonResponse($headers, $content) {
		return new Kiubi_API_DBO_Client_Response($headers, $content);
	}

	/**
	 * Perform query
	 * @param String $method
	 * @param String $url
	 * @param Array $params
	 * @param Array $addionnal_headers
	 * @return Array[Array, String] Table of headers and content as string
	 */
	protected function performQuery($method, $url, $params = array(), $addionnal_headers = array()) {
		
		$curl = curl_init($url);
		
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		
		$headers = (array) $addionnal_headers;
		$headers['X-API'] = 'Kiubi API PHP Client v'.$this->version;
		if($this->access_token) {
			$headers['Authorization'] = 'token '.$this->access_token;
		}
		
		if(count($params)) {		
			// Allow datas on HTTP PUT/DELETE methods
			$params['method'] = $method;
			$method = "POST";
			
			$payload = $this->preparePayload($params);
			$headers = array_merge($headers, $payload['headers']);
			
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload['body']);
		}
		
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->prepareHeaders($headers));		
		
		$response = curl_exec($curl);
		$header_size = curl_getinfo($curl,CURLINFO_HEADER_SIZE);
		curl_close($curl);
		
		$header = substr($response, 0, $header_size);
        $content = substr($response, $header_size);
		
		$headers = array();
		foreach(explode("\r\n", $header) as $h) {
			if(strlen($h)) {
				list($name, $value) = explode(':', $h);
				$headers[trim($name)] = trim($value);
			}
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
			if($value instanceof Kiubi_API_DBO_File) {
				$contains_file = true;
				break;
			}
		}
		
		if($contains_file) {
			$boundary = '---KiubiAPIClientBoundary-' . md5(microtime());
			$headers = array("Content-Type" => "multipart/form-data; boundary={$boundary}");
			
			$body = "";
			
			foreach($flat_params as $name => $value) {
				
				$body .= "--{$boundary}\r\n" . 'Content-Disposition: form-data; name="' . $name . '"';
				
				if($value instanceof Kiubi_API_DBO_File) {
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
     * Converts multidimentional array of params in a simple array
	 * 
     * Brackets [] are added to keys which contains an array
     *
     * @param array $params
     * @param string $prefix Prefix used for recursivity
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
	 * @param Array $params
	 * @param Array $addionnal_headers
	 * @return Object
	 */
	public function get($endpoint, $params = array(), $addionnal_headers = array()) {
		return $this->query('GET', $endpoint, $params, $addionnal_headers);
	}
	
	/**
	 * Perform POST query
	 * @param String $endpoint
	 * @param Array $params
	 * @param Array $addionnal_headers
	 * @return Object
	 */
	public function post($endpoint, $params = array(), $addionnal_headers = array()) {
		return $this->query('POST', $endpoint, $params, $addionnal_headers);
	}
	
	/**
	 * Perform PUT query
	 * @param String $endpoint
	 * @param Array $params
	 * @param Array $addionnal_headers
	 * @return Object
	 */
	public function put($endpoint, $params = array(), $addionnal_headers = array()) {
		return $this->query('PUT', $endpoint, $params, $addionnal_headers);
	}
	
	/**
	 * Perform DELETE query
	 * @param String $endpoint
	 * @param Array $params
	 * @param Array $addionnal_headers
	 * @return Object
	 */
	public function delete($endpoint, $params = array(), $addionnal_headers = array()) {
		return $this->query('DELETE', $endpoint, $params, $addionnal_headers);
	}
	
	/**
	 * Returns number of remaining query allowed
	 * @param Boolean $remote_check
	 * @return Integer
	 */
	public function getRateRemaining($remote_check = false) {
		if($remote_check || !$this->rate_remaining) {			
			$response = $this->get('rate');
			if($response instanceof Kiubi_API_DBO_Client_Response) {
				$meta = $response->getMeta();
				$this->rate_remaining = $meta['rate_remaining'];
			}
		}
		return $this->rate_remaining;
	}
	
	/**
	 * Determine if a request got a next page result
	 * @param Object $response
	 * @return boolean
	 */
	public function hasNextPage($response) {
		if($response instanceof Kiubi_API_DBO_Client_Response) {
			$meta = $response->getMeta();
			if(isset($meta['link']) && $meta['link']['next_page']) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Determine if a request got a previous page result
	 * @param Object $response
	 * @return boolean
	 */
	public function hasPreviousPage($response) {
		if($response instanceof Kiubi_API_DBO_Client_Response) {
			$meta = $response->getMeta();
			if(isset($meta['link']) && isset($meta['link']['previous_page']) && $meta['link']['previous_page']!=false) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Retrive a specific page of a resultset
	 * @param Object $response
	 * @param Integer $num
	 * @return Object
	 */
	public function getPage($response, $num) {
		if($response instanceof Kiubi_API_DBO_Client_Response) {
			$meta = $response->getMeta();
			if(isset($meta['link']) && isset($meta['link']['first_page']) && $meta['link']['first_page']!=false) {
				return $this->get($meta['link']['first_page'].'&page='.(int)$num);
			}
		}
		return false;
	}

	/**
	 * Retrive next page of a resultset
	 * @param Object $response
	 * @return Object
	 */
	public function getNextPage($response) {
		return $this->getNavigationPage($response, 'next_page');
	}
	
	/**
	 * Retrive previous page of a resultset
	 * @param Object $response
	 * @return Object
	 */
	public function getPreviousPage($response) {
		return $this->getNavigationPage($response, 'previous_page');
	}
	
	/**
	 * Retrive first page of a resultset
	 * @param Object $response
	 * @return Object
	 */
	public function getFirstPage($response) {
		return $this->getNavigationPage($response, 'first_page');
	}
	
	/**
	 * Retrive last page of a resultset
	 * @param Object $response
	 * @return Object
	 */
	public function getLastPage($response) {
		return $this->getNavigationPage($response, 'last_page');
	}
	
	/**
	 * Perform a request on a page of a resultset
	 * @param Object $response
	 * @return Object
	 */
	protected function getNavigationPage($response, $page) {
		if($response instanceof Kiubi_API_DBO_Client_Response) {
			$meta = $response->getMeta();
			if(isset($meta['link']) && isset($meta['link'][$page]) && $meta['link'][$page]!=false) {
				return $this->get($meta['link'][$page]);
			}
		}
		return false;
	}
}

class Kiubi_API_DBO_Client_Response {
	
	private $headers = array();
	private $error = array();
	private $meta = array();
	private $data = array();

	/**
	 * Kiubi_API_DBO_Client_Response
	 * @param Array $headers
	 * @param String $content
	 */
	public function __construct($headers, $content) {
		
		$this->headers = $headers;
		reset($headers);
		$http_code = key($headers);
		if(preg_match("=^HTTP/[\d].[\d] ([\d]+) =", $http_code, $regs)) {
			if($regs[1] == '500') {
				$this->meta = array(
					'success' => false,
					'status_code'=>500
				);
				return;
			}
		}
	
		$content = json_decode($content, true);
		$this->error = $content['error'];
		$this->meta = $content['meta'];
		$this->data = $content['data'];
	}
	
	/**
	 * Returns HTTP headers
	 * @return Array
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Return error if occurs
	 * @return Array
	 */
	public function getError() {
		if($this->hasFailed()) {
			return $this->error;
		}
		return null;
	}
	
	/**
	 * Return response meta data
	 * @return Array
	 */
	public function getMeta() {
		return $this->meta;
	}
	
	/**
	 * Returns response data
	 * @return Array
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
 
 class Kiubi_API_DBO_File {
	
	private $path;
	private $mime;

	/**
	 * Kiubi_API_DBO_File
	 * @param string $path Path of the file to send
	 * @param string $mime Mime type of the file
	 */
	public function __construct($path, $mime = "application/octet-stream") {
		$this->path = (string) $path;
		$this->mime = (string) $mime;
	}
	
	/**
	 * Returns file size in bytes
	 * @return int
	 */
	public function getContentSize() {
		if(is_readable($this->path)) return (int) filesize($this->path);
		else return 0;
	}
	
	/**
	 * Returns file name
	 * @return string
	 */
	public function getFilename() {
		return basename($this->path);
	}
	
	/**
	 * Returns file mime type
	 * @return string
	 */
	public function getContentType() {
		return $this->mime;
	}
	
	/**
	 * Returns file content
	 * @return string
	 */
	public function getContent() {
		if(is_readable($this->path)) return file_get_contents($this->path);
		else return "";
	}
	
}
