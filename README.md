# tb-base-rest-service
Simple PHP classes that help building REST webservices

## install
```
composer install telabotanica/tb-base-rest-service
```

## test
```
composer run-script test
```

## usage

Example code for a `service.php` file served under `http://localhost/my-service/`

```php
require_once "./BaseRestServiceTB.php";

class MyService extends BaseRestServiceTB {

	public function __construct() {
		parent::__construct([
			"domain_root" => "http://localhost",
			"base_uri" => "/my-service/service.php"
		]);
	}

	protected function routes() {
        $this->get("/", [ $this, "exampleRootGetHandler" ]);
        $this->get("/foo", function($args) {
			$this->sendJSON([
				"greeting" => "This is /foo",
				"args" => $args,
				"params" => $this->params
			]);
		});
        $this->get("/bar/:message", function($args) {
			$this->exampleBarGetHandler($args);
		});
        $this->post("/baz/:id", [ $this, "exampleBazPostHandler" ]);
	}

	// this method has to be public to be called from Route
	public function exampleRootGetHandler($args) {
		$this->sendJSON([
			"greeting" => "This is the example get handler for /",
			"args" => $args,
			"params" => $this->params
		]);
	}

	// this method does not need to be public to be called from a closure
	protected function exampleBarGetHandler($args) {
		$this->sendJSON([
			"greeting" => "This is the example get handler for /bar/:message",
			"args" => $args,
			"params" => $this->params
		]);
	}

	// try it with  curl -X POST http://localhost/my-service/service.php/baz/3 -H 'Content-Type: application/json' -d 'hello world !' | jq
	public function exampleBazPostHandler($args) {
		$body = $this->readRequestBody();
		if ($body) {
			$this->sendJSON([
				"greeting" => "This is the example post handler for /baz/:id",
				"args" => $args,
				"params" => $this->params,
				"body" => $body
			]);
		} else {
			$this->sendError("missing body");
		}
	}
}

$svc = new MyService();
$svc->run();
```

## config parameters
### mandatory
- __domain_root__ : root URL of your server
- __base_uri__ : base path of your webservice

### non-mandatory
- __first_resource_separator__ : first character following your base_uri (typically "/" or ":", defaults to "/")

## URL rewriting

To get rid of the `service.php` part in your URLs, rewrite all `my-service/*` URLS to `service.php` (using Apache mod_rewrite for ex.) and set `base_uri` to `/my-service` only, **without** the trailing slash.

### example .htaccess
```
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule . service.php [L]
```
