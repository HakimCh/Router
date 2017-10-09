<?php
namespace HakimCh\Http;

use HakimCh\Http\Exception\RouterException;
use Traversable;

class Router
{
	protected $routes = [];
	protected $namedRoutes = [];
	protected $basePath = '';
	protected $all = ['get', 'post'];
	private $server;
    /**
     * @var RouterParser
     */
    private $parser;

    /**
     * Create router in one call from config.
     *
     * @param RouterParser $parser
     * @param array $routes
     * @param string $basePath
     * @param null $server
     */
	public function __construct(RouterParser $parser, $routes = [], $basePath = '', $server = null)
	{
		$this->setRoutes($routes);
		$this->setBasePath($basePath);
        $this->parser = $parser;
		if(!$server) {
			$this->server = $_SERVER;
		}
    }

	/**
	 * Map a route to a target
	 *
	 * @param string $method One of 5 HTTP Methods, or a pipe-separated list of multiple HTTP Methods (GET|POST|PATCH|PUT|DELETE)
	 * @param string $route The route regex, custom regex must start with an @. You can use multiple pre-set regex filters, like [i:id]
	 * @param mixed $target The target where this route should point to. Can be anything.
	 * @param string $routeName Optional name of this route. Supply if you want to reverse route this url in your application.
	 */
	public function map($method, $route, $target, $routeName = null)
	{
		if (is_string($routeName)) {
		    $this->handleException($routeName, "Can not redeclare route '%s'");
			$this->namedRoutes[$routeName] = $route;
		}

		$this->routes[] = array($method, $route, $target, $routeName);
	}

	/**
	 * Reversed routing
	 *
	 * Generate the URL for a named route. Replace regexes with supplied parameters
	 *
	 * @param string $routeName The name of the route.
	 * @param array @params Associative array of parameters to replace placeholders with.
	 * @return string The URL of the route with named parameters in place.
	 */
	public function generate($routeName, array $params = [])
	{
        $this->handleException($routeName, "Can not redeclare route '%s'", false);

		$route = $this->namedRoutes[$routeName];

        return $this->parser->generate($this->basePath, $route, $params);
	}

	/**
	 * Match a given Request Url against stored routes
	 * @param string $requestUrl
	 * @param string $requestMethod
	 * @return array|boolean Array with route information on success, false on failure (no match).
	 */
	public function match($requestUrl = null, $requestMethod = null)
	{
		$requestUrl = $this->getRequestUrl($requestUrl);

		// set Request Method if it isn't passed as a parameter
		if (is_null($requestMethod)) {
			$requestMethod = $this->server['REQUEST_METHOD'];
		}

		foreach ($this->routes as $handler) {

			if(!$this->parser->methodMatch($handler[0], $requestMethod, $handler[1], $requestUrl)) continue;

			return array(
                'target' => $handler[2],
                'params' => array_filter($this->parser->getParams(), function ($k) { return !is_numeric($k); }, ARRAY_FILTER_USE_KEY),
                'name'   => $handler[3]
            );
		}

		return false;
	}

    /**
     * @param $method
     * @param $arguments
     * @throws RouterException
     */
    public function __call($method, $arguments)
	{
		if(!in_array($method, array('get', 'post', 'delete', 'put', 'patch', 'update', 'all'))) {
			throw new RouterException($method . ' not exist in the '. __CLASS__);
		}

		$methods = $method == 'all' ? implode('|', $this->all) : $method;

		$route = array_merge([$methods], $arguments);

		call_user_func_array([$this, 'map'], $route);
	}

    /**
     * Retrieves all routes.
     * Useful if you want to process or display routes.
     * @return array All routes.
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Add multiple routes at once from array in the following format:
     *
     *   $routes = array(
     *      array($method, $route, $target, $name)
     *   );
     *
     * @param array $routes
     * @return void
     * @author Koen Punt
     */
    public function setRoutes($routes)
    {
        if (!is_array($routes) && !$routes instanceof Traversable) {
            throw new RouterException('Routes should be an array or an instance of Traversable');
        }
        if(!empty($routes)) {
            foreach ($routes as $route) {
                call_user_func_array(array($this, 'map'), $route);
            }
        }
    }

    /**
     * Set the base path.
     * Useful if you are running your application from a subdirectory.
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Add named match types. It uses array_merge so keys can be overwritten.
     *
     * @param array $matchTypes The key is the name and the value is the regex.
     */
    public function setMatchTypes($matchTypes)
    {
        $this->matchTypes = array_merge($this->matchTypes, $matchTypes);
    }

    /**
     * @param $routeName
     * @param $message
     * @param bool $cmpTo
     * @throws RouterException
     */
    private function handleException($routeName, $message, $cmpTo = true)
    {
        if (array_key_exists($routeName, $this->namedRoutes) === $cmpTo) {
            throw new RouterException(sprintf($message, $routeName));
        }
    }

    /**
     * @param $requestUrl
     *
     * @return mixed
     */
    private function getRequestUrl($requestUrl = null)
    {
        // set Request Url if it isn't passed as parameter
        if (is_null($requestUrl)) {
            $requestUrl = parse_url($this->server['REQUEST_URI'], PHP_URL_PATH);
        }

        return str_replace($this->basePath, '', $requestUrl);
    }
}
