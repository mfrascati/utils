<?php
namespace Entheos\Utils\Error;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Sentry\State\Scope;

use function Sentry\configureScope as sentryConfigureScope;

class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            'CakeSentry.Client.beforeCapture' => 'setContext',
        ];
    }

    public function setContext(Event $event)
    {
        if (PHP_SAPI !== 'cli') {
            /** @var ServerRequest $request */
            $request = $event->getData('request') ?? ServerRequestFactory::fromGlobals();
            $request->trustProxy = true;

            sentryConfigureScope(function (Scope $scope) use ($request, $event) {
                $exception = $event->getData('exception');
                if ($exception) {
                    assert($exception instanceof \Exception);
                    $scope->setTag('status', $exception->getCode());
                }
                $scope->setUser([
                    'username' => \Cake\Core\Configure::read('GlobalAuth.username'),
                    'ip_address' => $request->clientIp()
                ]);
                $scope->setExtras([
                    // 'foo' => 'bar',
                    'request_attrs' => $request->getAttributes(),
                ]);
            });
        }
    }
}
