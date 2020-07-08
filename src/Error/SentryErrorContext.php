<?php
namespace Entheos\Utils\Error;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;

class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents()
    {
        return [
            'CakeSentry.Client.beforeCapture' => 'setContext',
        ];
    }

    public function setContext(Event $event)
    {
        if (PHP_SAPI !== 'cli') {
            $request = $event->getSubject()->getRequest();
            $request->trustProxy = true;
            $raven = $event->getSubject()->getRaven();
            $raven->user_context([
                'username' => \Cake\Core\Configure::read('GlobalAuth.username'),
                'ip_address' => $request->clientIp()
            ]);
        } else {
            return ['cli' => true];
        }
    }
}
