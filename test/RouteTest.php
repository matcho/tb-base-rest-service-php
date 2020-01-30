<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../Route.php";

class RouteTest extends TestCase
{
    public function testExtractSchemeParts()
    {
        $scheme = "/foo/bar/baz/:id/list";
        $expected = [ "foo", "bar", "baz", ":id", "list" ];

        $extracted = Route::extractSchemeParts($scheme);
        $this->assertSame($expected, $extracted);

        $extracted = Route::extractSchemeParts($scheme . "/");
        $this->assertSame($expected, $extracted);

        $extracted = Route::extractSchemeParts("/" . $scheme . "/");
        $this->assertSame($expected, $extracted);
    }

    public function testMatches()
    {
        $resources = [ "cities", "Montpellier", "weather", "3", "show" ];
        $scheme = "/cities/:name/weather/:day/show";
        $r = new Route($scheme, "");
        $this->assertTrue($r->matches($resources));

        $resources = [ "cities", "name", "weather", "3", "show" ];
        $this->assertTrue($r->matches($resources));

        $resources = [ "cities", ":name", "weather", "3", "show" ];
        $this->assertTrue($r->matches($resources));

        $resources = [ "cities", "Montpellier", "weather", "show" ];
        $this->assertFalse($r->matches($resources));
    }

}
