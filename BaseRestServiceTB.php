<?php

require(dirname(__FILE__) . '/Route.php');

/*
 * Base class for REST services
 * @author mathias@tela-botanica.org
 * @date 08/2015, 02/2020
 */
abstract class BaseRestServiceTB {

	/** Configuration given at construct time */
	protected $config;

	/** Set to true if the script is called over HTTPS */
	protected $isHTTPS;

	/** HTTP verb received (GET, POST, PUT, DELETE, OPTIONS) */
	protected $verb;

	/** Resources (URI elements) */
	protected $resources = array();

	/** Request parameters (GET or POST) */
	protected $params = array();

	/** Domain root (to build URIs) */
	protected $domainRoot;

	/** Base URI (to parse resources) */
	protected $baseURI;

	/** First resource separator (to parse resources) */
	protected $firstResourceSeparator;

	/** List of registered Route objects, for each HTTP verb */
	protected $routes;

	public function __construct($config) {
		$this->config = $config;

		// Is the script called over HTTPS ? Tricky !
		$this->isHTTPS = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != 'off'));

		// HTTP method
		$this->verb = $_SERVER['REQUEST_METHOD'];

		// server config
		$this->domainRoot = $this->config['domain_root'];
		$this->baseURI = $this->config['base_uri'];
		$this->firstResourceSeparator = "/";
		if (!empty ($this->config['first_resource_separator'])) {
			$this->firstResourceSeparator = $this->config['first_resource_separator'];
		}

		$this->routes = [
			"GET" => [],
			"POST" => [],
			"PUT" => [],
			"PATCH" => [],
			"DELETE" => [],
			"OPTIONS" => []
		];

		// initialization
		$this->routes();
		$this->getResources();
		$this->getParams();

		$this->init();
	}

	/** Define your routes here by calling $this->get(), $this->post(), … */
	protected abstract function routes();

	/** Post-constructor adjustments */
	protected function init() {
	}

	/**
	 * Adds a route
	 * 
	 * @param string $httpVerb the HTTP verb to bind the route to
	 * @param string $scheme
	 * @param callable $function
	 */
	protected function addRoute($httpVerb, $scheme, $function) {
		if (! in_array($httpVerb, array_keys($this->routes))) {
			throw new Exception("unsupported method: $httpVerb");
		}
		array_push($this->routes[$httpVerb], new Route($scheme, $function));
		$this->sortRoutes($httpVerb);
	}

	/** adds a route for GET HTTP verb */
	protected function get($scheme, $function) {
		$this->addRoute("GET", $scheme, $function);
	}

	/** adds a route for POST HTTP verb */
	protected function post($scheme, $function) {
		$this->addRoute("POST", $scheme, $function);
	}

	/** adds a route for PUT HTTP verb */
	protected function put($scheme, $function) {
		$this->addRoute("PUT", $scheme, $function);
	}

	/** adds a route for PATCH HTTP verb */
	protected function patch($scheme, $function) {
		$this->addRoute("PATCH", $scheme, $function);
	}

	/** adds a route for DELETE HTTP verb */
	protected function delete($scheme, $function) {
		$this->addRoute("DELETE", $scheme, $function);
	}

	/** adds a route for OPTIONS HTTP verb @WARNING might break CORS */
	protected function options($scheme, $function) {
		$this->addRoute("OPTIONS", $scheme, $function);
	}

	/**
	 * Sorts routes for a given HTTP verb, so that the most "complex"
	 * routes are found first
	 */
	protected function sortRoutes($httpVerb) {
		usort($this->routes[$httpVerb], function ($a, $b) {
			// 1. more URI scheme parts -> better rank
        	$aParts = Route::extractSchemeParts($a->scheme);
        	$bParts = Route::extractSchemeParts($b->scheme);
			if (count($aParts) < count($bParts)) {
				return 1;
			} elseif (count($aParts) > count($bParts)) {
				return -1;
			} else {
				// 2. more URI scheme parameters -> better rank
				$aParams = 0;
				foreach ($aParts as $ap) {
					if (substr($ap, 0, 1) === ":") {
						$aParams++;
					}
				}
				$bParams = 0;
				foreach ($bParts as $bp) {
					if (substr($bp, 0, 1) === ":") {
						$bParams++;
					}
				}
				if ($aParams < $bParams) {
					return 1;
				} elseif ($aParams > $bParams) {
					return -1;
				} else {
					// 3. lexicographical order
					return strcasecmp($a->scheme, $b->scheme);
				}
			}
		});
	}

	/**
	 * Reads the request and runs the appropriate method; catches library
	 * exceptions and turns them into HTTP errors with message
	 */
	public function run() {
		if (! in_array($this->verb, array_keys($this->routes))) {
			$this->sendError("unsupported method: $this->verb");
		}
		$routeFound = false;
		foreach ($this->routes[$this->verb] as $route) {
			if ($route->matches($this->resources)) {
				$routeFound = true;
				try {
					$response = $route->run($this->resources);
					if ($response !== null) {
						$this->sendJson($response);
					}
					break;
				} catch(Exception $e) {
					// catches lib exceptions and turns them into error 500
					$this->sendError($e->getMessage(), 500);
				}
			}
		}
		if (! $routeFound) {
			$this->sendError("no {$this->verb} route matching the given URI");
		}
	}

	/**
	 * Sends a JSON message indicating a success and exits the program
	 * @param type $json the message
	 * @param type $code defaults to 200 (HTTP OK)
	 */
	public function sendJson($json, $code=200) {
		header('Content-type: application/json');
		http_response_code($code);
		echo json_encode($json, JSON_UNESCAPED_UNICODE);
		exit;
	}

	/**
	 * Sends a JSON message indicating an error and exits the program
	 * @param type $error a string explaining the reason for this error
	 * @param type $code defaults to 400 (HTTP Bad Request)
	 */
	public function sendError($error, $code=400) {
		header('Content-type: application/json');
		http_response_code($code);
		echo json_encode(array("error" => $error));
		exit;
	}

	/**
	 * Compares request URI to base URI to extract URI elements (resources)
	 */
	protected function getResources() {
		$uri = $_SERVER['REQUEST_URI'];
		// slicing URI
		$baseURI = $this->baseURI . $this->firstResourceSeparator;
		if ((strlen($uri) > strlen($baseURI)) && (strpos($uri, $baseURI) !== false)) {
			$baseUriLength = strlen($baseURI);
			$posQM = strpos($uri, '?');
			if ($posQM != false) {
				$resourcesString = substr($uri, $baseUriLength, $posQM - $baseUriLength);
			} else {
				$resourcesString = substr($uri, $baseUriLength);
			}
			// decoding special characters
			$resourcesString = urldecode($resourcesString);
			//echo "Resources: $resourcesString" . PHP_EOL;
			$this->resources = explode("/", $resourcesString);
			// in case of a final /, gets rid of the last empty resource
			$nbRessources = count($this->resources);
			if (empty($this->resources[$nbRessources - 1])) {
				unset($this->resources[$nbRessources - 1]);
			}
		}
	}

	/**
	 * Gets the GET or POST request parameters
	 */
	protected function getParams() {
		$this->params = $_REQUEST;
	}

	/**
	 * Searches for parameter $name in $this->params; if defined (even if
	 * empty), returns its value; if undefined, returns $default; if
	 * $collection is a non-empty array, parameters will be searched among
	 * it rather than among $this->params (2-in-1-dirty-mode)
	 */
	protected function getParam($name, $default=null, $collection=null) {
		$arrayToSearch = $this->params;
		if (is_array($collection) && !empty($collection)) {
			$arrayToSearch = $collection;
		}
		if (isset($arrayToSearch[$name])) {
			return $arrayToSearch[$name];
		} else {
			return $default;
		}
	}
 
	/**
	 * Reads and returns request body contents
	 */
	protected function readRequestBody() {
		// @TODO beware of memory consumption
		$contents = file_get_contents('php://input');
		return $contents;
	}

	/**
	 * Sends the $file file for progressive download
	 */
	protected function sendFile($file, $name, $size, $mimetype='application/octet-stream') {
		if (! file_exists($file)) {
			$this->sendError("file does not exist");
		}
		header('Content-Type: ' . $mimetype);
		header('Content-Disposition: attachment; filename="' . $name . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . $size);
		// progressive sending
		// http://www.media-division.com/the-right-way-to-handle-file-downloads-in-php/
		set_time_limit(0);
		$f = @fopen($file,"rb");
		while(!feof($f)) {
			print(fread($f, 1024*8));
			ob_flush();
			flush();
		}
	}
}
