<?php

/**
 * Test: Kdyby\Monolog\Extension.
 *
 * @testCase
 */

namespace KdybyTests\Monolog;

use Kdyby\Monolog\DI\MonologExtension;
use Kdyby\Monolog\Handler\FallbackNetteHandler;
use Kdyby\Monolog\Logger as MonologLogger;
use Kdyby\Monolog\Processor\PriorityProcessor;
use Kdyby\Monolog\Processor\TracyExceptionProcessor;
use Kdyby\Monolog\Processor\TracyUrlProcessor;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\NewRelicHandler;
use Monolog\Processor\GitProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\TagProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Nette\Configurator;
use Tester\Assert;
use Tracy\Debugger;

require_once __DIR__ . '/../bootstrap.php';

class ExtensionTest extends \Tester\TestCase
{

	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer($configName = NULL)
	{
		$config = new Configurator();
		$config->setTempDirectory(TEMP_DIR);
		MonologExtension::register($config);
		$config->addConfig(__DIR__ . '/../nette-reset.neon');

		if ($configName !== NULL) {
			$config->addConfig(__DIR__ . '/config/' . $configName . '.neon');
		}

		return $config->createContainer();
	}

	public function testServices()
	{
		$dic = $this->createContainer();
		Assert::true($dic->getService('monolog.logger') instanceof MonologLogger);
	}

