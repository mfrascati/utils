<?php
namespace Entheos\Utils\Lib;

/**
 * Trait for automation of basic CRUD operations
 * Depends on CakePHP CRUD plugin
 */
trait CrudBasicTrait {

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
	 * Callback comune che viene richiamata per restituire l'entity completa dopo il save
	 * @param  \Cake\Event\Event $event 
	 * @return void
	 */
	public function _afterSave(\Cake\Event\Event $event)
	{
	    if ($event->subject->created)
	    {
	        $this->_setJson(true, $this->_findEntityById($event->subject->entity->id));
	    }
	}

	/**
	 * Callback comune per costruire automaticamente la query di view condividendo la logica di find con le altre funzioni
	 * @param  \Cake\Event\Event $event 
	 * @return void
	 */
	public function _beforeFind(\Cake\Event\Event $event)
	{
	    $event->subject->query = $this->_entityQuery($event->subject->query, $event->subject->id);
	}

	/**
	 * Scorciatoia per impostare le variabili success e data in view json
	 * @param boolean $success 
	 * @param array  $data
	 */
	public function _setJson($success, $data = [])
	{
	    $this->set([
	        'success' => $success,
	        'data' => $data,
	        '_serialize' => ['success', 'data']
	    ]); 
	}
}