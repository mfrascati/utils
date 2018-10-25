<?php
namespace Entheos\Utils\Lib;

use Entheos\Utils\Exception\WarningException;
use Entheos\Utils\Exception\ErrorException;
use Cake\Collection\Collection;
use Cake\Cache\Cache;
use Cake\Core\Configure;
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
	 * NB: Definire un finder 'report' sulla table!
	 *
	 * es, da controller:
	 * $data = $this->Offers->execReport($this->request->getData());
	 * 
	 * @param  array $params 
	 * @return array     
	 */
	public function generateReport($params)
	{
		if(empty($this->reportFields))
			throw new ErrorException("Campi report non definiti");

		// debug($this->reportFields->toArray());
		// debug($params) ;

		$this->query = $this->find()->find('report')->enableAutofields(true);

		$this->__buildWhere($params['where']);
		$this->__buildOrder($params['order']);
		$data = $this->__execute($params['select']);
		$headers = $this->__getHeaders($params['select']);

		return compact('data', 'headers');
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
	 * - label = Nice name da mostrare all'utente per identificare il campo (che può cambiarlo da front con 'alias')
	 * - description = Nota aggiuntiva per spiegare cos'è questo campo se non fosse chiaro dal nome
	 * - select = Se il campo è selezionabile per la query (Alcuni potrebbero essere usati solo per il where o join ma non visibili nelle colonne -- valutare se serve)
	 * - order = Se la query è ordinabile per questo campo
	 * - where = Se la query è filtrabile per questo campo
	 * - virtual = Se è un campo virtual ( serve ad escludere il campo dai where e per individuarlo meglio )
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
			list($model, $field) = $this->__splitModelField($tmp[0]);
			$fieldNameOriginal = $tmp[0];
			$r = ['field' => $this->__toAliasedFieldName($fieldNameOriginal), 'tableField' => $model.'.'.$field] + ($tmp[1] ?? []);
			if(!empty($this->reportTypeMap[$r['tableField']]))
				$r['type'] = $this->__mapType($this->reportTypeMap[$r['tableField']]);
			else
				$r['type'] = 'string'; // E' un virtual quindi
			if(!isset($r['label']))			$r['label']			= $this->__getNiceName($fieldNameOriginal); // Sovrascrivibile con 'alias'
			if(!isset($r['description']))	$r['description']	= '';
			if(!isset($r['select']))		$r['select'] 		= true;
			if(!isset($r['where']))			$r['where'] 		= $model == $this->tableName && empty($r['virtual']);
			if(!isset($r['order']))			$r['order'] 		= $model == $this->tableName && empty($r['virtual']);
			if(!isset($r['virtual']))		$r['virtual'] 		= false;
			if(!isset($r['lookup']))		$r['lookup'] 		= false;

			return $r;
		});

		$this->__setHelperVars();
	}

	private function __toAliasedFieldName($field)
	{
		return str_replace('.', '__', $field);
	}

	private function __toDbFieldName($field)
	{
		return str_replace('__', '.', $field);
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
	 * Mappa i tipi delle tabelle del db con tipi interni per gestire gli operatori:
	 * 
	 * - boolean
	 * - string
	 * - date
	 * - number
	 * 
	 * @param  string $type 
	 * @return string
	 */
	private function __mapType($type)
	{
		if(in_array($type, ['string', 'boolean']))
			return $type;
		if(in_array($type, ['integer', 'decimal', 'float', 'tinyinteger']))
			return 'number';
		if(in_array($type, ['date', 'datetime']))
			return 'date';
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
		return ($this->tableName != $model ? $this->reportModels[$model] . ' ' : '') . Inflector::humanize($field);
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
	 * @param  string $separator .|__
	 * @return array [model, field]
	 */
	private function __splitModelField($field, $mode = 'simple', $separator = '.')
	{
		$pos = strrpos($field, $separator);
		$model = substr($field, 0, $pos);
		if($mode != 'full'){
			$pos2 = strrpos($model, $separator);
			$model = substr($model, $pos2 !== false ? $pos2+1 : 0);
		}

		return [$model, substr($field, $pos+1)];
	}


	/**
	 * Costruisce il where della query.
	 * Si assicura prima che il campo filtrabile e che l'operatore passato sia adatto al campo
	 * @param  array $fields  strutturato in [['nome campo' => ['operator' => 'EQUAL', 'value' => 'XXX']], ecc..]
	 * @return void
	 */
	private function __buildWhere($fields)
	{
		foreach($fields as $field)
		{
			if(!isset($field['field']) || !isset($field['operator']) || !isset($field['value']))
				throw new WarningException("L'array di ricerca deve contentere gli indici: field, operator, value");
				
			if(!in_array($field['field'], $this->whereable))
				throw new WarningException("Non è permesso impostare filtri sul campo '$field[field]'");

			$type = $this->__getField($field['field'])['type'] ?? false;
			if(empty($type))
				throw new WarningException("Campo non configurato: '$field[field]'");
				
			if(!$this->__isValidWhereOperators($field['operator'], $type))
				throw new WarningException("Operatore '$field[operator]' non valido per il campo '$field[field]'");

			$f = $field['field'];
			$v = $field['value'];

			switch($field['operator'])
			{
				case 'EQUAL':
					$this->query->where([$f => $v]);
					break;
				case 'NOT_EQUAL':
					$this->query->where(["$f <>" => $v]);
					break;
				case 'GT':
					$this->query->where(["$f  >" => $v]);
					break;
				case 'GTE':
					$this->query->where(["$f >=" => $v]);
					break;
				case 'LT':
					$this->query->where(["$f  <" => $v]);
					break;
				case 'LTE':
					$this->query->where(["$f <=" => $v]);
					break;
				case 'NULL':
					$this->query->where(["$f IS" => null]);
					break;
				case 'NOT_NULL':
					$this->query->where(["$f IS NOT" => null]);
					break;
				case 'CONTAIN':
					$this->query->where(["$f LIKE" => "%$v%"]);
					break;
				case 'NOT_CONTAIN':
					$this->query->where(["$f NOT LIKE" => "%$v%"]);
					break;
				case 'LIKE':
					$this->query->where(["$f LIKE" => $v]);
					break;
				case 'NOT_LIKE':
					$this->query->where(["$f NOT LIKE" => $v]);
					break;
				case 'EMPTY':
					$this->query->where(["$f LIKE" => '']);
					break;
				case 'NOT_EMPTY':
					$this->query->where(["$f NOT LIKE" => '']);
					break;
			}
		}
			// debug($this->query);
	}

	private function __buildOrder()
	{

	}

	/**
	 * Esegue la query e fa un map delle righe per lasciare solo le colonne necessarie
	 * @param  array  $selectFields array di stringhe
	 * @return array
	 */
	private function __execute($selectFields)
	{
		return $this->query
		->formatResults(function ($results) use ($selectFields) {
		    return $results->map(function ($row) use ($selectFields) {
		    	$a = [];

		    	foreach($selectFields as $field) {
		    		// logd($field['field']);
		    		list($model, $fieldName) =  $this->__splitModelField($field['field'], 'full');
		    		$path = (new Collection(explode('.', $model)))
		    			->reject(function($val){ 
		    				return $val == $this->tableName;
		    			})
		    			->map(function($val){
		    				return Inflector::singularize(Inflector::tableize($val));
		    			})
		    			->toArray();
		    		$path[] = $fieldName;
		    		$walker = $row;
		    		foreach ($path as $property){
		    			$walker = $walker->{$property};
		    		}
		    		$a[] = $walker;
		    	}

		    	return $a;
		    });
		})
		->toArray();
	}

	private function __getField($field)
	{
		return $this->reportFields
			->filter(function($r) use ($field) { return $r['field'] == $field; })
			->first();
	}

	/**
	 * Costruisce gli headers del report, iterando i campi impostati in select
	 * Se è stato impostato 'alias' restituisce quello, altrimenti la label da config
	 * @param  array $selectFields 
	 * @return array
	 */
	private function __getHeaders($selectFields)
	{
		$headers = [];
		foreach($selectFields as $field) 
		{
			if(!empty($field['alias']))
				$headers[] = $field['alias'];
			else
				$headers[] = $this->__getField($field['field'])['label'];
		}
		return $headers;
	}

	/**
	 * Restituisce gli operatori validi per un tipo di campo
	 * @param  string $operator 
	 * @param  string $type 
	 * @return array
	 */
	private function __isValidWhereOperators($operator, $type)
	{
		return Configure::check("Lookup.ReportOperators.{$type}.{$operator}");
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