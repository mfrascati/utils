<?php
namespace Entheos\Utils\Lib;

trait IncrementalTrait {

	/**
	 * Restituisce il prossimo n progressivo, in base alle condizioni passate in conditions
	 * @param  array  $conditions es ['y' => 2016]
	 * @param  string $fieldName nome del campo counter
	 * @return integer
	 */
	public function getIncremental($conditions = [], $fieldName = 'n')
	{
		if(empty($conditions))
			throw new \Exception("Parametri incrementale mancanti");
			
	    $max = $this->find('all')
	    	->where($conditions)
	    	->order([$fieldName => 'DESC'])
	    	->select([$fieldName])
	    	->hydrate(false)
	    	->first();

	    if(empty($max))
	        return 1;

	    return $max[$fieldName] + 1;
	}

}