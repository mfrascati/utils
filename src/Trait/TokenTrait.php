<?php
namespace Entheos\Utils\Trait;

use Cake\Utility\Security;
use Cake\Network\Exception\UnauthorizedException;
use Cake\Auth\DefaultPasswordHasher;
use \Firebase\JWT\JWT;

trait TokenTrait {

	/**
	 * Verifica i dati di autenticazione dell'utente e se corretti restituisce 
	 * il token per effettuare le chiamate
	 * @return string Token
	 */
	public function token()
	{
	    if (!$this->request->data('username'))
	        throw new UnauthorizedException('Invalid username or password');

	    $user = $this->Users->find()
	    	->where(['username' => $this->request->data('username'), 'active' => true])
		    ->first();

	    if (empty($user) || !(new DefaultPasswordHasher)->check($this->request->data('password'), $user->password))
	        throw new UnauthorizedException('Invalid username or password');

	    $this->set([
	        'success' => true,
	        'data' => [
	            'user' => $user,
	        ],
            'token' => $token = JWT::encode([
                'sub' => $user['id'],
                'exp' =>  time() + 604800
            ],
            Security::salt()),
	        '_serialize' => ['success', 'data', 'token']
	    ]);
	}
}