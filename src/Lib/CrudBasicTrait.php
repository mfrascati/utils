<?php
namespace Entheos\Utils\Lib;

use Entheos\Utils\Exception\WarningException;
use Cake\Core\Configure;

/**
 * Trait for automation of basic CRUD operations
 * Depends on CakePHP CRUD plugin
 */
trait CrudBasicTrait {

	protected $_responseWarnings = [];

	/**
	 * Basic add callback
	 * If you need to extend the function just copy the function in your controller
	 */
	public function add()
	{
	    $this->Crud->on('afterSave', [$this, '_afterSave']);
	    return $this->Crud->execute();
	}

	/**
	 * Basic edit callback
	 * If you need to extend the function just copy the function in your controller
	 */
	public function edit()
	{
	    $this->Crud->on('afterSave', [$this, '_afterSave']);
	    return $this->Crud->execute();
	}

	/**
	 * Basic view callback
	 * If you need to extend the function just copy the function in your controller
	 */
	public function view($id)
	{
	    $this->Crud->on('beforeFind', [$this, '_beforeFind']);
	    return $this->Crud->execute();
	}

	/**
	 * Metodo comume per l'arricchimento della query, per funzioni view e add/edit post save
	 * @param Query $query la query con già impostata la ricerca per ottenere il record
	 * @return Query
	 */
	public function _entityQuery($query, $id){
	    return $query;
	}

	/**
	 * Finder per la singola entity, richiama entityQuery ed estrae il primo risultato
	 * Di default viene utilizzato nella callback generica afterSave, 
	 * ma se servisse estenderla basta richiamare questa funzione e poi utilizzare _setJson
	 * @param  int $id 
	 * @return Entity
	 */
	public function _findEntityById($id)
	{
	    return $this->_entityQuery($this->{$this->name}->find('all')->where([$this->name.'.id' => $id]), $id)->first();
	}

	/**
	 * Controlla che la chiamata sia post 
	 * @return void
	 */
	public function requirePost()
	{
		if(!$this->request->is('post'))
		    throw new WarningException('Request has to be POST');
	}

	/**
	 * Controlla che i campi obbligatori siano presenti
	 * @param  array $fields 
	 * @return bool
	 */
	public function requireFields($fields)
	{
		$this->requirePost();
		
		foreach($fields as $field) {
			if(!$this->request->getData($field))
				throw new WarningException("Campo obbligatorio: $field");
		}
	}

	/**
	 * Callback comune che viene richiamata per restituire l'entity completa dopo il save
	 * @param  \Cake\Event\Event $event 
	 * @return void
	 */
	public function _afterSave(\Cake\Event\Event $event)
	{
        $this->_setJson(true, $this->_findEntityById($event->getSubject()->entity->id));
	}

	/**
	 * Callback comune per costruire automaticamente la query di view condividendo la logica di find con le altre funzioni
	 * @param  \Cake\Event\Event $event 
	 * @return void
	 */
	public function _beforeFind(\Cake\Event\Event $event)
	{
	    $event->getSubject()->query = $this->_entityQuery($event->getSubject()->query, $event->getSubject()->id);
	}

	/**
	 * Scorciatoia per impostare le variabili success e data in view json
	 * Per integrare la risposta con variabili aggiuntive è possibile definirle nell'app controller
	 * Es. public $integrateResponseVars = ['emails' => 'EmailDrafts'];
	 * Il valore verrà letto da Configure EmailDrafts e restituito sotto l'indice top level emails
	 * @param boolean $success 
	 * @param array  $data
	 */
	public function _setJson($success, $data = [])
	{
		$res = [
			'success'	=> $success,
			'data'		=> $data,
			'warnings'	=> $this->_responseWarnings,
	    ];

	    if(!empty($this->integrateResponseVars))
	    {
	    	foreach($this->integrateResponseVars as $key => $var)
	    		$res[$key] = Configure::read($var);
	    }

	    $res['_serialize'] = array_keys($res);
	    $this->set($res); 
	}

	public function _setResponseWarning($message)
	{
		$this->_responseWarnings[] = $message;
	}

	/**
	 * Scorciatoia per impostare le variabili success e data[message, code] in view json
	 * @param boolean $success 
	 * @param array  $data
	 */
	public function _setError($message, $code = 500)
	{
	    $this->set([
			'success'		=> false,
			'data'			=> ['message' => $message, 'code' => $code],
			'warnings'		=> $this->_responseWarnings,
			'_serialize'	=> ['success', 'data']
	    ]); 
	}
}