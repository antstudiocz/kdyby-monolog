<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\DI;

use Kdyby\Monolog\Handler\FallbackNetteHandler;
use Kdyby\Monolog\Logger as KdybyLogger;
use Kdyby\Monolog\Processor\PriorityProcessor;
use Kdyby\Monolog\Processor\TracyExceptionProcessor;
use Kdyby\Monolog\Processor\TracyUrlProcessor;
use Kdyby\Monolog\Tracy\BlueScreenRenderer;
use Kdyby\Monolog\Tracy\MonologAdapter;
use Nette;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\Helpers as DIHelpers;
use Nette\DI\Statement;
use Nette\PhpGenerator\ClassType as ClassTypeGenerator;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Psr\Log\LoggerAwareInterface;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Integrates the Monolog seamlessly into your Nette Framework application.
 */
class MonologExtension extends \Nette\DI\CompilerExtension
{

	const TAG_FORMATTER = 'monolog.formatter';
	const TAG_HANDLER = 'monolog.handler';
	const TAG_PROCESSOR = 'monolog.processor';
	const TAG_PRIORITY = 'monolog.priority';
	const TAG_LOGGER = 'monolog.logger';

	/** @var array */
	public $defaults = [ // @phpstan-ignore-line
		'formatters' => [
			'chromePHP' => \Monolog\Formatter\ChromePHPFormatter::class,
			'fluentd' => \Monolog\Formatter\FluentdFormatter::class,
			'gelfMessage' => \Monolog\Formatter\GelfMessageFormatter::class,
			'html' => \Monolog\Formatter\HtmlFormatter::class,
			'json' => \Monolog\Formatter\JsonFormatter::class,
			'line' => \Monolog\Formatter\LineFormatter::class,
			'loggly' => \Monolog\Formatter\LogglyFormatter::class,
			'mongoDB' => \Monolog\Formatter\MongoDBFormatter::class,
			'normalizer' => \Monolog\Formatter\NormalizerFormatter::class,
			'scalar' => \Monolog\Formatter\ScalarFormatter::class,
			'wildfire' => \Monolog\Formatter\WildfireFormatter::class,
		],
		'processors' => [
			'git' => \Monolog\Processor\GitProcessor::class,
			'introspection' => \Monolog\Processor\IntrospectionProcessor::class,
			'memoryPeakUsage' => \Monolog\Processor\MemoryPeakUsageProcessor::class,
			'memoryUsage' => \Monolog\Processor\MemoryUsageProcessor::class,
			'processId' => \Monolog\Processor\ProcessIdProcessor::class,
			'psrLogMessage' => \Monolog\Processor\PsrLogMessageProcessor::class,
			'tag' => \Monolog\Processor\TagProcessor::class,
			'uid' => \Monolog\Processor\UidProcessor::class,
			'web' => \Monolog\Processor\WebProcessor::class,
		],
		'handlers' => [
			'errorLog' => [
				'class' => \Monolog\Handler\ErrorLogHandler::class,
			],
		],
	];

