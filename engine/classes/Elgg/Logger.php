<?php

namespace Elgg;

use Elgg\Logger\BacktraceProcessor;
use Elgg\Logger\ElggLogFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Log\LogLevel;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Logger
 *
 * Use elgg()->logger
 */
class Logger extends \Monolog\Logger {

	const CHANNEL = 'ELGG';

	const OFF = false;

	/**
	 * Severity levels
	 * @var array
	 */
	protected static $elgg_levels = [
		0 => false,
		100 => LogLevel::DEBUG,
		200 => LogLevel::INFO,
		250 => LogLevel::NOTICE,
		300 => LogLevel::WARNING,
		400 => LogLevel::ERROR,
		500 => LogLevel::CRITICAL,
		550 => LogLevel::ALERT,
		600 => LogLevel::EMERGENCY,
	];

	/**
	 * A map of legacy string levels
	 * @var array
	 */
	protected static $legacy_levels = [
		'OFF' => false,
		'INFO' => LogLevel::INFO,
		'NOTICE' => LogLevel::NOTICE,
		'WARNING' => LogLevel::WARNING,
		'ERROR' => LogLevel::ERROR,
	];

	/**
	 * @var string The logging level
	 */
	protected $level;

	/**
	 * @var PluginHooksService
	 */
	protected $hooks;

	/**
	 * @var array
	 */
	private $disabled_stack;

	/**
	 * Build a new logger
	 *
	 * @param $output OutputInterface Console output
	 *
	 * @return static
	 */
	public static function factory(OutputInterface $output = null) {
		$logger = new static(self::CHANNEL);

		if ('cli' == PHP_SAPI) {
			$handler = new ConsoleHandler(
				$output ? : new ConsoleOutput(),
				true,
				Cli::$verbosityLevelMap
			);

			$formatter = new ConsoleFormatter();
			$formatter->allowInlineLineBreaks();
			$formatter->ignoreEmptyContextAndExtra();

			$handler->setFormatter($formatter);

			$handler->pushProcessor(new BacktraceProcessor(self::ERROR));
		} else {
			$handler = new ErrorLogHandler();

			$handler->pushProcessor(new WebProcessor());

			$formatter = new ElggLogFormatter();
			$formatter->allowInlineLineBreaks();
			$formatter->ignoreEmptyContextAndExtra();

			$handler->setFormatter($formatter);

			$handler->pushProcessor(new MemoryUsageProcessor());
			$handler->pushProcessor(new MemoryPeakUsageProcessor());
			$handler->pushProcessor(new ProcessIdProcessor());
			$handler->pushProcessor(new BacktraceProcessor(self::WARNING));
		}

		$handler->pushProcessor(new PsrLogMessageProcessor());

		$logger->pushHandler($handler);

		$logger->setLevel();

		return $logger;
	}

	/**
	 * Normalizes legacy string or numeric representation of the level to LogLevel strings
	 *
	 * @param mixed $level Level
	 *
	 * @return string|false
	 * @access private
	 * @internal
	 */
	protected function normalizeLevel($level = null) {
		if (!$level) {
			return false;
		}

		if (array_key_exists($level, self::$legacy_levels)) {
			$level = self::$legacy_levels[$level];
		}

		if (array_key_exists($level, self::$elgg_levels)) {
			$level = self::$elgg_levels[$level];
		}

		if (!in_array($level, self::$elgg_levels)) {
			$level = false;
		}

		return $level;
	}

	/**
	 * Set the logging level
	 *
	 * @param mixed $level Level
	 *
	 * @return void
	 * @access private
	 * @internal
	 */
	public function setLevel($level = null) {
		if (!isset($level)) {
			$php_error_level = error_reporting();

			$level = false;

			if (($php_error_level & E_NOTICE) == E_NOTICE) {
				$level = LogLevel::NOTICE;
			} else if (($php_error_level & E_WARNING) == E_WARNING) {
				$level = LogLevel::WARNING;
			} else if (($php_error_level & E_ERROR) == E_ERROR) {
				$level = LogLevel::ERROR;
			}
		}

		$this->level = $this->normalizeLevel($level);
	}

