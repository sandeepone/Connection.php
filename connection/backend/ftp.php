<?php
/**
 * FTP connections using PHPs ftp-functions
 * 
 * @author Tuomas Angervuori <tuomas.angervuori@gmail.com>
 * @note Does not support connections through proxy
 * @license http://opensource.org/licenses/LGPL-3.0 LGPL v3
 */

namespace Connection\Backend;

require_once(dirname(__FILE__) . '/../backend.php');
require_once(dirname(__FILE__) . '/../exception.php');

class FTP implements \Connection\Backend {
	
	protected $conn;
	
	/**
	 * Establish a connection
	 * @todo Options...
	 */
	public function __construct($url, array $options = null) {
		
		$data = parse_url($url);
		
		//Establish connection to the server
		if(isset($data['scheme']) && strtolower($data['scheme']) == 'ftps') {
			if(isset($data['port']) && $data['port']) {
				$this->conn = ftp_ssl_connect($data['host'], $data['port']);
				if(!$this->conn) {
					throw new \Connection\Exception("Could not connect to 'ftps://{$data['host']}:{$data['port']}'");
				}
			}
			else {
				$this->conn = ftp_ssl_connect($data['host']);
				if(!$this->conn) {
					throw new \Connection\Exception("Could not connect to 'ftps://{$data['host']}'");
				}
			}
		}
		else {
			if(isset($data['port']) && $data['port']) {
				$this->conn = ftp_connect($data['host'], $data['port']);
				if(!$this->conn) {
					throw new \Connection\Exception("Could not connect to 'ftp://{$data['host']}:{$data['port']}'");
				}
			}
			else {
				$this->conn = ftp_connect($data['host']);
				if(!$this->conn) {
					throw new \Connection\Exception("Could not connect to 'ftp://{$data['host']}'");
				}
			}
		}
		
		//Provide username and password
		if(isset($data['user'])) {
			$user = urldecode($data['user']);
		}
		else {
			$user = 'anonymous';
		}
		if(isset($data['pass'])) {
			$pass = urldecode($data['pass']);
		}
		else {
			$pass = '';
		}
		if(!ftp_login($this->conn, $user, $pass)) {
			throw new \Connection\Exception("Could not login to '{$data['host']}' as '$user'");
		}
		
		//Use firewall friendly passive mode
		if(!ftp_pasv($this->conn, true)) {
			trigger_error("Passive mode failed",\E_USER_WARNING);
		}
		
		//Go to defined directory
		if(isset($data['path']) && $data['path']) {
			$this->cd(dirname($data['path']));
		}
	}
	
	/**
	 * Close connection on exit
	 */
	public function __destruct() {
		if($this->conn) {
			ftp_close($this->conn);
		}
	}
	
	/**
	 * Change directory
	 */
	public function cd($directory) {
		if(!ftp_chdir($this->conn, $directory)) {
			throw new \Connection\Exception("Changing directory to '$directory' failed");
		}
	}
	
	/**
	 * Download a file 
	 */
	public function get($remoteFile) {
		$file = tmpfile();
		if(!ftp_fget($this->conn, $file, $remoteFile, \FTP_BINARY)) {
			throw new \Connection\Exception("Could not download file '$remoteFile'");
		}
		$data = '';
		fseek($file, 0);
		while(!feof($file)) {
			$data .= fread($file,8192);
		}
		fclose($file);
		return $data;
	}
	
	/**
	 * Upload a file 
	 */
	public function put($data, $remoteFile) {
		$file = tmpfile();
		fwrite($file,$data);
		fseek($file,0);
		if(!ftp_fput($this->conn, $remoteFile, $file, \FTP_BINARY)) {
			throw new \Connection\Exception("Could not upload file '$remoteFile'");
		}
		fclose($file);
	}
	
	/**
	 * List current directory
	 * @todo add more info about files (size: ftp_size, modified: ftp_mdtm, is directory...)
	 */
	public function ls() {
		$dir = ftp_nlist($this->conn, '.');
		if($dir === false) {
			throw new \Connection\Exception("Listing directory failed");
		}
		return $dir;
	}
	
	/**
	 * Delete file from remote server
	 */
	public function rm($remoteFile) {
		if(!ftp_delete($this->conn, $remoteFile)) {
			throw new \Connection\Exception("Could not remove file '$remoteFile'");
		}
	}
	
	/**
	 * Rename file in remote server
	 */
	public function mv($remoteFile, $newName) {
		if(!ftp_rename($this->conn, $remoteFile, $newName)) {
			throw new \Connection\Exception("Could not rename file '$remoteFile' as '$newName'");
		}
	}
	
	/**
	 * Create a directory in remote server
	 */
	public function mkdir($dirName) {
		if(!ftp_mkdir($this->conn, $dirName)) {
			throw new \Connection\Exception("Could not create directory '$dirName'");
		}
	}
	
	/**
	 * Remove a directory from remote server
	 */
	public function rmdir($dirName) {
		if(!ftp_rmdir($this->conn, $dirName)) {
			throw new \Connection\Exception("Could not remove directory '$dirName'");
		}
	}
	
	/**
	 * Return array of supported protocols
	 */
	public static function getAvailableProtocols() {
		$protocols = array();
		if(function_exists('ftp_connect')) {
			$protocols[] = 'ftp';
		}
		if(function_exists('ftp_ssl_connect')) {
			$protocols[] = 'ftps';
		}
		return $protocols;
	}
}

