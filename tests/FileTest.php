<?php

use PHPUnit\Framework\TestCase;

class FileTest extends TestCase {

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

	public function test_file(): void {
		$tmpfile = $this->createTempFile('TEST');
		$File = new \Kiubi\File($tmpfile);
		$this->assertEquals('TEST', $File->getContent());
		$this->assertEquals(basename($tmpfile), $File->getFilename());
		$this->assertEquals('application/octet-stream', $File->getContentType());
		$this->assertEquals(4, $File->getContentSize());
	}
	
	public function test_explicit_content_type(): void {
		$tmpfile = $this->createTempFile('<b>TEST</b>');
		$File = new \Kiubi\File($tmpfile, 'text/html');
		$this->assertEquals('text/html', $File->getContentType());
	}
	
	public function test_unreadable_file(): void {
		$File = new \Kiubi\File('/tmp/doesnotexists');
		$this->assertEquals('doesnotexists', $File->getFilename());
		$this->assertEquals(false, $File->getContent());
		$this->assertEquals('application/octet-stream', $File->getContentType());
		$this->assertEquals(0, $File->getContentSize());
	}
	
}
