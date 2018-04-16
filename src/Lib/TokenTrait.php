<?php
namespace Entheos\Utils\Lib;

use Cake\Utility\Security;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Auth\DefaultPasswordHasher;
use \Firebase\JWT\JWT;

trait TokenTrait {

	/**
	 * Imposta le condizioni di controllo per l'utente attivo
	 * @var array
	 */
	public $activeUserConditions = ['active' => true];

	/**
	 * Specifica un finder per aggiungere condizioni o contain aggiuntivi (Definire sulla UsersTable)
	 * @var string
	 */
	public $userFinder = 'all';

	/**
	 * Verifica i dati di autenticazione dell'utente e se corretti restituisce 
	 * il token per effettuare le chiamate
	 * @return string Token
	 */
	public function token()
	{
	    if (!$this->request->getData('username') || !$this->request->getData('password'))
	        throw new UnauthorizedException(__('Nome utente o password non impostati'));

	    $user = $this->Users->find()
	    	->find($this->userFinder)
	    	->where(['Users.username' => $this->request->getData('username')])
	    	->where($this->activeUserConditions)
		    ->first();

	    if (empty($user) || !(new DefaultPasswordHasher)->check($this->request->getData('password'), $user->password))
	        throw new UnauthorizedException(__('Nome utente o password non validi'));

	    $this->__afterTokenValidatedCallback($user);
	    $this->__returnToken($user);
	}

	/**
	 * Aggiorna il token dell'utente loggato (autenticato con token valido)
	 * Restituisce unauhtorized se nel frattempo è stato modificato e 
	 * non rispetta  più le condizioni per essere attivo
	 * @return string Token
	 */
	public function refreshToken()
	{
	    $user = $this->Users->find()
	    	->find($this->userFinder)
	    	->where(['Users.id' => $this->Auth->user('id')])
	    	->where($this->activeUserConditions)
		    ->first();

		if(empty($user))
			throw new UnauthorizedException(__('Token non rinnovato'));

		$this->__afterTokenValidatedCallback($user);
		$this->__returnToken($user);
	}

	/**
	 * Definendo una funzione afterTokenValidated($user) su controller è possibile
	 * definire logica custom 
	 * @return void
	 */
	private function __afterTokenValidatedCallback($user)
	{
		if(method_exists($this, 'afterTokenValidated'))
			return $this->afterTokenValidated($user);
	}

	private function __returnToken($user)
	{
	    $this->set([
	        'success' => true,
	        'data' => [
	            'user' => $user,
	        ],
            'token' => $token = JWT::encode([
                'sub' => $user->id,
                'exp' =>  time() + 604800
            ],
            Security::getSalt()),
	        '_serialize' => ['success', 'data', 'token']
	    ]);
	}
}