	public function testFunctional()
	{
		foreach (array_merge(glob(TEMP_DIR . '/*.log'), glob(TEMP_DIR . '/*.html')) as $logFile) {
			unlink($logFile);
		}

		Debugger::$logDirectory = TEMP_DIR;

		$dic = $this->createContainer();
		/** @var \Kdyby\Monolog\Logger $logger */
		$logger = $dic->getByType(MonologLogger::class);

		Debugger::log('tracy message 1');
		Debugger::log('tracy message 2', 'error');

		Debugger::log(new \Exception('tracy exception message 1'), 'error');
		Debugger::log(new \Exception('tracy exception message 2'));

		$logger->addInfo('logger message 1');
		$logger->addInfo('logger message 2', ['channel' => 'custom']);

		$logger->addError('logger message 3');
		$logger->addError('logger message 4', ['channel' => 'custom']);

		$logger->addWarning('exception message 1', ['exception' => new \Exception('exception message 1')]);

		$logger->addDebug('logger message 5');
		$logger->addDebug('logger message 6', ['channel' => 'custom']);

		$logger->addNotice('logger message 7');
		$logger->addNotice('logger message 8', ['channel' => 'custom']);

		$logger->addCritical('logger message 9');
		$logger->addCritical('logger message 10', ['channel' => 'custom']);

		$logger->addAlert('logger message 11');
		$logger->addAlert('logger message 12', ['channel' => 'custom']);

		$logger->addEmergency('logger message 13');
		$logger->addEmergency('logger message 14', ['channel' => 'custom']);

		$logger->warn('exception message 2', ['exception' => new \Exception('exception message 2')]);
		$logger->warn('logger message 16', ['channel' => 'custom']);

		$logger->err('logger message 17');
		$logger->err('logger message 18', ['channel' => 'custom']);

		$logger->crit('logger message 19');
		$logger->crit('logger message 20', ['channel' => 'custom']);

		$logger->emerg('logger message 21');
		$logger->emerg('logger message 22', ['channel' => 'custom']);

        $logContent = file_get_contents(TEMP_DIR . '/info.log');
        Assert::contains('tracy message 1', $logContent);
        Assert::contains('Exception: tracy exception message 2', $logContent);
        Assert::contains('logger message 1', $logContent);

		Assert::match(
			'[%a%] exception message 1 {"exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} {%a%}' . "\n" .
			'[%a%] exception message 2 {"exception":"%a%","tracy_filename":"exception-%a%.html","tracy_created":true} {%a%}',
			file_get_contents(TEMP_DIR . '/warning.log')
		);


        $logContent = file_get_contents(TEMP_DIR . '/error.log');
        Assert::contains('tracy message 2', $logContent);
        Assert::contains('Exception: tracy exception message 1', $logContent);
        Assert::contains('logger message 3', $logContent);
        Assert::contains('logger message 17', $logContent);

		Assert::match(
			'[%a%] INFO: logger message 2 [] {%a%}' . "\n" .
			'[%a%] ERROR: logger message 4 [] {%a%}' . "\n" .
			'[%a%] DEBUG: logger message 6 [] {%a%}' . "\n" .
			'[%a%] NOTICE: logger message 8 [] {%a%}' . "\n" .
			'[%a%] CRITICAL: logger message 10 [] {%a%}' . "\n" .
			'[%a%] ALERT: logger message 12 [] {%a%}' . "\n" .
			'[%a%] EMERGENCY: logger message 14 [] {%a%}' . "\n" .
			'[%a%] WARNING: logger message 16 [] {%a%}' . "\n" .
			'[%a%] ERROR: logger message 18 [] {%a%}' . "\n" .
			'[%a%] CRITICAL: logger message 20 [] {%a%}' . "\n" .
			'[%a%] EMERGENCY: logger message 22 [] {%a%}' . "\n",
			file_get_contents(TEMP_DIR . '/custom.log')
		);

		Assert::match(
			'[%a%] logger message 5 [] {%a%}' . "\n",
			file_get_contents(TEMP_DIR . '/debug.log')
		);

		Assert::match(
			'[%a%] logger message 7 [] {%a%}' . "\n",
			file_get_contents(TEMP_DIR . '/notice.log')
		);

		Assert::match(
			'[%a%] logger message 9 [] {%a%}' . "\n" .
			'[%a%] logger message 19 [] {%a%}',
			file_get_contents(TEMP_DIR . '/critical.log')
		);

		Assert::match(
			'[%a%] logger message 11 [] {%a%}' . "\n",
			file_get_contents(TEMP_DIR . '/alert.log')
		);

		Assert::match(
			'[%a%] logger message 13 [] {%a%}' . "\n" .
			'[%a%] logger message 21 [] {%a%}' . "\n",
			file_get_contents(TEMP_DIR . '/emergency.log')
		);

		Assert::count(2, glob(TEMP_DIR . '/exception-*.html'));

		// TEST FOR CUSTOM CHANNEL

		$channel = $logger->channel('test');
		Assert::type('Kdyby\Monolog\CustomChannel', $channel);
		Assert::match('test', $channel->getName());

		$channel->addInfo('custom channel message 1');
		$channel->addError('custom channel message 2');
		$channel->addWarning('custom channel message 3');
		$channel->addDebug('custom channel message 4');
		$channel->addNotice('custom channel message 5');
		$channel->addCritical('custom channel message 6');
		$channel->addAlert('custom channel message 7');
		$channel->addEmergency('custom channel message 8');

		$channel->debug('custom channel message 9');
		$channel->info('custom channel message 10');
		$channel->notice('custom channel message 11');
		$channel->warn('custom channel message 12');
		$channel->warning('custom channel message 13');
		$channel->err('custom channel message 14');
		$channel->error('custom channel message 15');
		$channel->crit('custom channel message 16');
		$channel->critical('custom channel message 17');
		$channel->alert('custom channel message 18');
		$channel->emerg('custom channel message 19');
		$channel->emergency('custom channel message 20');

		Assert::match(
			'[%a%] INFO: custom channel message 1 [] {%a%}' . "\n" .
			'[%a%] ERROR: custom channel message 2 [] {%a%}' . "\n" .
			'[%a%] WARNING: custom channel message 3 [] {%a%}' . "\n" .
			'[%a%] DEBUG: custom channel message 4 [] {%a%}' . "\n" .
			'[%a%] NOTICE: custom channel message 5 [] {%a%}' . "\n" .
			'[%a%] CRITICAL: custom channel message 6 [] {%a%}' . "\n" .
			'[%a%] ALERT: custom channel message 7 [] {%a%}' . "\n" .
			'[%a%] EMERGENCY: custom channel message 8 [] {%a%}' . "\n" .
			'[%a%] DEBUG: custom channel message 9 [] {%a%}' . "\n" .
			'[%a%] INFO: custom channel message 10 [] {%a%}' . "\n" .
			'[%a%] NOTICE: custom channel message 11 [] {%a%}' . "\n" .
			'[%a%] WARNING: custom channel message 12 [] {%a%}' . "\n" .
			'[%a%] WARNING: custom channel message 13 [] {%a%}' . "\n" .
			'[%a%] ERROR: custom channel message 14 [] {%a%}' . "\n" .
			'[%a%] ERROR: custom channel message 15 [] {%a%}' . "\n" .
			'[%a%] CRITICAL: custom channel message 16 [] {%a%}' . "\n" .
			'[%a%] CRITICAL: custom channel message 17 [] {%a%}' . "\n" .
			'[%a%] ALERT: custom channel message 18 [] {%a%}' . "\n" .
			'[%a%] EMERGENCY: custom channel message 19 [] {%a%}' . "\n" .
			'[%a%] EMERGENCY: custom channel message 20 [] {%a%}' . "\n",
			file_get_contents(TEMP_DIR . '/test.log')
		);
	}

