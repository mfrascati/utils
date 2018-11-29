<?php
namespace Entheos\Utils\Error;

use Cake\Error\ExceptionRenderer;
use Cake\Collection\Collection;

class ApiExceptionRenderer extends ExceptionRenderer
{
	protected function _outputMessage($template)
	{
		if(!$this->controller->request->is('json'))
		{	
			if(\Cake\Core\Configure::read('debug'))
				return parent::_outputMessage($template);
			else
				die('<div style="text-align:center; margin-top:40px"><h3>'.$this->controller->viewVars['message'].'</h3><h1>'.$this->controller->viewVars['error']->getMessage().'</h1></div>');
		}
		
		$data = [
	        'error' => $this->controller->viewVars['message'],
	        'code' => $this->controller->viewVars['code']
	    ];
	    if($data['code'] == 422 && !empty($this->controller->viewVars['error'])){
	    	$data['error']		= 'Sono presenti degli errori nei dati inviati:';
	    	$validationErrors = [];

	    	foreach($this->controller->viewVars['error']->getValidationErrors() as $field => $errors)
	    	{
	    		foreach($errors as $rule => $message)
	    			$validationErrors[] = ['field' => $field, 'rule' => $rule, 'message' => $message];
    		}
	    	$data['validation'] = $validationErrors;

	    	$errorString = '<ul style="padding-left: 20px;">';
	    	foreach($validationErrors as $error)
	    	{
	    		$errorString .= "<li><b>$error[field]</b> - $error[message]</li>";
	    	}
	    	$errorString .= '</ul>';

	    	$data['error'] .= $errorString;
	    }

	    $this->controller->set('success', false);
	    $this->controller->set('data', $data);
	    $this->controller->set('_serialize', ['success', 'data']);

	    return parent::_outputMessage($template);
	}
}