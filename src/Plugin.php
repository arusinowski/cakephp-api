<?php
declare(strict_types=1);

/**
 * Copyright 2016 - 2019, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2016 - 2019, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\Api;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerApplicationInterface;
use Cake\Core\ContainerInterface;
use Cake\Core\Exception\CakeException;
use Cake\Core\PluginApplicationInterface;
use CakeDC\Api\Command\ServiceRoutesCommand;
use CakeDC\Api\Middleware\ParseApiRequestMiddleware;
use CakeDC\Api\Middleware\ProcessApiRequestMiddleware;

/**
 * Api plugin
 */
class Plugin extends BasePlugin
{
    /**
     * Container
     *
     * @var \Cake\Core\ContainerInterface|null
     */
    protected ?ContainerInterface $container = null;

    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (!in_array(ContainerApplicationInterface::class, class_implements($app), true)) {
            throw new CakeException(
                __('Your base application class is not implementing ContainerApplicationInterface.')
            );
        }
        /** @var \Cake\Core\ContainerApplicationInterface|\Cake\Core\PluginApplicationInterface $app */
        $this->container = $app->getContainer();
    }

    /**
     * @inheritDoc
     */
    public function routes($routes): void
    {
        $middlewares = Configure::read('Api.Middleware', []);
        foreach ($middlewares as $alias => $middleware) {
            $class = $middleware['class'];
            if (array_key_exists('request', $middleware)) {
                $requestClass = $middleware['request'];
                $request = new $requestClass();
                if (array_key_exists('method', $middleware)) {
                    $request = $request->{$middleware['method']}();
                }
                if (array_key_exists('params', $middleware)) {
                    $options = $middleware['params'];
                    $routes->registerMiddleware($alias, new $class($request, $options));
                } else {
                    $routes->registerMiddleware($alias, new $class($request));
                }
            } else {
                if (array_key_exists('params', $middleware)) {
                    $options = $middleware['params'];
                    $routes->registerMiddleware($alias, new $class($options));
                } elseif (
                    ($class === ParseApiRequestMiddleware::class) ||
                    ($class === ProcessApiRequestMiddleware::class)
                ) {
                    $routes->registerMiddleware($alias, new $class($this->container));
                } else {
                    $routes->registerMiddleware($alias, new $class());
                }
            }
        }

        parent::routes($routes);
    }

    /**
     * Add console commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        return $commands->add('service routes', ServiceRoutesCommand::class);
    }
}
