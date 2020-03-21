<?php declare(strict_types = 1);

namespace Tests\Cases;

use Bunny;
use FastyBird\NodeLibs\Connections as NodeLibsConnections;
use FastyBird\NodeLibs\Consumers as NodeLibsConsumers;
use FastyBird\NodeLibs\Helpers as NodeLibsHelpers;
use FastyBird\NodeWebServer\Commands;
use FastyBird\NodeWebServer\Http;
use FastyBird\NodeWebServer\JsonApi;
use FastyBird\NodeWebServer\Middleware;
use IPub\SlimRouter;
use Mockery;
use Neomerx;
use Nette\DI;
use Ninjify\Nunjuck\TestCase\BaseMockeryTestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log;
use React\EventLoop;
use React\Promise;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';

final class JsonApiMiddlewareTest extends BaseMockeryTestCase
{

	public function testProcess()
	{
		$responseFactory = new Http\ResponseFactory();

		$logger = Mockery::mock(Log\LoggerInterface::class);

		$schema = Mockery::mock(Neomerx\JsonApi\Contracts\Schema\SchemaInterface::class);
		$schema
			->shouldReceive('getId')
			->andReturn(123)
			->getMock()
			->shouldReceive('getType')
			->andReturn('entity-type')
			->getMock()
			->shouldReceive('getAttributes')
			->andReturn([
				'value' => 'content',
			])
			->getMock()
			->shouldReceive('getRelationships')
			->andReturn([])
			->getMock()
			->shouldReceive('getLinks')
			->andReturn([])
			->getMock()
			->shouldReceive('hasResourceMeta')
			->andReturn(false);

		$schemaContainer = Mockery::mock(JsonApi\JsonApiSchemaContainer::class);
		$schemaContainer
			->shouldReceive('hasSchema')
			->withArgs([[
				'value' => 'content',
			]])
			->andReturn(true)
			->getMock()
			->shouldReceive('getSchema')
			->andReturn($schema);

		$container = Mockery::mock(DI\Container::class);
		$container
			->shouldReceive('getByType')
			->withArgs([Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface::class])
			->andReturn($schemaContainer)
			->times(1);

		$middleware = new Middleware\JsonApiMiddleware(
			$responseFactory,
			$logger,
			$container,
			'Author name',
			'Copyright'
		);

		$uri = Mockery::mock(UriInterface::class);
		$uri
			->shouldReceive('getPath')
			->andReturn('/api/path')
			->times(3)
			->getMock()
			->shouldReceive('getQuery')
			->andReturn('')
			->times(1)
			->getMock()
			->shouldReceive('getFragment')
			->andReturn('')
			->times(1);

		$request = Mockery::mock(ServerRequestInterface::class);
		$request
			->shouldReceive('getUri')
			->andReturn($uri)
			->getMock()
			->shouldReceive('getQueryParams')
			->andReturn([])
			->times(1);

		$responseBody = Mockery::mock(StreamInterface::class);
		$responseBody
			->shouldReceive('write');

		$response = Mockery::mock(Http\Response::class);
		$response
			->shouldReceive('getEntity')
			->andReturn(Http\ScalarEntity::from([
				'value' => 'content',
			]))
			->times(1)
			->getMock()
			->shouldReceive('hasAttribute')
			->withArgs([Http\ResponseAttributes::ATTR_TOTAL_COUNT])
			->andReturn(false)
			->getMock()
			->shouldReceive('getBody')
			->andReturn($responseBody)
			->getMock()
			->shouldReceive('withHeader')
			->andReturn($response)
			->times(5);

		$handler = Mockery::mock(RequestHandlerInterface::class);
		$handler
			->shouldReceive('handle')
			->withArgs([$request])
			->andReturn($response)
			->times(1);

		$response = $middleware->process($request, $handler);

		Assert::type(Http\Response::class, $response);
	}

}

$test_case = new JsonApiMiddlewareTest();
$test_case->run();
