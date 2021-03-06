<?php
namespace Entheos\Utils\Lib;

use ADmad\JwtAuth\Auth\JwtAuthenticate;
use Cake\Utility\Security;
use Firebase\JWT\JWT;

trait SimpleTestTrait
{
	// Cache per i token dei diversi utenti
	protected $tokens = [];

	// Utente attivo per il singolo test
	protected $user_id = 1;

	private function authenticateUser()
	{
	    Security::salt('secret-key');

	    $this->Registry = $this->createMock('Cake\Controller\ComponentRegistry');
	    $this->auth = new JwtAuthenticate($this->Registry, [
	        'userModel' => 'Users'
	    ]);

	    $this->tokens[$this->user_id] = JWT::encode(['sub' => $this->user_id], Security::salt());
	}

	private function getToken()
	{
		if(empty($this->tokens[$this->user_id]))
			$this->authenticateUser();
		return $this->tokens[$this->user_id];
	}

	/**
	 * Imposta gli header per ogni richiesta
	 *
	 * Opzioni aggiuntive:
	 * - user_id: passa un id per rendere possibile il test con token di differenti utenti con ruoli diversi
	 * 
	 * @param  boolean $auth Se true imposta il token per l'autenticazione
	 * @param  array $options Lista di opzioni aggiuntive
	 * @return void        
	 */
	public function setupRequest($auth = true, $options = [])
	{
		$headers = ['Accept' => 'application/json'];

		if(!empty($options['user_id']))
			$this->user_id = $options['user_id'];

		if($auth == true) {
			$headers['Authorization'] = 'Bearer '.$this->getToken();
		}

	    $this->configRequest([
	        'headers' => $headers,
	    ]);
	    $this->response = $this->createMock('Cake\Network\Response');
	}

	/**
	 * Wrapper per aggiungere un eventuale prefisso per il test delle chiamate api
	 * @param  string $url Url assoluto (inizia con /)
	 * @return string
	 */
	public function apiUrl($url){
		// return '/api'.$url;
		return $url;
	}

	public function apiGet($url){
		return $this->get($this->apiUrl($url));
	}

	public function apiPost($url, $data = []){
		$data = array_filter($data, function($k){ return !in_array($k, ['created', 'modified']); }, ARRAY_FILTER_USE_KEY);
		return $this->post($this->apiUrl($url), $data);
	}

	/**
	 * Wrapper per recuperare i dati della risposta dall'oggetto json
	 * @param  boolean $assert 
	 * @return mixed
	 */
	public function getResponseData($assert = true)
	{
		$response = json_decode($this->_response->body(), true);
		$data = !empty($response['data']) ? $response['data'] : false;

		if($assert){
			$this->assertResponseOk();
		}
		return $data;
	}

	public function stringToDatetime(&$string){
		$string = date('Y-m-d H:i:s', strtotime($string));
	}
}