	/**
	 * Get the current logging level severity
	 *
	 * @param bool $severity If true, will return numeric representation of the logging level
	 *
	 * @return int|string|false
	 * @access private
	 * @internal
	 */
	public function getLevel($severity = true) {
		if ($severity) {
			return array_search($this->level, self::$elgg_levels);
		}

		return $this->level;
	}

	/**
	 * Check if a level is loggable under current logging level
	 *
	 * @param mixed $level Level name or severity code
	 * @return bool
	 */
	public function isLoggable($level) {
		$level = $this->normalizeLevel($level);

		$severity = array_search($level, self::$elgg_levels);
		if (!$this->getLevel() || $severity < $this->getLevel()) {
			return false;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function log($level, $message, array $context = []) {

		$level = $this->normalizeLevel($level);

		if ($this->disabled_stack) {
			// capture to top of stack
			end($this->disabled_stack);
			$key = key($this->disabled_stack);
			$this->disabled_stack[$key][] = [
				'message' => $message,
				'level' => $level,
			];
		}

		if (!$this->isLoggable($level)) {
			return false;
		}

		// when capturing, still use consistent return value
		if ($this->disabled_stack) {
			return true;
		}

		if ($this->hooks) {
			$levelString = strtoupper($level);

			$params = [
				'level' => $level,
				'msg' => $message,
				'context' => $context,
			];

			if (!$this->hooks->triggerDeprecated('debug', 'log', $params, true)) {
				return false;
			}
		}

		return parent::log($level, $message, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function emergency($message, array $context = []) {
		return $this->log(LogLevel::EMERGENCY, $message, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function alert($message, array $context = []) {
		return $this->log(LogLevel::ALERT, $message, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function critical($message, array $context = []) {
		return $this->log(LogLevel::CRITICAL, $message, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function error($message, array $context = []) {
		return $this->log(LogLevel::ERROR, $message, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function warning($message, array $context = []) {
		return $this->log(LogLevel::WARNING, $message, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function notice($message, array $context = []) {
		return $this->log(LogLevel::NOTICE, $message, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function info($message, array $context = []) {
		return $this->log(LogLevel::INFO, $message, $context);
	}

	/**
	 * {@inheritdoc}
	 */
	public function debug($message, array $context = []) {
		return $this->log(LogLevel::DEBUG, $message, $context);
	}

	/**
	 * Log message at the WARNING level
	 *
	 * @param string $message The message to log
	 * @param array  $context Context
	 *
	 * @return bool
	 * @deprecated 3.0 Use Logger::warning()
	 */
	public function warn($message, array $context = []) {
		return $this->warning($message, $context);
	}

	/**
	 * Dump data to log
	 *
	 * @param mixed $data The data to log
	 *
	 * @return bool
	 */
	public function dump($data) {
		return $this->log(LogLevel::ERROR, $data);
	}

	/**
	 * Temporarily disable logging and capture logs (before tests)
	 *
	 * Call disable() before your tests and enable() after. enable() will return a list of
	 * calls to log() (and helper methods) that were not acted upon.
	 *
	 * @note   This behaves like a stack. You must call enable() for each disable() call.
	 *
	 * @return void
	 * @see    enable()
	 * @access private
	 * @internal
	 */
	public function disable() {
		$this->disabled_stack[] = [];
	}

	/**
	 * Restore logging and get record of log calls (after tests)
	 *
	 * @return array
	 * @see    disable()
	 * @access private
	 * @internal
	 */
	public function enable() {
		return array_pop($this->disabled_stack);
	}

	/**
	 * Reset the hooks service for this instance (testing)
	 *
	 * @param PluginHooksService $hooks the plugin hooks service
	 *
	 * @return void
	 * @access private
	 * @internal
	 */
	public function setHooks(PluginHooksService $hooks) {
		$this->hooks = $hooks;
	}
}
