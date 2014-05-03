<?php
namespace DsPack;

use Aws\S3\S3Client;
use Aws\Common\Enum\Size;
use Aws\S3\Model\MultipartUpload\UploadBuilder;

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
			$filename = isset($source['filename']) ? $source['filename'] : pathinfo($source['target'], PATHINFO_BASENAME);
			if (!empty($this->config['filename_date_format'])) {
				$filename .= "." . (new \DateTime())->format($this->config['filename_date_format']);
			}
			if ($source['type'] == "db") {
				$filename .= ".dump";
				$sqlpath = $filename . ".sql";
			}
			$filename .= ".tar.gz";
			$filepath = implode("/", [$this->config['work_dir'], $filename]);
			switch ($source['type']) {
				case 'dir':
					$command = implode(" ", [$this->tar_bin, $this->tar_option, $filepath, basename($source['target'])]);
					if (isset($source['exclude_list']) && is_array($source['exclude_list'])) {
						$command .= implode('', array_map(function($ex){return " --exclude $ex";}, $source['exclude_list']));
					}
					$this->out($command);
					chdir(dirname($source['target']));
					shell_exec($command);
					break;
				case 'db':
					$command = "mysqldump ";
					$command .= "-u{$source['user']}";
					if (isset($source['pass'])) {
						$command .= " -p{$source['pass']}";
					}
					$command .= " {$source['target']} > $sqlpath";
					$this->out($command);
					shell_exec($command);
					chdir(dirname($sqlpath));
					$tar_command =  implode(" ", [$this->tar_bin, $this->tar_option, $filepath, basename($sqlpath)]);
					$this->out($tar_command);
					shell_exec($tar_command);
					unlink($sqlpath);
					
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
						$uploader = UploadBuilder::newInstance();
						$uploader->setClient($s3);
						$uploader->setSource($archive);
						$uploader->setBucket($target['bucket']);
						$uploader->setKey( pathinfo($archive, PATHINFO_BASENAME));
						$uploader->setMinPartSize(10 * Size::MB);
						$uploader->build()->upload();
												
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
	
	/**
	 * 標準出力
	 *
	 * 標準出力を実行。
	 * サイレントモード時は出力しない。
	 *
	 * @param string $str
	 */
	protected function out($str) {
		if(empty($this->config['silent_mode']) || $this->config['silent_mode'] == false) {
			echo "$str\n";
		}
	}
}