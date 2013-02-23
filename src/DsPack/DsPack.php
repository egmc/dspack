<?php
namespace DsPack;

use Aws\S3\S3Client;

class DsPack {
	
	protected $config = [];
	
	protected $tar_bin = "tar";
	
	protected $tar_option = "czf";
	
	protected $archives = [];
	
	public function __construct(array $config) {
		$this->config = array_merge($this->getDefaultSettins(), $config);
	}
	
	public function run() {
		if (!is_dir($this->config['work_dir'])) {
			mkdir($this->config['work_dir'], 0777, true);
		}
		if (!is_dir($this->config['work_dir']) || !is_writable($this->config['work_dir'])) {
			throw new Exception("{$this->config['work_dir']} is not writable dir");
		}
		$this->clearWorkDir();
		$this->makeArchives();
		$this->sendArchives();
	}
	
	protected function makeArchives() {
		$this->archives = [];
		foreach ($this->config['source_list'] as $source) {
			$filename = isset($soure['filename']) ? $soure['filename'] : pathinfo($source['target'], PATHINFO_BASENAME) . ".tar.gz";
			$filepath = implode("/", [$this->config['work_dir'], $filename]);
			switch ($source['type']) {
				case 'dir':
					$command = implode(" ", [$this->tar_bin, $this->tar_option, $filepath, basename($source['target'])]);
					echo "$command\n";
					chdir(dirname($source['target']));
					shell_exec($command);
					break;
				case 'db':
					// TODO db command
					break;
				default:
					throw new Exception("unknown type {$source['type']}");
			}
			$this->archives[] = $filepath;
		}
	}
	
	protected function sendArchives() {
		foreach ($this->config['target_list'] as $target) {
			switch ($target['type']){
				case 's3':
					$s3 = S3Client::factory([
						'key' => $target['key'],
						'secret' => $target['secret'],
					]);
					foreach ($this->archives as $archive) {
						$result = $s3->putObject([
								'Bucket' => $target['bucket'],
								'Key' => pathinfo($archive, PATHINFO_BASENAME),
								'Body' => file_get_contents($archive)
						]);
					}
					
					break;
				default:
					throw new Exception("invalid target type {$target['type']}");
			}
		}
	}
	
	protected function getDefaultSettins() {
		return [
			'work_dir' =>realpath( __DIR__ ."/../..") ."/tmp",
		];
	}
	
	protected function checkConfig() {
		
	}
	
	
	protected function clearWorkDir() {
		return shell_exec("rm -fr " . $this->config['work_dir'] . "/*");
	}
	
}