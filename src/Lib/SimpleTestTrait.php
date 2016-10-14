<?php
namespace Entheos\Utils\Lib;

use ADmad\JwtAuth\Auth\JwtAuthenticate;
use Cake\Utility\Security;
use Firebase\JWT\JWT;

trait SimpleTestTrait
{
	protected $token = null;

	private function authenticateUser()
	{
	    Security::salt('secret-key');

	    $this->Registry = $this->createMock('Cake\Controller\ComponentRegistry');
	    $this->auth = new JwtAuthenticate($this->Registry, [
	        'userModel' => 'Users'
	    ]);

	    $this->token = JWT::encode(['sub' => 1], Security::salt());
	}

	private function getToken()
	{
		if($this->token === null)
			$this->authenticateUser();
		return $this->token;
	}

	/**
	 * Imposta gli header per ogni richiesta
	 * @param  boolean $auth Se true imposta il token per l'autenticazione
	 * @return void        
	 */
	public function setupRequest($auth = true)
	{
		$headers = ['Accept' => 'application/json'];
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

	public function stringToDatetime(&$string){
		$string = date('Y-m-d H:i:s', strtotime($string));
	}
}