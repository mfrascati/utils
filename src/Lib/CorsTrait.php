<?php
namespace Entheos\Utils\Lib;

/**
 * Trait for simplyfing handle of CORS headers and preflight response
 * Add it to AppController::initialize()
 */
trait CorsTrait {

	public function setCorsHeaders($allowedOrigin = null)
	{
		if(empty($allowedOrigin))
			$allowedOrigin = \Cake\Routing\Router::fullBaseUrl();
	    header("Access-Control-Allow-Origin: $allowedOrigin");

		// Allow from any origin
		if (isset($_SERVER['HTTP_ORIGIN'])) {
		    header('Access-Control-Allow-Credentials: true');
		    header('Access-Control-Max-Age: 86400');    // cache for 1 day
		}
		// Access-Control headers are received during OPTIONS requests
		if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

		    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
		        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");         

		    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
		        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

		    exit();
		}
	}
}