	/** @var array */
	protected $config = []; // @phpstan-ignore-line

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'handlers' => Expect::anyOf(Expect::arrayOf('array'), 'false')->default([]),
			'processors' => Expect::anyOf(Expect::arrayOf('Nette\DI\Definitions\Statement'), 'false')->default([]),
			'name' => Expect::string('app'),
			'hookToTracy' => Expect::bool(TRUE),
			'tracyBaseUrl' => Expect::string(),
			'registerFallback' => Expect::bool( TRUE),
			'usePriorityProcessor' => Expect::bool(TRUE),
			'accessPriority' => Expect::string(ILogger::INFO),
			'logDir' => Expect::string(),
			'loggers' => Expect::anyOf(Expect::arrayOf('array'), 'false')->default([]),
		])->castTo('array');
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		//FORMATTERS
		$formatters = $this->defaults['formatters'];
		if (is_array($config)) {
			$formatters = ($config['formatters'] ?? []) + $formatters;
		}
		if ($formatters) {
			foreach ($formatters as $formatterName => $formatter) {
				$this->compiler->loadDefinitionsFromConfig([
					$this->prefix('formatter.' . $formatterName) => [
						'factory' => $formatter,
						'tags' => [
							self::TAG_FORMATTER,
						],
					],
				]);
			}
		}

		//PROCESSORS
		$processors = $this->defaults['processors'];
		if (is_array($config)) {
			$processors = ($config['processors'] ?? []) + $processors;
		}
		foreach ($processors as $processorName => $processor) {
			$this->compiler->loadDefinitionsFromConfig([
				$this->prefix('processor.' . $processorName) => [
					'factory' => $processor,
					'tags' => [
						self::TAG_PROCESSOR,
					],
				],
			]);
		}

		//HANDLERS
		$handlers = $this->defaults['handlers'];
		if (is_array($config)) {
			$handlers = ($config['handlers'] ?? []) + $handlers;
		}
		if (!empty($handlers)) {
			foreach ($handlers as $handlerName => $handlerConfig) {
				if (is_string($handlerConfig)) {
					$message = 'Wrong handler format. Handlers configuration must be in this format:' .
						"\n\nhandlers:\n\t{$handlerName}:\n\t\tclass: $handlerConfig\n\t\t[formatter: formatterName]\n\t\t[processors: [processorName, ...]]";
					throw new \Nette\UnexpectedValueException($message);
				}

				$this->compiler->loadDefinitionsFromConfig([
					$serviceName = $this->prefix('handler.' . $handlerName) => [
						'factory' => $handlerConfig['class'],
						'tags' => [
							'kdyby.' . self::TAG_HANDLER,
						],
					],
				]);
				$handler = $builder->getDefinition($serviceName);

				if (isset($handlerConfig['formatter'])) {
					$handler->addSetup('?->setFormatter(?)', [ // @phpstan-ignore-line
						'@self',
						$builder->getDefinition($this->prefix('formatter.' . $handlerConfig['formatter'])),
					]);
				}

				if (isset($handlerConfig['processors'])) {
					foreach (array_reverse($handlerConfig['processors']) as $handlerName) {
						$handler->addSetup('?->pushProcessor(?)', [ // @phpstan-ignore-line
							'@self',
							$builder->getDefinition($this->prefix('processor.' . $handlerName)),
						]);
					}
				}
			}
		}


		//LOGGERS
		$loggers = [];
		if (is_array($config)) {
			$loggers = ($config['loggers'] ?? []);
		}
		if (isset($loggers)) {
			foreach ($loggers as $loggerName => $loggerConfig) {
				if ($loggerName === 'global') {
					continue;
				}

				if (is_string($loggerConfig)) {
					$message = 'Wrong logger format. Loggers configuration must be in this format:' .
						"\n\nloggers:\n\t{$loggerName}:\n\t\tclass: $loggerConfig\n\t\t[processors: [processorName, ...]]\n\t\t[handlers: [handlerName, ...]]";
					throw new \Nette\UnexpectedValueException($message);
				}

				$this->compiler->loadDefinitionsFromConfig([
					$serviceName = $this->prefix('logger.' . $loggerName) => [
						'factory' => $loggerConfig['class'],
						'arguments' => [
							'name' => $loggerName,
						],
						'tags' => [
							self::TAG_LOGGER,
						],
						'autowired' => $loggerConfig['autowired'] ? [$loggerConfig['autowired']] : TRUE, //TODO: are u sure?
					],
				]);

				$logger = $builder->getDefinition($serviceName);

				if (isset($loggerConfig['processors'])) {
					foreach (array_reverse($loggerConfig['processors']) as $processorName) {
						$logger->addSetup('?->pushProcessor(?)', [ // @phpstan-ignore-line
							'@self',
							$builder->getDefinition($this->prefix('processor.' . $processorName)),
						]);
					}
				}

				if (isset($loggerConfig['handlers'])) {
					foreach (array_reverse($loggerConfig['handlers']) as $handlerName) {
						$logger->addSetup('?->pushHandler(?)', [ // @phpstan-ignore-line
							'@self',
							$builder->getDefinition($this->prefix('handler.' . $handlerName)),
						]);
					}
				}
			}
		}

		if (is_array($config)) {
			unset($config['handlers']); //handled by this extension
			unset($config['processors']); //handled by this extension
		}
		$this->setConfig($config);

		$builder = $this->getContainerBuilder();
		$this->config['logDir'] = self::resolveLogDir($builder->parameters);
		$config = $this->config;
		self::createDirectory($config['logDir']);

		if (!isset($builder->parameters[$this->name]) || (is_array($builder->parameters[$this->name]) && !isset($builder->parameters[$this->name]['name']))) {
			$builder->parameters[$this->name]['name'] = $config['name'];
		}

		if (!isset($builder->parameters['logDir'])) { // BC
			$builder->parameters['logDir'] = $config['logDir'];
		}

		$builder->addDefinition($this->prefix('logger'))
			->setFactory(KdybyLogger::class, [$config['name']]);

		// Tracy adapter
		$builder->addDefinition($this->prefix('adapter'))
			->setFactory(MonologAdapter::class, [
				'monolog' => $this->prefix('@logger'),
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				'email' => Debugger::$email,
				'accessPriority' => $config['accessPriority'],
			])
			->addTag('logger');

		// The renderer has to be separate, to solve circural service dependencies
		$builder->addDefinition($this->prefix('blueScreenRenderer'))
			->setFactory(BlueScreenRenderer::class, [
				'directory' => $config['logDir'],
			])
			->setAutowired(FALSE)
			->addTag('logger');

		if ($config['hookToTracy'] === TRUE && $builder->hasDefinition('tracy.logger')) {
			// TracyExtension initializes the logger from DIC, if definition is changed
			$builder->removeDefinition($existing = 'tracy.logger');
			$builder->addAlias($existing, $this->prefix('adapter'));
		}

		$this->loadHandlers($config);
		$this->loadProcessors($config);
		$this->setConfig($config);
	}

	protected function loadHandlers(array $config): void // @phpstan-ignore-line
	{
		$builder = $this->getContainerBuilder();

		if (!empty($config['handlers'])) {
			foreach ($config['handlers'] as $handlerName => $implementation) {

				$builder->addDefinition($this->prefix('handler.' . $handlerName))
					->setFactory($implementation)
					->setAutowired(FALSE)
					->addTag(self::TAG_HANDLER)
					->addTag(self::TAG_PRIORITY, is_numeric($handlerName) ? $handlerName : 0);
			}
		}

	}

	protected function loadProcessors(array $config): void // @phpstan-ignore-line
	{
		$builder = $this->getContainerBuilder();

		if ($config['usePriorityProcessor'] === TRUE) {
			// change channel name to priority if available
			$builder->addDefinition($this->prefix('processor.priorityProcessor'))
				->setFactory(PriorityProcessor::class)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 20);
		}

		$builder->addDefinition($this->prefix('processor.tracyException'))
			->setFactory(TracyExceptionProcessor::class, [
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
			])
			->addTag(self::TAG_PROCESSOR)
			->addTag(self::TAG_PRIORITY, 100);

		if ($config['tracyBaseUrl'] !== NULL) {
			$builder->addDefinition($this->prefix('processor.tracyBaseUrl'))
				->setFactory(TracyUrlProcessor::class, [
					'baseUrl' => $config['tracyBaseUrl'],
					'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				])
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 10);
		}

		if (!empty($config['processors'])) {
			foreach ($config['processors'] as $processorName => $implementation) {

				$this->compiler->loadDefinitionsFromConfig([
					$serviceName = $this->prefix('processor.' . $processorName) => $implementation,
				]);

				$builder->getDefinition($serviceName)
					->addTag(self::TAG_PROCESSOR)
					->addTag(self::TAG_PRIORITY, is_numeric($processorName) ? $processorName : 0);
			}
		}

	}

	public function beforeCompile(): void
	{
		$config = $this->getConfig();

		$loggers = [];
		if (is_array($config)) {
			$loggers = ($config['loggers'] ?? []);
		}
		if (isset($loggers)) {
			if (isset($loggers['global'])) {
				$builder = $this->getContainerBuilder();
				$globalConfig = $loggers['global'];

				if (isset($globalConfig['handlers'])) {
					foreach (array_reverse($globalConfig['handlers']) as $handlerName) {
						foreach ($builder->findByType(\Kdyby\Monolog\Logger::class) as $globalLogger) {
							$globalLogger->addSetup('?->pushHandler(?)', [ // @phpstan-ignore-line
								'@self',
								$builder->getDefinition($this->prefix('handler.' . $handlerName)),
							]);
						}
					}
				}

				if (isset($globalConfig['processors'])) {
					foreach (array_reverse($globalConfig['processors']) as $processorName) {
						foreach ($builder->findByType(\Kdyby\Monolog\Logger::class) as $globalLogger) {
							$globalLogger->addSetup('?->pushProcessor(?)', [ // @phpstan-ignore-line
								'@self',
								$builder->getDefinition($this->prefix('processor.' . $processorName)),
							]);
						}
					}
				}
			}
		}

		$builder = $this->getContainerBuilder();
		/** @var \Nette\DI\ServiceDefinition $logger */
		$logger = $builder->getDefinition($this->prefix('logger'));

		foreach ($handlers = $this->findByTagSorted(self::TAG_HANDLER) as $serviceName => $meta) {
			$logger->addSetup('pushHandler', ['@' . $serviceName]); // @phpstan-ignore-line
		}

		foreach ($this->findByTagSorted(self::TAG_PROCESSOR) as $serviceName => $meta) {
			$logger->addSetup('pushProcessor', ['@' . $serviceName]); // @phpstan-ignore-line
		}

		if (empty($handlers) && !array_key_exists('registerFallback', $this->config)) {
			$this->config['registerFallback'] = TRUE;
		}

		if (array_key_exists('registerFallback', $this->config) && !empty($this->config['registerFallback'])) {
			$logger->addSetup('pushHandler', [ // @phpstan-ignore-line
				new Nette\DI\Definitions\Statement(FallbackNetteHandler::class, [
					'appName' => $this->config['name'],
					'logDir' => $this->config['logDir'],
				]),
			]);
		}

		/** @var \Nette\DI\ServiceDefinition $service */
		foreach ($builder->findByType(LoggerAwareInterface::class) as $service) {
			$service->addSetup('setLogger', ['@' . $this->prefix('logger')]); // @phpstan-ignore-line
		}
	}

	protected function findByTagSorted(?string $tag): array // @phpstan-ignore-line
	{
		$builder = $this->getContainerBuilder();

		$services = $builder->findByTag($tag);
		uksort($services, function ($nameA, $nameB) use ($builder) {
			$pa = $builder->getDefinition($nameA)->getTag(self::TAG_PRIORITY) ?: 0;
			$pb = $builder->getDefinition($nameB)->getTag(self::TAG_PRIORITY) ?: 0;
			return $pa > $pb ? 1 : ($pa < $pb ? -1 : 0);
		});

		return $services;
	}

	public function afterCompile(ClassTypeGenerator $class): void
	{
		$initialize = $class->getMethod('initialize');

		if (Debugger::$logDirectory === NULL && array_key_exists('logDir', $this->config)) {
			$initialize->addBody('?::$logDirectory = ?;', [new PhpLiteral(Debugger::class), $this->config['logDir']]);
		}
	}

	public static function register(Configurator $configurator): void
	{
		$configurator->onCompile[] = function ($config, Compiler $compiler) {
			$compiler->addExtension('monolog', new MonologExtension());
		};
	}

	/**
	 * @return string
	 */
	private static function resolveLogDir(array $parameters): string // @phpstan-ignore-line
	{
		if (isset($parameters['logDir'])) {
			return DIHelpers::expand('%logDir%', $parameters);
		}

		if (Debugger::$logDirectory !== NULL) {
			return Debugger::$logDirectory;
		}

		return DIHelpers::expand('%appDir%/../log', $parameters);
	}

	/**
	 * @param string $logDir
	 */
	private static function createDirectory($logDir): void
	{
		if (!@mkdir($logDir, 0777, TRUE) && !is_dir($logDir)) {
			throw new \RuntimeException(sprintf('Log dir %s cannot be created', $logDir));
		}
	}

}