	public function testHandlersSorting()
	{
		$dic = $this->createContainer('handlers');
		$logger = $dic->getByType(MonologLogger::class);
		$handlers = $logger->getHandlers();
		Assert::count(5, $handlers);
		Assert::type(FallbackNetteHandler::class, array_shift($handlers));
		Assert::type(BrowserConsoleHandler::class, array_shift($handlers));
		Assert::type(ChromePHPHandler::class, array_shift($handlers));
		Assert::type(NewRelicHandler::class, array_shift($handlers));
		Assert::type(ErrorLogHandler::class, array_shift($handlers));

	}

	public function testProcessorsSorting()
	{
		$dic = $this->createContainer('processors');
		$logger = $dic->getByType(MonologLogger::class);
		$processors = $logger->getProcessors();
		Assert::count(15, $processors);

		Assert::type(TracyExceptionProcessor::class, array_shift($processors));
		Assert::type(PriorityProcessor::class, array_shift($processors));
		Assert::type(TracyUrlProcessor::class, array_shift($processors));
		Assert::type(WebProcessor::class, array_shift($processors));
		Assert::type(UidProcessor::class, array_shift($processors));
		Assert::type(TagProcessor::class, array_shift($processors));
		Assert::type(PsrLogMessageProcessor::class, array_shift($processors));
		Assert::type(ProcessIdProcessor::class, array_shift($processors));
		Assert::type(MemoryUsageProcessor::class, array_shift($processors));
		Assert::type(MemoryPeakUsageProcessor::class, array_shift($processors));
		Assert::type(IntrospectionProcessor::class, array_shift($processors));
		Assert::type(GitProcessor::class, array_shift($processors));
	}

	public function testProcessorsSorting2()
	{
		$dic = $this->createContainer('processorsName');
		$logger = $dic->getByType(MonologLogger::class);
		$processors = $logger->getProcessors();
		Assert::count(12, $processors);

		Assert::type(TracyExceptionProcessor::class, array_shift($processors));
		Assert::type(PriorityProcessor::class, array_shift($processors));
		Assert::type(TracyUrlProcessor::class, array_shift($processors));
		Assert::type(UidProcessor::class, array_shift($processors));
		Assert::type(TagProcessor::class, array_shift($processors));
		Assert::type(PsrLogMessageProcessor::class, array_shift($processors));
		Assert::type(MemoryUsageProcessor::class, array_shift($processors));
		Assert::type(MemoryPeakUsageProcessor::class, array_shift($processors));
		Assert::type(IntrospectionProcessor::class, array_shift($processors));
		Assert::type(WebProcessor::class, array_shift($processors));
		Assert::type(ProcessIdProcessor::class, array_shift($processors));
		Assert::type(GitProcessor::class, array_shift($processors));
	}


}

(new ExtensionTest())->run();
