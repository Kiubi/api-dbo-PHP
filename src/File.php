<?php declare(strict_types=1);

/**
 * @copyright Copyright (c) Kiubi 2021
 */

namespace Kiubi;

class File {
	
	/** @var string */
	private $path;
	
	/** @var string */
	private $mime;

	/**
	 * Constructor
	 * @param string $path Path of the file to send
	 * @param string $mime Mime type of the file
	 */
	public function __construct(string $path, string $mime = "application/octet-stream") {
		$this->path = $path;
		$this->mime = $mime;
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
	 * @return false|string
	 */
	public function getContent() {
		if (!is_readable($this->path)) return false;
		return file_get_contents($this->path);
	}
	
}
