<?php
namespace Entheos\Utils\Error;

use Cake\Error\ExceptionRenderer;

class ApiExceptionRenderer extends ExceptionRenderer
{
	protected function _outputMessage($template)
	{
		if(!$this->controller->request->is('json'))
			return parent::_outputMessage($template);
		
	    $this->controller->set('success', false);
	    $this->controller->set('data', [
	        'error' => $this->controller->viewVars['message'],
	        'code' => $this->controller->viewVars['code']
	    ]);
	    $this->controller->set('_serialize', ['success', 'data']);

	    return parent::_outputMessage($template);
	}
}