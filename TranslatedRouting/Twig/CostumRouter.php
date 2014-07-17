<?php

namespace MyBundle\ModelBundle\Twig;

class CostumRouter extends \Twig_Extension {
	
	private $customrouter;
	
	public function __construct($cotumrouter)
	{
		$this->customrouter = $cotumrouter;
	}

    public function getFilters() {
		return array();
    }
	
	public function onKernelRequest($event) {
    	$this->request = $event->getRequest();
    }

    public function getFunctions() {
        return array(
            'costum_router' => new \Twig_Function_Method($this, 'costumrouter'),
        );
    }

    public function getName() {
        return 'costumrouter';
    }

   	public function costumrouter($name,$params=array()){
		$params['_locale'] = isset($params['_locale']) ? 
			$params['_locale'] : $this->request->getLocale();
   		return $this->customrouter->generate($name, $params);
    }
}