<?php declare(strict_types = 1);

/**
 * WebServerExtension.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:WebServer!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           21.03.20
 */

namespace FastyBird\WebServer\DI;

use FastyBird\WebServer\Application;
use FastyBird\WebServer\Commands;
use FastyBird\WebServer\Http;
use FastyBird\WebServer\Middleware;
use FastyBird\WebServer\Router;
use Fig\Http\Message\RequestMethodInterface;
use IPub\SlimRouter;
use Nette;
use Nette\DI;
use Nette\Schema;
use React\EventLoop;
use stdClass;

/**
 * Simple web server extension container
 *
 * @package        FastyBird:WebServer!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class WebServerExtension extends DI\CompilerExtension
{

	public const ROUTER_MIDDLEWARE_TAG = 'middleware';

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'fbWebServer'
	): void {
		$config->onCompile[] = function (
			Nette\Configurator $config,
			DI\Compiler $compiler
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new WebServerExtension());
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'server' => Schema\Expect::structure([
				'address'     => Schema\Expect::string('127.0.0.1'),
				'port'        => Schema\Expect::int(8000),
				'certificate' => Schema\Expect::string(null)->nullable(),
			]),
			'static' => Schema\Expect::structure([
				'webroot' => Schema\Expect::string(null)->nullable(),
				'enabled' => Schema\Expect::bool(false),
			]),
			'cors'   => Schema\Expect::structure([
				'enabled' => Schema\Expect::bool(false),
				'allow'   => Schema\Expect::structure([
					'origin'      => Schema\Expect::string('*'),
					'methods'     => Schema\Expect::arrayOf('string')
						->default([
							RequestMethodInterface::METHOD_GET,
							RequestMethodInterface::METHOD_POST,
							RequestMethodInterface::METHOD_PATCH,
							RequestMethodInterface::METHOD_DELETE,
							RequestMethodInterface::METHOD_OPTIONS,
						]),
					'credentials' => Schema\Expect::bool(true),
					'headers'     => Schema\Expect::arrayOf('string')
						->default([
							'Content-Type',
							'Authorization',
							'X-Requested-With',
						]),
				]),
			]),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var stdClass $configuration */
		$configuration = $this->getConfig();

		$builder->addDefinition($this->prefix('routing.responseFactory'))
			->setType(Http\ResponseFactory::class);

		$builder->addDefinition($this->prefix('routing.router'))
			->setType(Router\Router::class);

		$builder->addDefinition('react.eventLoop')
			->setType(EventLoop\LoopInterface::class)
			->setFactory('React\EventLoop\Factory::create');

		$builder->addDefinition($this->prefix('command.server'))
			->setType(Commands\HttpServerCommand::class)
			->setArguments([
				'serverAddress'     => $configuration->server->address,
				'serverPort'        => $configuration->server->port,
				'serverCertificate' => $configuration->server->certificate,
			]);

		// Webserver middlewares
		$builder->addDefinition($this->prefix('middlewares.cors'))
			->setType(Middleware\CorsMiddleware::class)
			->setArguments([
				'enabled'          => $configuration->cors->enabled,
				'allowOrigin'      => $configuration->cors->allow->origin,
				'allowMethods'     => $configuration->cors->allow->methods,
				'allowCredentials' => $configuration->cors->allow->credentials,
				'allowHeaders'     => $configuration->cors->allow->headers,
			]);

		$builder->addDefinition($this->prefix('middlewares.staticFiles'))
			->setType(Middleware\StaticFilesMiddleware::class)
			->setArgument('publicRoot', $configuration->static->webroot)
			->setArgument('enabled', $configuration->static->enabled);

		$builder->addDefinition($this->prefix('middlewares.router'))
			->setType(Middleware\RouterMiddleware::class);

		// Applications
		$builder->addDefinition($this->prefix('application.console'))
			->setType(Application\Console::class);

		$builder->addDefinition($this->prefix('application.classic'))
			->setType(Application\Application::class);
	}

	/**
	 * {@inheritDoc}
	 */
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		$routerServiceName = $builder->getByType(SlimRouter\Routing\IRouter::class, true);

		if ($routerServiceName !== null) {
			$routerService = $builder->getDefinition($routerServiceName);
			assert($routerService instanceof DI\Definitions\ServiceDefinition);

			/**
			 * ROUTES
			 */

			$routesConfigurationServices = $builder->findByType(Router\IRoutes::class);

			foreach ($routesConfigurationServices as $routesConfigurationService) {
				if ($routesConfigurationService instanceof DI\Definitions\ServiceDefinition) {
					$routerService->addSetup('registerRoutes', [$routesConfigurationService]);
				}
			}

			$routerService->addSetup('injectRoutes');

			/**
			 * ROUTER MIDDLEWARE
			 */

			$middlewareServices = $builder->findByTag(self::ROUTER_MIDDLEWARE_TAG);

			// Sort by priority
			uasort($middlewareServices, function (array $a, array $b): int {
				$p1 = $a['priority'] ?? 10;
				$p2 = $b['priority'] ?? 10;

				if ($p1 === $p2) {
					return 0;
				}

				return ($p1 < $p2) ? -1 : 1;
			});

			foreach ($middlewareServices as $middlewareService => $middlewareServiceTags) {
				$routerService->addSetup('addMiddleware', [$builder->getDefinition($middlewareService)]);
			}
		}
	}

}
