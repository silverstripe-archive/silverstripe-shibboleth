<?php

/**
 *	sspConfigLoader.php is supposed to hand environment specific configurations from the _ssp_environment.php file on
 *	to the relevant config files in the simplesaml code. In the _ssp_environment.php should contain the definition of
 *	of one variable only: $env which ist a nested array. 
 *	the first level index is the relative path of the sumplesamlphp config file where the config var is being used.
 *	the second level index is the name of the variable to be set
 *	
 *	example for config/config.php $config['auth.adminpassword'] = 123
 *	$env['config/config.php']['config]['auth.adminpassword'] = 123;
 *
 **/

// debug function for easy, readable and tracable var_dumping
function _dbg($var) {
	$bt = debug_backtrace();
	echo "<pre style='background:#eee; border:1px dashed #ddd; padding:2px; margin:5px 0;'><code style='background:yellow; color:red; font-weight:bold;'>" . $bt[0]['file'] . " (" . $bt[0]['line'] . ")</code>";
	$args = func_get_args();
	if(count($args) == 1) $args = $args[0];
	var_dump($args);
	echo "</pre>";
}

class SspConfigLoader {
	
	private static $env;
	public static $env_path;
	
	private static function get_env() {
		if(empty(self::$env_path)) self::$env_path = __DIR__;
		
		while(empty(self::$env) && strlen(self::$env_path) > 2) {
			$file = self::$env_path . '/_ssp_environment.php';
			if(@file_exists($file)) {
				if(is_readable($file)) {
					include_once($file);
					self::$env = $env;
				} else {
					trigger_error($file . ' is not readable. You might want to check permissions.');
				}
			} else {
				self::$env_path = dirname(self::$env_path);
			}
		}
		
		if(empty(self::$env)) trigger_error('_ssp_environment.php not found. Please set SspConfigLoader::$env_path to the location of the file.');
		
		return self::$env;
	}

	/**
	 *	Get a configuration variable through the SspConfigLoader::get_env_conf() or a default value if no config val is found.
	 *	expects at least 2 parameters:
	 *	@param mixed default value which is returned if the requested var is undefined
	 *	@params mixed params to be passed to @see SspConfigLoader::get_env_conf() for resolution
	 **/
	static function get_default_or_conf() {
		$args = func_get_args();
		if(count($args)<2) trigger_error("SspConfigLoader::get_default_or_conf() expects at least 2 arguments, " . count($args) . " given.", E_USER_ERROR);
		$default = array_shift($args);
		try {
			return self::get_env_conf($args);
		} catch(SspConfigLoaderException $e) {
			return $default;
		}
	}

	/**
	 *	Get a configuration variable from the _ss_enrironment.php file.
	 *	expects at least 1 parameter:
	 *	@param string name of the variable to be configured
	 *	[ @param mixed integer or string value of the index of the variable to be configured, if the variable is a nested array add
	 *			the appropriate number of @param s ]
	 *	Example:
	 *	In order to get the value for $config['auth.adminpassword'] which is to be configured in config/config.php the
	 *	To get the value in the config/config.php file you'd call SspConfigLoader::get_env_conf('config', 'auth.adminpassword')
	 **/
	static function get_env_conf($args) {

		$bt = debug_backtrace();
		$error = $bt[0]['file'] == __FILE__ ? false : E_USER_ERROR;
		while($bt[0]['file'] == __FILE__) array_shift($bt);
	
		// try to resolve the file name where get_env_conf() has been called
		if(preg_match('"' . realpath(dirname(__FILE__) . '/../thirdparty/simplesaml/') . '(.+)"', realpath($bt[0]['file']), $matches)) {

			$indexes = is_array($args) ? $args : func_get_args();
			if(!count($indexes)) self::alert("SspConfigLoader::get_env_conf() expects at least 1 argument, none given.", $error);
			array_unshift($indexes, trim($matches[1], '/'));

			$val = self::get_env();
			foreach($indexes as $index) {
				if(isset($val[$index])) {
					$val = $val[$index];
				} else {
					$file = array_shift($indexes);
					$var = array_shift($indexes);
					self::alert("Can't find the config value for \${$var}[" . implode('][', $indexes) . "] defined in {$bt[0]['file']} on line {$bt[0]['line']}<br />Please set \$env['$file']['$var']['" . implode("']['", $indexes) . "'] in the _ssp_environment.php file<br />", $error);
				}
			}

			return $val;
		}
	
		self::alert("Could not resolve path '{$bt[0]['file']}'", $error);
	}
	
	// handle errors appropriately
	private static function alert($msg, $code) {
		if($code === false) {
			throw new SspConfigLoaderException($msg);
		} else {
			trigger_error($msg, $code);
		}
	}
}

class SspConfigLoaderException extends Exception {}