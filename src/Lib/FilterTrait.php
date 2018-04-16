<?php
namespace Entheos\Utils\Lib;

use Cake\Utility\Hash;

trait FilterTrait {

	// La whitelist va definita sul controller nel formato [Model][field] => 'data_type'

	// public $filterWhitelist = [
	//     'Cars' => [
	//         'id'        => 'integer',
	//         'targa'     => 'like',
	//         'telaio'    => 'like',
	//     ],
	//     'Details' => [
	//         'origine'   => 'string',
	//         'sede'      => 'string',
	//     ]
	// ];
	// 
	// I dati vanno passati nel formato Model[field]
	// 
	// Per i filtri di tipo "range" date-range, integer-range i diversi valori vanno separati da pipe '|'
	// mentre il campo di filtro rimane sempre uno e uno soltanto
	// 
	// Es.
	// Model[data]  => '2016-05-10|2016-05-10'

	protected $whitelist = []; // Usato come copia interna
	protected $filteredFields = [];

	/**
	 * Processa tutti i campi del this->request->data, controllando che siano nella whitelist e
	 * li prepara e appende alla query, a seconda del tipo di dato specificato
	 * 
	 * @param Query $query
	 * @return Query
	 */
	public function applyFilter($query)
	{
		if(empty($this->filterWhitelist))
			throw new \Exception('Definire sul controller la proprietà public $filterWhitelist');

		$this->paging = $this->request->getParam('paging');
		$this->request->data = Hash::flatten($this->request->data);
		$this->whitelist = Hash::flatten($this->filterWhitelist);

		foreach($this->request->data as $field=>$value)
		{
			// Se il campo non è accettato, lo rimuovo
			// Se il campo è custom lo rimuovo dalle normali condizioni perché lo gestisco a mano
			// Se il valore è null lo rimuovo (non se è 0 perché può essere un valore valido per il filtro)
			if(!isset($this->whitelist[$field]) || $value === null)
				continue;

			$query = $this->_buildQueryType($query, $field, $value);
		}
		return $query;
	}

	/**
	 * Implementa le funzioni di base per la paginazione filtrata
	 * Funge da wrapper che richiama le funzioni comuni
	 *
	 * Il filtro per condizioni non deve essere impostato su $query, viene settato qui
	 * Tutte le altre condizioni e contain vanno impostate prima
	 * 
	 * @param  Query $query 
	 * @return set dati per la view
	 */
	public function filterPaginate($query)
	{
		$this->Crud->action()->disable();

		$query = $this->applyFilter($query);

		$this->paginate = Hash::merge($this->paginate, $this->paging);
		
		$data = $this->paginate($query);

		$this->set([
		    'success' => true,
		    'data' => !$data->isEmpty() ? $data : [],
		    'pagination' => $this->__paginationData(),
		    '_serialize' => ['success', 'data', 'pagination']
		]);
		return true;
	}

	/**
	 * Processa la chiave e il valore per farli diventare una query valida
	 * La prima parte gestisce dei valori speciali per query IS NULL / IS EMPTY
	 * Controlla che il valore sia del tipo specificato in dichiarazione, se serve forzandone il cast
	 * 
	 * @param   Query $query
	 * @param   $key   
	 * @param   $value 
	 * @return Query restituisce la query aggiornata (oppure no se i parametri non rispettavano i requisiti)
	 */
	protected function _buildQueryType($query, $key, $value) 
	{
		if($value === 'isNull')
			return $query->where(["$key IS" => null]);
		if($value === 'isNotNull')
			return $query->where(["$key IS NOT" => null]);
		if($value === 'isNotEmpty')
			return $query->where(["$key <>" => '']);
		if($value === 'isNotZero')
			return $query->where(["$key <>" => 0]);

		switch($this->whitelist[$key])
		{
			case 'like' :
				if(!empty($value))
					return $query->where(["$key LIKE" => "%$value%"]);
				break;

			case 'integer' :
				$value = (int)$value || $value === 0 ? (int)$value : null;
				if($value !== null)
					return $query->where([$key => $value]);
				break;

			case 'boolean':
				$values = [
					'0' => false, 'NO' => false,
					'1' => true,  'SI' => true,
				];
				if(isset($values[$value]))
					return $query->where([$key => (bool)$values[$value]]);
				break;

			case 'date' : 
				if(!empty($value)){
					return $query->where([
						"$key >=" => $this->__formatDate($value, 'start'),
						"$key <=" => $this->__formatDate($value, 'end'),
					]);
				}
				break;

			case 'integer-range' : case 'date-range':
				if(!empty($value)){
					$tmp = explode('|', $value);
					if(is_array($tmp))
					{
						if($this->whitelist[$key] == 'integer-range'){
							$tmp[0] = (int)$tmp[0];
							$tmp[1] = (int)$tmp[1];
						}elseif($this->whitelist[$key] == 'date-range'){
							$tmp[0] = $this->__formatDate($tmp[0], 'start');
							$tmp[1] = $this->__formatDate($tmp[1], 'end');
						}
						
						return $query->where([
							"$key >=" => $tmp[0],
							"$key <=" => $tmp[1],
						]);
					}
				}
				break;

			case 'string[]' :
				if(!empty($value)){
					$value = explode('|', $value);
					return $query->where([$key => $value], [$key => 'string[]']);
				}
				break;

			case 'string' :
				return $query->where([$key => $value]);
				break;

			case 'custom' :
				// I filtri custom sono gestiti direttamente sul controller di appartenenza come
				// condizioni o finder aggiuntivi
				break;

			default :
				throw new \Exception("Tipo di dato non valido per il filtro");
				
		}
		return $query;
	}

	private function __formatDate($date, $which)
	{
		return date('Y-m-d ', strtotime($date)).($which == 'start' ? '00:00:00' : '23:59:59');
	}

	/**
	 * Riorganizza i meta dati della paginazione per rimandarli in risposta
	 * @return array
	 */
	private function __paginationData()
	{
	    $pagination = $this->request->paging[$this->modelClass];
	    $paginationResponse = [
	        'page_count' => $pagination['pageCount'],
	        'current_page' => $pagination['page'],
	        'has_next_page' => $pagination['nextPage'],
	        'has_prev_page' => $pagination['prevPage'],
	        'count' => $pagination['count'],
	        'limit' => $pagination['limit']
	    ];
	    return $paginationResponse;
	}

}