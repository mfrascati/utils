<?php
namespace Entheos\Utils\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Event\Event;
use Cake\Datasource\EntityInterface;

/**
 * Behaviour used to control a group of fields with a checkbox
 * If the checkbox is selected controlled fields are passed,
 * otherwise are cleared to their default values
 */
class CheckboxDependentBehavior extends Behavior
{

	/**
	 * Fields configuration
	 * Key is checkbox field,
	 * Value is an array, [controlled_field_name => default_value, ...]
	 * @var array
	 */
	protected $_defaultConfig = [
        'fields' => [],
    ];

    /**
     * Defaults controlled fields with controller checkbox set to false
     * @param  Entity $entity 
     * @return Entity
     */
	public function checkedOrClear($entity)
	{
		$config = $this->getConfig();
		if(empty($config['fields']))
			return $entity;

		foreach($config['fields'] as $controlField => $controlledFields)
		{
			if(!isset($entity->{$controlField}) || $entity->{$controlField} == true)
				continue;
			foreach($controlledFields as $field => $defaultValue)
				$entity->{$field} = $defaultValue;
		}
		return $entity;

	}

	public function beforeSave(Event $event, EntityInterface $entity)
	{
	    $entity = $this->checkedOrClear($entity);
	}

}