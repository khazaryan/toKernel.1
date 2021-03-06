<?php
/**
 * toKernel - Universal PHP Framework.
 * Library for caching on filesystem.
 *
 * This file is part of toKernel.
 *
 * toKernel is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * toKernel is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with toKernel. If not, see <http://www.gnu.org/licenses/>.
 *
 * @category   library
 * @package    framework
 * @subpackage library
 * @author     toKernel development team <framework@tokernel.com>
 * @copyright  Copyright (c) 2017 toKernel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @version    2.1.1
 * @link       http://www.tokernel.com
 * @since      File available since Release 1.0.0
 */

/* Restrict direct access to this file */
defined('TK_EXEC') or die('Restricted area.');

/**
 * filecache_lib class library.
 *  
 * @author David Ayvazyan <tokernel@gmail.com>
 */
class filecache_lib {

/**
 * Library object for working with 
 * libraries in this class
 * 
 * @var object
 * @access protected
 */ 
 protected $lib;
 
/**
 * Main Application object for 
 * accessing app functions from this class
 * 
 * @var object
 * @access protected
 */ 
 protected $app;

/**
 * Cache expiration by minutes.
 * 
 * xxx mintes
 * 0 disabled
 * -1 never expire
 * 
 * @access protected
 * @var integer
 */ 
 protected $cache_expiration = 0;

/**
 * Cache file extension.
 * 
 * @access protected
 * @var string
 */
 protected $ext = '';
 
 /**
  * Cache directory
  * 
  * @access protected
  * @var string
  * @since 2.0.0
  */
 protected $cache_dir = '';
 
/**
 * Class constructor
 * 
 * @access public
 * @param mixed $config
 * @return void
 */ 
 public function __construct($config = array()) {

 	$this->lib = lib::instance();
    $this->app = app::instance();
    
    /* Set cache expiration */
	if(isset($config['cache_expiration'])) {
		$ce_ = $config['cache_expiration'];
	} else {
		$ce_ = $this->app->config('cache_expiration', 'CACHING');
	}
    
    if($this->lib->valid->digits($ce_) or $ce_ == '-1') {
    	$this->cache_expiration = $ce_;
    }

    /* Set cache file extension */
	if(isset($config['cache_file_extension'])) {
		$this->ext = $config['cache_file_extension'];
	} else {
		$this->ext = $this->app->config('cache_file_extension', 'CACHING');
	}
    
	/* Set cache directory */
	$this->cache_dir = $this->app->config('cache_dir', 'CACHING');
	
	/* Set subdirectory */
	if(isset($config['subdirectory'])) {
		$this->cache_dir .= $config['subdirectory'] . TK_DS;
		
		/* Check if the subdirectory is not exists, create it. */
		if(!is_dir($this->cache_dir)) {
		
			if(!@mkdir($this->cache_dir)) {
				trigger_error('Cannot create cache directory: ' . $this->cache_dir);
			}
			
		}
	}
    
 } // end func __construct 
 
