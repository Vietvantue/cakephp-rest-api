<?php

namespace RestApi\Event;

use RestApi\Utility\ApiRequestLogger;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;

/**
 * Event listner for API requests.
 *
 * This class binds the different events and performs required operations.
 */
class ApiRequestHandler implements EventListenerInterface
{

    /**
     * Event bindings.
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'Dispatcher.beforeDispatch' => [
                'callable' => 'beforeDispatch',
                'priority' => 0,
            ],
            'Dispatcher.afterDispatch' => [
                'callable' => 'afterDispatch',
                'priority' => 9999,
            ],
            'Controller.shutdown' => [
                'callable' => 'shutdown',
                'priority' => 9999,
            ],
        ];
    }

    /**
     * Handles incoming request and its data.
     *
     * @param Event $event The beforeDispatch event
     */
    public function beforeDispatch(Event $event)
    {
        $this->buildResponse($event);
        $request = $event->data['request'];
        if ('OPTIONS' === $request->method()) {
            $event->stopPropagation();
            $response = $event->data['response'];
            $response->statusCode(200);

            return $response;
        }

        if (empty($request->data)) {
            $request->data = $request->input('json_decode', true);
        }
    }

    /**
     * Updates response headers.
     *
     * @param Event $event The afterDispatch event
     */
    public function afterDispatch(Event $event)
    {
        $this->buildResponse($event);
    }

    /**
     * Logs the request and response data into database.
     *
     * @param Event $event The shutdown event
     */
    public function shutdown(Event $event)
    {
        $request = $event->subject()->request;
        if ('OPTIONS' === $request->method()) {
            return;
        }

        if (Configure::read('ApiRequest.log')) {
            ApiRequestLogger::log($request, $event->subject()->response);
        }
    }

    /**
     * Prepares the response object with content type and cors headers.
     *
     * @param Event $event The event object either beforeDispatch or afterDispatch
     *
     * @return bool true
     */
    private function buildResponse(Event $event)
    {
        $request = $event->data['request'];
        $response = $event->data['response'];
        $response->type('json');
        $response->cors($request)
            ->allowOrigin(Configure::read('ApiRequest.cors.origin'))
            ->allowMethods(Configure::read('ApiRequest.cors.allowedMethods'))
            ->allowHeaders(Configure::read('ApiRequest.cors.allowedHeaders'))
            ->allowCredentials()
            ->maxAge(Configure::read('ApiRequest.cors.maxAge'))
            ->build();

        return true;
    }
}
