<?php
namespace MyBundle\ModelBundle\Doctrine;

/**
 * @Annotation
 * @Target({"CLASS","PROPERTY"})
 */
class Translation
{
	private $properties;
	private $dataType = 'string';
	
	public function __construct($options)
	{
		$this->properties = $options;
	}
	
	public function getProperties()
	{
		return $this->properties;
	}
}