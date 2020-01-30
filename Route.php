<?php

/**
 * A service route
 */
class Route {

    /**
     * The route URI scheme : a string containing "/" separated parts, that
     * are considered parameters when they start with ":"; those parameters
     * will be replaced by matching resources when the route is run
     */
    public $scheme;
    public $function;

    public function __construct($scheme, $function) {
        $this->scheme = $scheme;
        $this->function = $function;
    }

    /**
     * Splits a URI scheme into parts, ignoring empty elements
     * (before first "/" and after last "/")
     */
	public static function extractSchemeParts($scheme) {
        $schemeParts = explode("/", trim($scheme, "/"));
        if (count($schemeParts) > 0 && $schemeParts[0] === "") {
            array_shift($schemeParts);
        }
        return $schemeParts;
	}

    /**
     * Returns true if the given resources array matches the
     * current URI scheme
     */
    public function matches(array $resources) {
        $schemeParts = Route::extractSchemeParts($this->scheme);
        if (count($resources) !== count($schemeParts)) {
            return false;
        }
        $matches = true;
        for ($i = 0; $i < count($schemeParts); $i++) {
            $sp = $schemeParts[$i];
            $matches = $matches && (
                $this->isParam($sp)
                || $sp === $resources[$i]
            );
        }
        return $matches;
    }

    /**
     * "Runs" the route by calling the function passed at declaration time. The
     * arguments given to the called function are:
     *  - a pointer to the service
     *  - those of the given resources that match the current scheme, in a key-value array
     */
    public function run($resources) {
        $args = [];
        // find matching resources
        $schemeParts = Route::extractSchemeParts($this->scheme);
        for ($i = 0; $i < count($schemeParts); $i++) {
            $sp = $schemeParts[$i];
            if ($this->isParam($sp)) {
                $args[substr($sp, 1)] = $resources[$i];
            }
        }
        // call given function
        call_user_func_array($this->function, [ $args ]);
    }

    /** Returns true if the given scheme part is a parameter (ie. starts with ":") */
    protected function isParam($schemePart) {
        return substr($schemePart, 0, 1) === ":";
    }
}
