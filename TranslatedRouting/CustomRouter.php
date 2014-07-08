<?php

namespace MyBundleBundle\Manager;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Exception\RouteNotFoundException;


class CustomRouter
{
	private $container;
	private $router;
	private $user_locale;
	private $default_locale;	
	
	public function __construct(Router $router, Container $container, $default_locale)
	{
		$this->container 	= $container;
		$this->router 		= $router;
		$this->user_locale 	= $this->container->get('request')->getLocale();
		$this->default_locale = $default_locale;
	}
	
	public function generate($name, $params = array())
	{
		$routes = $this->router->getRouteCollection();		
		$route = $routes->get($name);
		
		// No Route found
		if ( !$route )
		   Throw new RouteNotFoundException('Upps!! No route for: '.$name);
		
		// Check if translated routing
		$requirements = $route->getRequirements();
		
		// If just a simple routing
		if ( empty($requirements) )
			return $this->router->generate($name, $params);
			
		// Prefix url, from main routing to bundle
		$prefix = explode('|', $requirements['_locale']);
		$transl = explode('|', $requirements['_translated']);
		$actionTranslation = isset($requirements['_action_translation']) ? 
			explode('|', $requirements['_action_translation']) : false;

		// Array position of language
		$langPosition = array_search($this->user_locale, $prefix);		
		if (!$langPosition)
			$langPosition = array_search($this->default_locale, $prefix);
		
		// First param of new url
		$params['_locale'] 		= $prefix[$langPosition];
		$params['_translated'] 	= $transl[$langPosition];
		
		// if action in bundle
		if ( $actionTranslation )
			$params['_action_translation'] = $actionTranslation[$langPosition];
		
		
		return $this->router->generate($name, $params);
	}
}
