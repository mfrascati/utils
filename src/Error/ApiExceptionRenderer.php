<?php
namespace Entheos\Utils\Error;

use Cake\Error\ExceptionRenderer;
use Cake\Http\Response;
use Cake\Collection\Collection;

class ApiExceptionRenderer extends ExceptionRenderer
{
	protected function _outputMessage(string $template): Response
	{
		$vars = $this->controller->viewBuilder()->getVars();
		if(!$this->controller->getRequest()->is('json'))
		{	
			if(\Cake\Core\Configure::read('debug'))
				return parent::_outputMessage($template);
			else
				die('<div style="text-align:center; margin-top:40px"><h3>'.$vars['message'].'</h3><h1>'.$vars['error']->getMessage().'</h1></div>');
		}
		
		$data = [
	        'error' => $vars['message'],
	        'code' => $vars['code']
	    ];
	    if($data['code'] == 422 && !empty($vars['error'])){
	    	$data['error']		= 'Sono presenti degli errori nei dati inviati:';
	    	$validationErrors = [];

	    	foreach($vars['error']->getValidationErrors() as $field => $errors)
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
			$this->controller->viewBuilder()->setOption('serialize', ['success', 'data']);

	    return parent::_outputMessage($template);
	}
}
