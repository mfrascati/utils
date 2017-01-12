<?php
namespace Entheos\Utils\Error;

use Cake\Error\ExceptionRenderer;
use Cake\Collection\Collection;

class ApiExceptionRenderer extends ExceptionRenderer
{
	protected function _outputMessage($template)
	{
		if(!$this->controller->request->is('json'))
			return parent::_outputMessage($template);
		
		$data = [
	        'error' => $this->controller->viewVars['message'],
	        'code' => $this->controller->viewVars['code']
	    ];
	    if($data['code'] == 422 && !empty($this->controller->viewVars['error'])){
	    	$data['error']		= 'Sono presenti degli errori di validazione';
	    	$validationErrors = [];

	    	foreach($this->controller->viewVars['error']->getValidationErrors() as $field => $errors)
	    	{
	    		foreach($errors as $rule => $message)
	    			$validationErrors[] = ['field' => $field, 'rule' => $rule, 'message' => $message];
    		}
	    	$data['validation'] = $validationErrors;
	    }

	    $this->controller->set('success', false);
	    $this->controller->set('data', $data);
	    $this->controller->set('_serialize', ['success', 'data']);

	    return parent::_outputMessage($template);
	}
}