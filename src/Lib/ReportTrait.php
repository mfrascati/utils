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
	protected $reportModels		= [];
	protected $reportFields		= null;
	protected $dependentFields  = [];
	protected $selectable		= [];
	protected $whereable		= [];
	protected $orderable		= [];
	protected $reportHeaders	= [];
	protected $reportHeaderTypes = [];
	protected $reportData		= [];
	protected $reportWarnings	= [];
	protected $cachedTypes		= [];

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
		$return = 'data'; // 'data'|'download';
		if(empty($this->reportFields))
			throw new ErrorException("Campi report non definiti");

		ini_set('memory_limit','450M');

		// debug($this->reportFields->toArray());
		// debug($params) ;

		$containedModels = $this->__getContainedModels($params['select']);
		$this->query = $this->find()->contain($containedModels)->enableAutoFields(true);
		// $this->query->getConnection()->logQueries(true);

		$this->__buildWhere($params['where']);
		$this->__buildOrder($params['order']);
		$this->__getHeaders($params['select']);
		$this->__execute($params['select']);
		// logd($this->reportData);

		// return ['data' => $this->reportData, 'headers' => $this->reportHeaders];
	}

	public function getReportData()
	{
		return $this->reportData;
	}

	public function getReportHeaders()
	{
		return $this->reportHeaders;
	}

	public function getExcel()
	{
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$worksheet = $spreadsheet->getActiveSheet();

		$worksheet->fromArray($this->reportHeaders, null, 'A1');
		$rowIdx = 2;

		foreach($this->reportData as $r)
		{
			// debug($r);
		    $worksheet->fromArray($r, null, 'A'.$rowIdx);
		    $rowIdx++;
		}

		$n = 0;
		foreach($this->reportHeaderTypes as $type)
		{
			$n++;
			$colIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);

			if(in_array($type, ['string', 'boolean', 'date']))
				continue;

			$range = $colIdx.'1:'.$colIdx.$rowIdx;

			if($type == 'integer'){
				$spreadsheet->getActiveSheet()->getStyle($range)->getNumberFormat()
				    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
			}
			elseif($type == 'decimal'){
				$spreadsheet->getActiveSheet()->getStyle($range)->getNumberFormat()
				    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
			}
		}

		$styleArray = [
		    'font' => [
		        'bold' => true,
		    ],
		    'borders' => [
		        'bottom' => [
		            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
		        ],
		    ],
		];

		$spreadsheet->getActiveSheet()->getStyle('A1:'.$colIdx.'1')->applyFromArray($styleArray);

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save('php://output');

        // In controller usa prima della chiamata
        
        // $this->autoRender = false;
        // ob_start();

        // Dopo la chiamata
        // 
        // $response = $this->response
        //     ->withType('xls')
        //     ->withDownload('nomeFile.xls')
        //     ->withDisabledCache()
        //     ->withStringBody(ob_get_clean());

        // return $response;
	}

	/**
	 * Recupera dinamicamente i model per la contain, escludendo quello del model principale
	 * Non posso limitare dinamicamente i campi in select per via dei virtual!
	 * @param  array $selected 
	 * @return array
	 */
	public function __getContainedModels($selected)
	{
		// debug($selected);
		$models = array_unique(array_map(function($r){
			return $this->__splitModelField($this->__toDbFieldName($r['field']), 'full')[0];
		}, $selected));

		$models = array_filter($models, function($r){
			return $r != $this->tableName;
		});

		// Gestisce model dipendenti
		array_walk($selected, function($r) use (&$models){
			if(!empty($this->dependentFields[$r['field']]) && !in_array($this->dependentFields[$r['field']] ,$models))
				$models[] = $this->dependentFields[$r['field']];
		});

		return $models;
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
	 * - dependsOn = Se dipende da un model diverso dal proprio (Es, telefono è un virtual che dipende da una hasMany)
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
			elseif(empty($r['type']))
				$r['type'] = 'string'; // E' un virtual quindi
			if(!isset($r['label']))			$r['label']			= $this->__getNiceName($fieldNameOriginal); // Sovrascrivibile con 'alias'
			if(!isset($r['description']))	$r['description']	= '';
			if(!isset($r['select']))		$r['select'] 		= true;
			if(!isset($r['where']))			$r['where'] 		= $model == $this->tableName && empty($r['virtual']);
			if(!isset($r['order']))			$r['order'] 		= $model == $this->tableName && empty($r['virtual']);
			if(!isset($r['virtual']))		$r['virtual'] 		= false;
			if(!isset($r['lookup']))		$r['lookup'] 		= false;
			if(!isset($r['dependsOn']))		$r['dependsOn'] 	= null;

			return $r;
		});

		$this->dependentFields = $this->reportFields
			->filter(function($r){return $r['dependsOn'];})
			->combine('field', 'dependsOn')
			->toArray();

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
	 * - integer
	 * - decimal
	 * 
	 * @param  string $type 
	 * @return string
	 */
	private function __mapType($type)
	{
		if(in_array($type, ['string', 'boolean']))
			return $type;
		if(in_array($type, ['integer', 'tinyinteger']))
			return 'integer';
		if(in_array($type, ['decimal', 'float']))
			return 'decimal';
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
	 * @param  array $fields  strutturato in [['field' => ['name' => nome campo'], 'operator' => 'EQUAL', 'value' => 'XXX']], ecc..]
	 * @return void
	 */
	private function __buildWhere($fields)
	{
		foreach($fields as $field)
		{
			if(!isset($field['field']['name']) || !isset($field['operator']))
				throw new WarningException("L'array di ricerca deve contentere gli indici: field, operator");

			$field['field'] = $field['field']['name'];

			if(!in_array($field['field'], $this->whereable))
				throw new WarningException("Non è permesso impostare filtri sul campo '$field[field]'");

			$type = $this->__getField($field['field'])['type'] ?? false;
			if(empty($type))
				throw new WarningException("Campo non configurato: '$field[field]'");
				
			if(!$this->__isValidWhereOperators($field['operator'], $type))
				throw new WarningException("Operatore '$field[operator]' non valido per il campo '$field[field]'");

			$f = $this->__toDbFieldName($field['field']);
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

	private function __buildOrder($fields)
	{
		$order = [];
		foreach($fields as $field)
		{
			$field['field'] = $this->__toDbFieldName($field['field']);
			$order[$field['field']] = $field['direction'];
		}

		return $this->query->order($order);
	}

	/**
	 * Esegue la query e fa un map delle righe per lasciare solo le colonne necessarie
	 * @param  array  $selectFields array di stringhe
	 * @return array
	 */
	private function __execute($selectFields)
	{
		$this->reportData = $this->query
		->formatResults(function ($results) use ($selectFields) {
		    return $results->map(function ($row) use ($selectFields) {
		    	$a = [];

		    	foreach($selectFields as $field) {
		    		// logd($field['field']);
		    		// $field = $this->__toDbFieldName($field);
		    		list($model, $fieldName) =  $this->__splitModelField($this->__toDbFieldName($field['field']), 'full');
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
			    		if(!isset($walker->{$property}))
			    		{
			    			$walker = null;
			    			continue;
			    		}
		    			$walker = $walker->{$property};
		    		}
		    		$value = $this->__processValueWithType($field['field'], $walker);
		    		$a[] = $value;
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
	 * Converte il valore a seconda del tipo di dato
	 * @param  string $field 
	 * @param  mixed $value 
	 * @return string
	 */
	private function __processValueWithType($field, $value)
	{
		if(isset($this->cachedTypes[$field]))
			$type = $this->cachedTypes[$field];
		else {
			$type = $this->__getField($field)['type'];
			$this->cachedTypes[$field] = $type;
		}
		// debug($field); debug($type);
		
		if($type == 'date')
			return empty($value) ? $value : $value->format('d/m/Y');
		elseif($type == 'boolean')
		{
			if($value === true)
				return 'SI';
			elseif($value === false)
				return 'NO';
			return $value;
		}

		return $value;
	}

	/**
	 * Costruisce gli headers del report, iterando i campi impostati in select
	 * Se è stato impostato 'alias' restituisce quello, altrimenti la label da config
	 * @param  array $selectFields 
	 * @return array
	 */
	private function __getHeaders($selectFields)
	{
		foreach($selectFields as $field) 
		{
			$f = $this->__getField($field['field']);
			$this->reportHeaderTypes[$field['field']] = $f['type'];

			if(!empty($field['label']))
				$this->reportHeaders[] = $field['label'];
			else
				$this->reportHeaders[] = $f['label'];
		}
		// debug($this->reportHeaders);
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