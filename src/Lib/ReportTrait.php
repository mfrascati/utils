<?php
namespace Entheos\Utils\Lib;

use Entheos\Utils\Exception\ErrorException;
use Cake\Collection\Collection;
use Cake\Utility\Inflector;

/**
 * Permette di costruire una query con select, where (semplice) e order passati dal frontend
 * Trait da usare sulla classe Table
 */
trait ReportTrait
{
	protected $reportFields = null;
	protected $reportNiceNames = [];
	protected $selectable	= [];
	protected $whereable	= [];
	protected $orderable	= [];
	protected $reportWarnings = [];

	/**
	 * Genera il report custom
	 *
	 * es, da controller:
	 * $data = $this->Offers->execReport($this->request->getData(), $this->Offers->find());
	 * 
	 * @param  array $params 
	 * @param  Query $query , con gli eventuali contain necessari
	 * @return array     
	 */
	public function generateReport($params, $query)
	{
		if(empty($this->reportFields))
			throw new ErrorException("Campi report non definiti");

		// debug($this->reportFields->toArray());

		$this->query = $query;
		$this->tableName = $query->getRepository()->getAlias();
		// debug($query);

		$this->__buildSelect($params['select']);
		$this->__buildWhere();
		$this->__buildOrder();
	}

	/**
	 * Imposta i nomi nice per i model.
	 * Se ci sono sottomodel usare qua l'intero percorso!
	 *
	 * Es.
	 * [
	 * 	'Clients' => 'Cliente',
 	 * 	'Clients.Sede' => 'Sede',
 	 * ]
 	 * 
	 * @param void
	 */
	public function setReportNiceNames($fields)
	{
		$this->reportNiceNames = $fields;
	}

	/**
	 * Configura i campi processabili
	 *
	 * Il primo valore dell'array è il field, nel formato Models.field
	 * 
	 * Il secondo valore è il type, ovvero string|number|date|boolean, default string se non presente
	 *
	 * Parametri aggiuntivi nel terzo indice dell'array:
	 * 
	 * - label = Nice name da mostrare all'utente per identificare il campo
	 * - description = Nota aggiuntiva per spiegare cos'è questo campo se non fosse chiaro dal nome
	 * - select = Se il campo è selezionabile per la query (Alcuni potrebbero essere usati solo per il where o join ma non visibili nelle colonne -- valutare se serve)
	 * - order = Se la query è ordinabile per questo campo
	 * - where = Se la query è filtrabile per questo campo
	 * - virtual = Se è un campo virtual ( -- valutare se serve )
	 * - lookup = Se in automatico il valore da mostrare all'utente viene integrato col name della lookup passata
	 * 
	 * @param void
	 */
	public function setReportFields($fields)
	{
		$this->reportFields = (new Collection($fields))
		->map(function($tmp){
			$r = ['field' => $tmp[0]] + ($tmp[2] ?? []);
			$r['type'] = $tmp[1] ?? 'string';
			if(!isset($r['label']))			$r['label']			= $this->__getNiceName($r['field']);
			if(!isset($r['description']))	$r['description']	= '';
			if(!isset($r['select']))		$r['select'] 		= true;
			if(!isset($r['where']))			$r['where'] 		= true;
			if(!isset($r['order']))			$r['order'] 		= false;
			if(!isset($r['virtual']))		$r['virtual'] 		= false;
			if(!isset($r['lookup']))		$r['lookup'] 		= false;

			return $r;
		});

		$this->__setHelperVars();
	}

	/**
	 * Restituisce un nice nime da mostrare all'utente, composto da 
	 * Model => trasformato in base alla lookup definita in $reportNiceNames
	 * Field => humanize del campo con inflector
	 * @param  string $fieldName 
	 * @return string
	 */
	private function __getNiceName($fieldName)
	{
		list($model, $field) = $this->__splitModelField($fieldName);

		return $this->reportNiceNames[$model] . ' ' . Inflector::humanize($field);
	}

	/**
	 * Separa model (in singolo di base oppure anche più di uno) e il nome del campo
	 * usando l'ultimo punto della stringa come riferimento
	 * 
	 * @param  string $field 
	 * @return array [model, field]
	 */
	private function __splitModelField($field)
	{
		$pos = strrpos($field, '.');
		return [substr($field, 0, $pos), substr($field, $pos+1)];
	}

	/**
	 * Costruisce la select
	 * Selezioniamo tutti i campi, che poi verranno limitati solo successivamente
	 * @return void
	 */
	private function __buildSelect()
	{
		$this->query->enableAutofields(true);
	}

	private function __buildWhere()
	{
		
	}

	private function __buildOrder()
	{

	}

	/**
	 * Estrae i campi selezionabili, ordinabili e filtrabili
	 *
	 * @return void
	 */
	private function __setHelperVars()
	{
		$this->selectable = $this->reportFields
			->filter(function($r){
				return $r['select'] = true;
			})
			->extract('field')
			->toArray();

		$this->whereable = $this->reportFields
			->filter(function($r){
				return $r['where'] = true;
			})
			->extract('field')
			->toArray();

		$this->orderable = $this->reportFields
			->filter(function($r){
				return $r['order'] = true;
			})
			->extract('field')
			->toArray();
	}

	private function __reportWarning($t)
	{
		$this->reportWarnings[] = $t;
	}
}