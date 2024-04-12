<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Monolog;

use Kdyby\Monolog\Logger as KdybyLogger;
use Monolog\DateTimeImmutable;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger as MonologLogger;

class CustomChannel extends \Kdyby\Monolog\Logger
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \Kdyby\Monolog\Logger
	 */
	private $parentLogger;

	public function __construct($name, KdybyLogger $parentLogger)
	{
		parent::__construct($name, [], []);
		$this->parentLogger = $parentLogger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function pushHandler(HandlerInterface $handler): MonologLogger
	{
		return $this->parentLogger->pushHandler($handler);
	}

	/**
	 * {@inheritdoc}
	 */
	public function popHandler(): HandlerInterface
	{
		return $this->parentLogger->popHandler();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHandlers(): array
	{
		return $this->parentLogger->getHandlers();
	}

	/**
	 * {@inheritdoc}
	 */
	public function pushProcessor(callable $callback): MonologLogger
	{
		return $this->parentLogger->pushProcessor($callback);
	}

	/**
	 * {@inheritdoc}
	 */
	public function popProcessor(): callable
	{
		return $this->parentLogger->popProcessor();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getProcessors(): array
	{
		return $this->parentLogger->getProcessors();
	}

	/**
	 * {@inheritdoc}
	 */
    public function addRecord(int $level, string $message, array $context = [], DateTimeImmutable $datetime = null): bool
	{
		return $this->parentLogger->addRecord($level, $message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the DEBUG level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function addDebug(?string $message, array $context = []): void
	{
		$this->parentLogger->debug($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the INFO level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function addInfo(?string $message, array $context = []): void
	{
		$this->parentLogger->info($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the NOTICE level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function addNotice(?string $message, array $context = []): void
	{
		$this->parentLogger->notice($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the WARNING level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function addWarning(?string $message, array $context = []): void
	{
		$this->parentLogger->warning($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the ERROR level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function addError(?string $message, array $context = []): void
	{
		$this->parentLogger->error($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the CRITICAL level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function addCritical(?string $message, array $context = []): void
	{
		$this->parentLogger->critical($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the ALERT level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function addAlert(?string $message, array $context = []): void
	{
		$this->parentLogger->alert($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the EMERGENCY level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function addEmergency(?string $message, array $context = []): void
	{
		$this->parentLogger->emergency($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function isHandling(int $level): bool
	{
		return $this->parentLogger->isHandling($level);
	}

	/**
	 * {@inheritdoc}
	 */
	public function log($level, $message, array $context = []): void
	{
		$this->parentLogger->log($level, $message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function debug($message, array $context = []): void
	{
		$this->parentLogger->debug($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function info($message, array $context = []): void
	{
		$this->parentLogger->info($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function notice($message, array $context = []): void
	{
		$this->parentLogger->notice($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the WARNING level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function warn(?string $message, array $context = []): void
	{
		$this->parentLogger->warning($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function warning($message, array $context = []): void
	{
		$this->parentLogger->warning($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the ERROR level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function err(?string $message, array $context = []): void
	{
		$this->parentLogger->error($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function error($message, array $context = []): void
	{
		$this->parentLogger->error($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the CRITICAL level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function crit(?string $message, array $context = []): void
	{
		$this->parentLogger->critical($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function critical($message, array $context = []): void
	{
		$this->parentLogger->critical($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function alert($message, array $context = []): void
	{
		$this->parentLogger->alert($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * Adds a log record at the EMERGENCY level.
	 *
	 * This method allows for compatibility with common interfaces.
	 *
	 * @param string $message The log message
	 * @param array<string>  $context The log context
	 */
	public function emerg(?string $message, array $context = []): void
	{
		$this->parentLogger->emergency($message, array_merge(['channel' => $this->name], $context));
	}

	/**
	 * {@inheritdoc}
	 */
	public function emergency($message, array $context = []): void
	{
		$this->parentLogger->emergency($message, array_merge(['channel' => $this->name], $context));
	}

}
