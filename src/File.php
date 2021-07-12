<?php 

/**
 * @copyright Copyright (c) Kiubi 2021
 */

namespace Kiubi;

class File {
	
	private $path;
	private $mime;

	/**
	 * Constructor
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
		if (is_readable($this->path)) return (int) filesize($this->path);
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
		if (is_readable($this->path)) return file_get_contents($this->path);
		else return "";
	}
	
}
