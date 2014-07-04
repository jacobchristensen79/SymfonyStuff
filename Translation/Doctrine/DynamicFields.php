<?php
namespace MyBundle\ModelBundle\Doctrine;

use Doctrine\Common\Collections\ArrayCollection as ArrayCollection;

class DynamicFields
{
	private $methods = array();
	private $entityManager;
	private $transTable;
	private $transKey;
	private $translatableFields = array();
	private $iso;
	public $isos = array();
	private $realUpdatedAt;
	
	private $translationErrors = array();
			
	public function addField($name) 
	{			
		if ( !is_object($this->$name) )
			$this->$name = new ArrayCollection();
		
		$get = function($key) {
			return $this->$key->get($this->iso);
		};
		$set = function($value, $key) {
			if ( !in_array($this->iso, $this->isos) )  
				$this->isos[] =  $this->iso;
			
			$this->$key->set($this->iso, $value);
			return $this;
		};
		$mehtod = ucwords($name);
		$this->methods['get'.$mehtod] = \Closure::bind($get, $this, get_class());
		$this->methods['set'.$mehtod] = \Closure::bind($set, $this, get_class());
	}
	
	function __call($method, $args)
	{
		// Twig Hack
		if(substr($method, 0,3) != 'set' && !isset($this->methods[$method]) ){
			$method = 'get'.ucwords($method);
		}
		// No Method, create it if setter.
		if (!isset($this->methods[$method])) {
			if ( substr($method, 0,3) == 'set' )
				return $this->makeField($method, $args);
			else
				return 'Incorrect function called in translation: '.$method;
		}
		if(is_callable($this->methods[$method]))
		{
			$name = strtolower(substr($method, 3));			
			$args['key'] = $name;
			return call_user_func_array($this->methods[$method], $args);
		}
	}
	
	private function makeField($method, $args)
	{
		$name = strtolower(substr($method, 3));
		$this->addField($name);
		$args['key'] = $name;
		return call_user_func_array($this->methods[$method], $args);
	}
	
	public function getTranstaltionErrors()
	{
		return $this->translationErrors;
	}
	public function setTranstaltionError($code, $error)
	{
		$this->translationErrors[$code] = $error;
	}
	
	/**
	 * For Translations
	 */
	public function setRealUpdatedAt($datetime)
	{
		if (empty($datetime)) $datetime = new \DateTime('now');
		$this->realUpdatedAt = $datetime;
	}
	public function getRealUpdatedAt()
	{
		return $this->realUpdatedAt;
	}
	public function locale($iso)
	{
		return $this->setLocale($iso);
	}
	
	/**
	 * When saving, this is needed to get the multi values.
	 * @param string $iso
	 */
	public function preSetIso($iso){
		$this->iso = $iso;
	}
	public function setLocale($iso)
	{
		$this->iso = $iso;
		$this->setDynamicTranslations($iso);
		return $this;
	}
	public function getLocale()
	{
		return $this->iso;
	}
	
	/**
	 * Settet by Entity Listener
	 */
	public function setTranslatableFields($fields)
	{
		$this->translatableFields = $fields;
	}
	public function setTransTable($transTable)
	{
		$this->transTable = $transTable;
	}
	public function setTransKey($transKey)
	{
		$this->transKey = $transKey;
	}
	public function setEntityManager($entityManager)
	{
		$this->entityManager = $entityManager;
	}
	
	/**
	 * When Main Entity require a set Locale.
	 */
	private function setDynamicTranslations($iso)
	{
		if ( !is_object($this->entityManager) ) return $this;
		
		$updatedAt = $this->getUpdatedAt();
		$this->setRealUpdatedAt($updatedAt);
		$this->setUpdatedAt(new \DateTime());
		
		$con = $this->entityManager->getConnection();
		
		$fields = 't.'.implode(', t.', array_keys($this->translatableFields) );
		$objId  = $this->getId();
		
		$stmt = $con->prepare("
			SELECT {$fields} FROM {$this->transTable} t 
			INNER JOIN language l ON l.id=t.language_id 
			WHERE l.iso=:iso AND t.{$this->transKey} = :objId 
			LIMIT 1");
		
		$stmt->bindParam('iso', $iso, \PDO::PARAM_STR);
		$stmt->bindParam('objId', $objId, \PDO::PARAM_INT);
		
		$stmt->execute();
		$results = $stmt->fetch();
		
		if ( empty($results) ) return;
		
		foreach ($results as $key=>$value) {
			$m = 'set'.ucwords($key);
			$this->$m($value);
		}
	}
	

}