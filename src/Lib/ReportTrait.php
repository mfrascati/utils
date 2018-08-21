<?php
namespace Entheos\Utils\Lib;

use Entheos\Utils\Exception\ErrorException;
use Cake\Collection\Collection;
use Cake\Cache\Cache;
use Cake\Utility\Inflector;

/**
 * Permette di costruire una query con select, where (semplice) e order passati dal frontend
 * Trait da usare sulla classe Table
 */
trait ReportTrait
{
	protected $tableName;
	protected $reportModels = [];
	protected $reportFields = null;
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

		debug($this->reportFields->toArray());

		$this->query = $query;

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
	public function setReportModels($main, $fields)
	{
		$this->tableName = $main;
		$this->reportModels = $fields;
	}

	/**
	 * Configura i campi processabili
	 *
	 * Il primo valore dell'array è il field, nel formato Models.field, ed è obbligatorio
	 * 
	 * Parametri aggiuntivi nel secondo indice dell'array:
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
		$this->reportTypeMap = Cache::remember('reportTypeMap'.$this->tableName, 
			function(){
				$associated = array_keys($this->reportModels);
				unset($associated[array_search($this->tableName, $associated)]);
				return $this->find()->contain($associated)->getDefaultTypes();
			}, 
		'_cake_model_');

		$this->reportFields = (new Collection($fields))
		->map(function($tmp){
			$r = ['field' => $tmp[0], 'tableField' => join('.', $this->__splitModelField($tmp[0]))] + ($tmp[1] ?? []);
			$r['type'] = $this->__mapType($this->reportTypeMap[$r['tableField']]);
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

	public function getReportFieldsForFrontend()
	{
		return $this->reportFields
		->map(function($r){
			unset($r['virtual']);
			unset($r['lookup']);
			unset($r['tableField']);
			return $r;
		});
	}

	/**
	 * Mappa i tipi delle tabelle del db con tipi interni 
	 * @param  string $type 
	 * @return string
	 */
	private function __mapType($type)
	{
		if(in_array($type, ['integer', 'decimal', 'float', 'tinyinteger']))
			return 'numeric';
		if(in_array($type, ['string', 'date', 'datetime', 'boolean']))
			return $type;
		else
			throw new ErrorException("Tipo non gestito in mapType: $type");
	}

	/**
	 * Restituisce un nice nime da mostrare all'utente, composto da 
	 * Model => trasformato in base alla lookup definita in $reportModels
	 * Field => humanize del campo con inflector
	 * @param  string $fieldName 
	 * @return string
	 */
	private function __getNiceName($fieldName)
	{
		list($model, $field) = $this->__splitModelField($fieldName, 'full');
		return $this->reportModels[$model] . ' ' . Inflector::humanize($field);
	}

	/**
	 * Separa model (in singolo di base oppure anche più di uno) e il nome del campo
	 * usando l'ultimo punto della stringa come riferimento
	 *
	 * Se mode simple in caso di model annidati viene restituito solo l'ultimo
	 * Se mode full viene restituita l'intera gerarchia
	 * 
	 * @param  string $field 
	 * @param  string $mode simple|full 
	 * @return array [model, field]
	 */
	private function __splitModelField($field, $mode = 'simple')
	{
		$pos = strrpos($field, '.');
		$model = substr($field, 0, $pos);
		if($mode != 'full'){
			$pos2 = strrpos($model, '.');
			$model = substr($model, $pos2 !== false ? $pos2+1 : 0);
		}

		return [$model, substr($field, $pos+1)];
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