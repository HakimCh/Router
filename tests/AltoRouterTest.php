<?php

require dirname(__DIR__) . '/AltoRouter.php';

class AltoRouterDebug extends AltoRouter{

	public function getNamedRoutes(){
		return $this->namedRoutes;
	}

	public function getBasePath(){
		return $this->basePath;
	}

}

class SimpleTraversable implements Iterator{

	protected $_position = 0;

	protected $_data = array(
		array('GET', '/foo', 'foo_action', null),
		array('POST', '/bar', 'bar_action', 'second_route')
	);

	public function current(){
		return $this->_data[$this->_position];
	}
	public function key(){
		return $this->_position;
	}
	public function next(){
		++$this->_position;
	}
	public function rewind(){
		$this->_position = 0;
	}
	public function valid(){
		return isset($this->_data[$this->_position]);
	}

}

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2013-07-14 at 17:47:46.
 */
class AltoRouterTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var AltoRouter
	 */
	protected $router;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->router = new AltoRouterDebug;
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
	}

	/**
	 * @covers AltoRouter::getRoutes
	 */
	public function testGetRoutes()
	{
		$method = 'POST';
		$route = '/[:controller]/[:action]';
		$target = function(){};

		$this->assertInternalType('array', $this->router->getRoutes());
		$this->router->map($method, $route, $target);
		$this->assertEquals(array(array($method, $route, $target, null)), $this->router->getRoutes());
	}

	/**
	 * @covers AltoRouter::setRoutes
	 */
	public function testAddRoutes()
	{
		$method = 'POST';
		$route = '/[:controller]/[:action]';
		$target = function(){};
		
		$this->router->setRoutes(array(
			array($method, $route, $target),
			array($method, $route, $target, 'second_route')
		));
		
		$routes = $this->router->getRoutes();
		
		$this->assertEquals(array($method, $route, $target, null), $routes[0]);
		$this->assertEquals(array($method, $route, $target, 'second_route'), $routes[1]);
	}

	/**
	 * @covers AltoRouter::setRoutes
	 */
	public function testAddRoutesAcceptsTraverable()
	{
		$traversable = new SimpleTraversable();
		$this->router->setRoutes($traversable);
		
		$traversable->rewind();
		
		$first = $traversable->current();
		$traversable->next();
		$second = $traversable->current();
		
		$routes = $this->router->getRoutes();
		
		$this->assertEquals($first, $routes[0]);
		$this->assertEquals($second, $routes[1]);
	}

	/**
	 * @covers AltoRouter::setRoutes
	 * @expectedException Exception
	 */
	public function testAddRoutesThrowsExceptionOnInvalidArgument()
	{
		$this->router->setRoutes(new stdClass);
	}

	/**
	 * @covers AltoRouter::setBasePath
	 */
	public function testSetBasePath()
	{
		$basePath = $this->router->setBasePath('/some/path');
		$this->assertEquals('/some/path', $this->router->getBasePath());
		
		$basePath = $this->router->setBasePath('/some/path');
		$this->assertEquals('/some/path', $this->router->getBasePath());
	}

	/**
	 * @covers AltoRouter::map
	 */
	public function testMap()
	{
		$method = 'POST';
		$route = '/[:controller]/[:action]';
		$target = function(){};
		
		$this->router->map($method, $route, $target);
		
		$routes = $this->router->getRoutes();
		
		$this->assertEquals(array($method, $route, $target, null), $routes[0]);
	}

	/**
	 * @covers AltoRouter::map
	 */
	public function testMapWithName()
	{
		$method = 'POST';
		$route = '/[:controller]/[:action]';
		$target = function(){};
		$name = 'myroute';
		
		$this->router->map($method, $route, $target, $name);
		
		$routes = $this->router->getRoutes();
		$this->assertEquals(array($method, $route, $target, $name), $routes[0]);
		
		$named_routes = $this->router->getNamedRoutes();
		$this->assertEquals($route, $named_routes[$name]);
		
		try{
			$this->router->map($method, $route, $target, $name);
			$this->fail('Should not be able to add existing named route');
		}catch(Exception $e){
			$this->assertEquals("Can not redeclare route '{$name}'", $e->getMessage());
		}
	}


	/**
	 * @covers AltoRouter::generate
	 */
	public function testGenerate()
	{
		$params = array(
			'controller' => 'test',
			'action' => 'someaction'
		);
		
		$this->router->map('GET', '/[:controller]/[:action]', function(){}, 'foo_route');
		
		$this->assertEquals('/test/someaction',
			$this->router->generate('foo_route', $params));
		
		$params = array(
			'controller' => 'test',
			'action' => 'someaction',
			'type' => 'json'
		);
		
		$this->assertEquals('/test/someaction',
			$this->router->generate('foo_route', $params));
		
	}

	public function testGenerateWithOptionalUrlParts()
	{
		$this->router->map('GET', '/[:controller]/[:action].[:type]?', function(){}, 'bar_route');
		
		$params = array(
			'controller' => 'test',
			'action' => 'someaction'
		);
		
		$this->assertEquals('/test/someaction',
			$this->router->generate('bar_route', $params));
		
		$params = array(
			'controller' => 'test',
			'action' => 'someaction',
			'type' => 'json'
		);
		
		$this->assertEquals('/test/someaction.json',
			$this->router->generate('bar_route', $params));
	}
	
	public function testGenerateWithNonexistingRoute()
	{
		try{
			$this->router->generate('nonexisting_route');
			$this->fail('Should trigger an exception on nonexisting named route');
		}catch(Exception $e){
			$this->assertEquals("Route 'nonexisting_route' does not exist.", $e->getMessage());
		}
	}
	
	/**
	 * @covers AltoRouter::match
	 * @covers AltoRouter::compileRoute
	 */
	public function testMatch()
	{
		$this->router->map('GET', '/foo/[:controller]/[:action]', 'foo_action', 'foo_route');
		
		$this->assertEquals(array(
			'target' => 'foo_action',
			'params' => array(
				'controller' => 'test',
				'action' => 'do'
			),
			'name' => 'foo_route'
		), $this->router->match('/foo/test/do', 'GET'));
		
		$this->assertFalse($this->router->match('/foo/test/do', 'POST'));
		
		$this->assertEquals(array(
			'target' => 'foo_action',
			'params' => array(
				'controller' => 'test',
				'action' => 'do'
			),
			'name' => 'foo_route'
		), $this->router->match('/foo/test/do?param=value', 'GET'));
		
	}
	
	public function testMatchWithFixedParamValues()
	{
		$this->router->map('POST','/users/[i:id]/[delete|update:action]', 'usersController#doAction', 'users_do');
		
		$this->assertEquals(array(
			'target' => 'usersController#doAction',
			'params' => array(
				'id' => 1,
				'action' => 'delete'
			),
			'name' => 'users_do'
		), $this->router->match('/users/1/delete', 'POST'));
		
		$this->assertFalse($this->router->match('/users/1/delete', 'GET'));
		$this->assertFalse($this->router->match('/users/abc/delete', 'POST'));
		$this->assertFalse($this->router->match('/users/1/create', 'GET'));
	}
	
	public function testMatchWithServerVars()
	{
		$this->router->map('GET', '/foo/[:controller]/[:action]', 'foo_action', 'foo_route');
		
		$_SERVER['REQUEST_URI'] = '/foo/test/do';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		
		$this->assertEquals(array(
			'target' => 'foo_action',
			'params' => array(
				'controller' => 'test',
				'action' => 'do'
			),
			'name' => 'foo_route'
		), $this->router->match());
	}
	
	public function testMatchWithOptionalUrlParts()
	{
		$this->router->map('GET', '/bar/[:controller]/[:action].[:type]?', 'bar_action', 'bar_route');
		
		$this->assertEquals(array(
			'target' => 'bar_action',
			'params' => array(
				'controller' => 'test',
				'action' => 'do',
				'type' => 'json'
			),
			'name' => 'bar_route'
		), $this->router->match('/bar/test/do.json', 'GET'));
		
	}
	
	public function testMatchWithWildcard()
	{
		$this->router->map('GET', '/a', 'foo_action', 'foo_route');
		$this->router->map('GET', '*', 'bar_action', 'bar_route');
		
		$this->assertEquals(array(
			'target' => 'bar_action',
			'params' => array(),
			'name' => 'bar_route'
		), $this->router->match('/everything', 'GET'));
		
	}
	
	public function testMatchWithCustomRegexp()
	{
		$this->router->map('GET', '@^/[a-z]*$', 'bar_action', 'bar_route');
		
		$this->assertEquals(array(
			'target' => 'bar_action',
			'params' => array(),
			'name' => 'bar_route'
		), $this->router->match('/everything', 'GET'));
		
		$this->assertFalse($this->router->match('/some-other-thing', 'GET'));
		
	}

	public function testMatchWithUnicodeRegex()
	{
		$pattern = '/(?<path>[^';
		// Arabic characters
		$pattern .= '\x{0600}-\x{06FF}';
		$pattern .= '\x{FB50}-\x{FDFD}';
		$pattern .= '\x{FE70}-\x{FEFF}';
		$pattern .= '\x{0750}-\x{077F}';
		// Alphanumeric, /, _, - and space characters
		$pattern .= 'a-zA-Z0-9\/_\-\s';
		// 'ZERO WIDTH NON-JOINER'
		$pattern .= '\x{200C}';
		$pattern .= ']+)';
		
		$this->router->map('GET', '@' . $pattern, 'unicode_action', 'unicode_route');
		
		$this->assertEquals(array(
			'target' => 'unicode_action',
			'name' => 'unicode_route',
			'params' => array(
				'path' => '大家好'
			)
		), $this->router->match('/大家好', 'GET'));
		
		$this->assertFalse($this->router->match('/﷽‎', 'GET'));
	}

	/**
	 * @covers AltoRouter::setMatchTypes
	 */
	public function testMatchWithCustomNamedRegex()
	{
		$this->router->setMatchTypes(array('cId' => '[a-zA-Z]{2}[0-9](?:_[0-9]++)?'));
		$this->router->map('GET', '/bar/[cId:customId]', 'bar_action', 'bar_route');
		
		$this->assertEquals(array(
			'target' => 'bar_action',
			'params' => array(
				'customId' => 'AB1',
			),
			'name' => 'bar_route'
		), $this->router->match('/bar/AB1', 'GET'));

		$this->assertEquals(array(
			'target' => 'bar_action',
			'params' => array(
				'customId' => 'AB1_0123456789',
			),
			'name' => 'bar_route'
		), $this->router->match('/bar/AB1_0123456789', 'GET'));
		
		$this->assertFalse($this->router->match('/some-other-thing', 'GET'));
		
	}

	public function testMatchWithCustomNamedUnicodeRegex()
	{
		$pattern = '[^';
		// Arabic characters
		$pattern .= '\x{0600}-\x{06FF}';
		$pattern .= '\x{FB50}-\x{FDFD}';
		$pattern .= '\x{FE70}-\x{FEFF}';
		$pattern .= '\x{0750}-\x{077F}';
		$pattern .= ']+';
		
		$this->router->setMatchTypes(array('nonArabic' => $pattern));
		$this->router->map('GET', '/bar/[nonArabic:string]', 'non_arabic_action', 'non_arabic_route');
		
		$this->assertEquals(array(
			'target' => 'non_arabic_action',
			'name' => 'non_arabic_route',
			'params' => array(
				'string' => 'some-path'
			)
		), $this->router->match('/bar/some-path', 'GET'));
		
		$this->assertFalse($this->router->match('/﷽‎', 'GET'));
	}
}
