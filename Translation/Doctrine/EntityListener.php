<?php
namespace MyBundle\ModelBundle\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;

use Doctrine\Common\Annotations\AnnotationReader;
use MyBundle\ModelBundle\Doctrine\Translation as Translation;
// use Doctrine\Common\Annotations\Reader;

class EntityListener implements EventSubscriber
{
	private $entity;
	private $entityManager;
	
	private $transTable = array();
	private $transKey = array();
	private $annoFields = array();
	
	private $languages = array();
	
	public function getSubscribedEvents()
	{
		return array(
				'postPersist',
				'postUpdate',
				'postLoad'
		);
	}
	
	/**
	 * After Loading the Entity
	 * Creates Getters and Setters
	 * Sets needed EntityManager and the requiered values.
	 * @param LifecycleEventArgs $args
	 */
	public function postLoad(LifecycleEventArgs $args) 
	{
        $entity = $args->getEntity();
        $eName = get_class($entity);
        $this->entity = $entity;
        
        if ( is_subclass_of($entity, 'MyBundle\ModelBundle\Doctrine\DynamicFields') ){
        	if(!$this->entityManager) $this->entityManager = $args->getEntityManager();
        	$this->entity->setRealUpdatedAt($this->entity->getUpdatedAt());
        	$this->parseAnnotations($eName);
        	$this->setChildValues($eName);
        }
	}

	/**
	 * When creating a new or updates item, this functión 
	 * updates/creates translations
	 * @param LifecycleEventArgs $args
	 */
	public function postPersist(LifecycleEventArgs $args)
	{
		$this->postUpdate($args);
	}
	
	public function postUpdate(LifecycleEventArgs $args)
	{
		$entity = $args->getEntity();
        $eName = get_class($entity);

        $this->entity = $entity;
                
		$id = $entity->getId();
		if ( $id && is_subclass_of($entity, 'MyBundle\ModelBundle\Doctrine\DynamicFields') ){
			if(!$this->entityManager) $this->entityManager = $args->getEntityManager();
			if (!isset($this->annoFields[$eName]) || empty($this->annoFields[$eName]) ) {
				$this->parseAnnotations($eName, false);
				$this->setChildValues($eName);
			}
			$this->saveTranslation($eName);
		}
	}
	
	/**
	 * Creates the needed setter/getter by Annotation
	 * @param string $addSetterGetter
	 */
	private function parseAnnotations($eName, $addSetterGetter = true)
	{	
		$entity = $this->entity;
		$this->annoFields[$eName] = array();	
		// annotation reader gets the annotations for the class
		$reader = new AnnotationReader;
		$reflectionClass = new \ReflectionClass($entity);
		
		// Main Class Annotation
		$annotations = $reader->getClassAnnotations($reflectionClass);
		foreach ($reader->getClassAnnotations($reflectionClass) as $annotation){
			if ( $annotation instanceof Translation ){
				$anno = $annotation->getProperties();
				$this->transTable[$eName] = $anno['translation_table'];
				$this->transKey[$eName] = $anno['translation_key'];
			}
		}
		
		// Method Annotations
		$props = $reflectionClass->getProperties(\ReflectionProperty::IS_PROTECTED);
		foreach($props as $prop) {
			$name = $prop->getName();
			$annotations = $reader->getPropertyAnnotations(new \ReflectionProperty($entity, $name));
			// Is this a Translation Annotation		
			if (isset($annotations[0]) && $annotations[0] instanceof Translation){
				$tran = $annotations[0];
				$anno = $tran->getProperties();
				if ( isset($anno['name']) ) {
					$this->annoFields[$eName][$anno['name']] = $anno;
					if ( $addSetterGetter ) 
						$entity->addField($anno['name']);
				}
			}
			
		}

	}
	
	
	/**
	 * Saves to Database.
	 */
	private function saveTranslation($eName)
	{
		$em = $this->entityManager;		
		$con = $em->getConnection();
		
		$mainClass = $this->entity;
		
		$objId  = $mainClass->getId();
		
		$locales = $mainClass->isos;
			
		foreach ($locales as $iso)
		{
			if ( isset($this->language[$iso]) ) {
				$language = $this->language[$iso];
			} else {
				$language = $em->getRepository('ModelBundle:Language')->findOneByIso($iso);		
				$this->language[$iso] = $language;
			}			
			if ( !$language ) {
				$mainClass->setTranstaltionError('L0-'.$iso, 'No language for ISO: '.$iso);
				continue;
			}
			$languageId = $language->getId();

			// Check if is a update or a new translation
			$stmt = $con->prepare("SELECT t.language_id FROM {$this->transTable[$eName]} t
				WHERE t.language_id=:langId AND t.{$this->transKey[$eName]} = :objId LIMIT 1");
			$stmt->bindParam('langId', $languageId, \PDO::PARAM_INT);
			$stmt->bindParam('objId', $objId, \PDO::PARAM_INT);
			$stmt->execute();
			$result = $stmt->fetch();
			
			// Prepare values
			$values = array();
			$update = array();
			$mainClass->preSetIso($iso);
			foreach ($this->annoFields[$eName] as $col=>$f) {
				$method = 'get'.ucwords($col);
				$values[$col] = $mainClass->$method();
				$update[$col] = $col.'=:'.$col;
			}
			// SQL
			$transLanguageId = isset($result['language_id']) ? $result['language_id'] : false;
			if ( $transLanguageId ) {
				$valString = implode(', ', $update);
				$stmt = $con->prepare("UPDATE {$this->transTable[$eName]} SET {$valString}
						WHERE language_id=:langId AND {$this->transKey[$eName]} = :objId LIMIT 1");
			} else {
				$update[] = $this->transKey[$eName].'=:objId';
				$update[] = 'language_id=:langId';
				$valString = implode(', ', $update);
				$stmt = $con->prepare("INSERT INTO {$this->transTable[$eName]} SET {$valString}");
			}
			$stmt->bindParam('langId', $languageId, \PDO::PARAM_INT);
			$stmt->bindParam('objId', $objId, \PDO::PARAM_INT);
			foreach ($this->annoFields[$eName] as $col=>$f) {
				$stmt->bindParam($col, $values[$col], \PDO::PARAM_STR);
			}
			$stmt->execute();			
		}
	}
	
	private function setChildValues($eName)
	{
		$entity = $this->entity;
		
		$entity->setTranslatableFields($this->annoFields[$eName]);		
		$entity->setTransTable($this->transTable[$eName]);	
		$entity->setTransKey($this->transKey[$eName]);	
		$entity->setEntityManager($this->entityManager);
	
	}
	
	
}