 /**
  * Return cloned copy of this object
  *
  * @access public
  * @param mixed $config
  * @return object
  * @since 2.0.0
  */
 public function instance($config = array()) {
 	
	$obj = clone $this;
	$obj->__construct($config);

	return $obj;
	
 } // End func instance
	
/**
 * Return cache file expiration status.
 * Expiration time defined in application configuration 
 * file application.ini defined in [CACHING] section.
 * 
 * @access public
 * @param string $file_id
 * @param integer $minutes
 * @return bool
 */ 
 public function expired($file_id, $minutes = NULL) {
	
 	/* Set cache file path/name with extension */
	$file = $this->filename($file_id);
	
	if(!is_file($file)) {
		return true;
	}
	
	/* 
	 * if minutes is not set, then set 
	 * minutes from app configuration 
	 */
	if(is_null($minutes)) {
		$minutes = $this->cache_expiration;
	}
	
	/* -1 assume that the cache never expire */ 
	if($minutes == '-1') {
		return false;
	}
		
	/* Set seconds */
	$exp_sec = $minutes * 60;
	
	/* Get file time */
    $file_time = filemtime($file);

    /* Return true if cache expired */
	if(time() > ($exp_sec + $file_time)) {
		$this->remove($file_id);
		return true;
	} else { 
		return false;
	}

 } // end func expired
 
/**
 * Read and return cached file content if exist.
 * Return false if cache is expired.
 * 
 * @access public
 * @param string $file_id
 * @param integer $minutes
 * @return mixed string | bool
 */ 
 public function get_content($file_id, $minutes = NULL) {
	
 	/* Return false if expired */
	if($this->expired($file_id, $minutes) === true) {
		return false;
	}
	
	/* Set cache file path/name with extension */
	$file = $this->filename($file_id);

	/* Return false if file is not readable */ 
	if(!is_readable($file)) {
		return false;
	}

	return file_get_contents($file);
	
 } // end func get_content
 
/**
 * Write cache content if expired.
 * 
 * @access public 
 * @param string $file_id
 * @param string $buffer
 * @param integer $minutes
 * @return bool
 */ 
 public function write_content($file_id, $buffer, $minutes = NULL) {

    /* If cache disabled, than return false */
    if($this->cache_expiration == 0) {
        return false;
    }

 	/* Try to put content if cache expired */
	if($this->expired($file_id, $minutes)) {
		
		/* Set cache file path/name with extension */
		$file = $this->filename($file_id);

		if(@file_put_contents($file, $buffer)) {
			return true;
		} else {
			trigger_error('Can not write cache content: '. $file . ' (ID: ' . 
						$file_id . ')', E_USER_WARNING);
			return false;
		} 
		
	} // end if expired
		
	return true;
} // end func write_content

/**
 * Remove cache file.
 * 
 * @access public
 * @param string $file_id
 * @return bool
 */ 
 public function remove($file_id) {
	
 	/* Set cache file path/name with extension */
	$file = $this->filename($file_id);
	
	if(is_writable($file)) {
		unlink($file);
		return true;
	} else {
		return false;
	}
	
 } // end func remove
 
/**
 * Clean all cache files
 * 
 * @access public
 * @return integer deleted files count
 */ 
 public function clean_all() {
    
 	$del_files_count = 0;
 	
 	if(!is_writable($this->cache_dir)) {
 		return false;
 	}
 	
 	/* create a handler for the directory */
    $handler = opendir($this->cache_dir);

    /* open directory and walk through the filenames */
    while($file = readdir($handler)) {

      /*
       * if file isn't this directory or its parent, 
       * also if it have .cache extension, then remove it 
       */
      if($file != "." && $file != ".." && pathinfo($file, PATHINFO_EXTENSION) == $this->ext) {
         
		if(unlink($this->cache_dir . $file)) {
			$del_files_count++;
        }
		
      } // end if cache file

    } // end while

    // tidy up: close the handler
    closedir($handler);

    return $del_files_count;
 	 	
 } // end func clean_all
 
/**
 * Make cache file name with path and extendion.
 * 
 * @access public
 * @param string $string
 * @return mixed string | bool
 */
 protected function filename($file_id) {
	
	if(trim($file_id) == '') {
		return false;
	}
	
 	return $this->cache_dir . md5($file_id) . '.' . $this->ext;

 } // end func filename
 
/**
 * Get cache files statistics 
 * 
 * @access public
 * @return array
 * @since 2.1.0
 */ 
 public function stats() {
	 
	$files_count = 0;
 	$total_size = 0;
	
 	if(!is_writable($this->cache_dir)) {
 		return false;
 	}
 	
 	/* create a handler for the directory */
    $handler = opendir($this->cache_dir);

    /* open directory and walk through the filenames */
    while($file = readdir($handler)) {

      /*
       * if file isn't this directory or its parent, 
       * also if it have .cache extension, then count it 
       */
      if($file != "." && $file != ".." && pathinfo($file, PATHINFO_EXTENSION) == $this->ext) {
         
		  $files_count++;
		  $total_size += filesize($this->cache_dir . $file);
		
      } // end if cache file

    } // end while

    // tidy up: close the handler
    closedir($handler);

	return array(
		'files_count' => $files_count,
		'bytes' => $total_size
	);
	 
 } // End func stats
 
/* End of class cache_lib */ 
}

/* End of file */
?>