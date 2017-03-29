<?php
namespace Entheos\Utils\Lib;

use Cake\Utility\Security;
use Cake\Network\Exception\UnauthorizedException;
use Cake\Auth\DefaultPasswordHasher;
use \Firebase\JWT\JWT;

trait TokenTrait {

	/**
	 * Imposta le condizioni di controllo per l'utente attivo
	 * @var array
	 */
	public $activeUserConditions = ['active' => true];

	/**
	 * Verifica i dati di autenticazione dell'utente e se corretti restituisce 
	 * il token per effettuare le chiamate
	 * @return string Token
	 */
	public function token()
	{
	    if (!$this->request->data('username') || !$this->request->data('password'))
	        throw new UnauthorizedException(__('Nome utente o password non impostati'));

	    $user = $this->Users->find()
	    	->where(['username' => $this->request->data('username')])
	    	->where($this->activeUserConditions)
		    ->first();

	    if (empty($user) || !(new DefaultPasswordHasher)->check($this->request->data('password'), $user->password))
	        throw new UnauthorizedException(__('Nome utente o password non validi'));

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
	    	->where(['id' => $this->Auth->user('id')])
	    	->where($this->activeUserConditions)
		    ->first();

		if(empty($user))
			throw new UnauthorizedException(__('Token non rinnovato'));

		$this->__returnToken($user);
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
            Security::salt()),
	        '_serialize' => ['success', 'data', 'token']
	    ]);
	}
}