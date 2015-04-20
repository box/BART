<?php
namespace Bart\Shell;
use Bart\Diesel;
use Bart\Log4PHP;

/**
 * Encapsulates a shell command
 */
class Command
{
	/** @var \Logger */
	private $logger;
	private $safeCommandStr;

	/**
	 * @param string $commandFormat Command string to run. Use printf like placeholders for argument
	 * WARNING Only STRINGS supported. This is due to escapeshellcommand converting everything to a string.
	 * @param string $args, ... [Optional] All arguments
	 * @warning Do NOT single-quote any arg placeholders in $commandFormat. This will be done by
	 * the class itself and placing single-quotes in the command string will negate this work.
	 */
	public function __construct($commandFormat)
	{
		$this->logger = Log4PHP::getLogger(__CLASS__);

		$safeCommandFormat = escapeshellcmd($commandFormat);

		$args = func_get_args();
		array_shift($args); // bump off the format string from the front

		$this->safeCommandStr = self::makeSafeString($safeCommandFormat, $args);
		$this->logger->debug('Set safe command string ' . $this->safeCommandStr);
	}

	public function __toString()
	{
		return "{$this->safeCommandStr}";
	}

	/**
	 * Helper for creating Commands when you've got a layer of indirection and can't use {@see Shell::command()}
	 * All parameters and results same as {@see self::__construct()}
	 * @param string $commandFormat The command itself
	 * @param array $args All arguments to command
	 * @return Command
	 */
	public static function fromFmtAndArgs($commandFormat, $args)
	{
		array_unshift($args, $commandFormat);
		array_unshift($args, __CLASS__);

		// Create instance of self using Diesel so that we can use this (static) method in tests
		return call_user_func_array(['\Bart\Diesel', 'create'], $args);
	}

	/**
	 * Safely format a string for use on command line.
	 * You should aim to always use {@see self} for building execution strings,
	 * but sometimes it's not possible
	 *
	 * @param string $format The sprintf-like formatted string (Note all placeholders must be strings)
	 * @param string[] $args The arguments to the formatted string
	 * @return string The put together string
	 */
	public static function makeSafeString($format, array $args)
	{
		$safeArgs = array($format);
		foreach ($args as $arg) {
			$safeArgs[] = escapeshellarg($arg);
		}

		return call_user_func_array('sprintf', $safeArgs);
	}

	/**
	 * Execute command and wrap in @see CommandResult
	 * This is preferable if you don't want exceptions raised when commands fail
	 * @return CommandResult
	 */
	public function getResult()
	{
		$output = array();
		$returnVar = 0;

		$this->logger->trace('Executing ' . $this->safeCommandStr);
		exec($this->safeCommandStr, $output, $returnVar);

		return new CommandResult($this, $output, $returnVar);
	}

	/**
	 * @param bool $returnOutputAsString [Optional] By default, command output is returned as an array
	 * @return array|string Output of command
	 * @throws CommandException if command fails
	 */
	public function run($returnOutputAsString = false)
	{
		$output = array();
		$returnVar = 0;

		$this->logger->trace('Executing ' . $this->safeCommandStr);
		exec($this->safeCommandStr, $output, $returnVar);

		if ($returnVar !== 0) {
			$this->logger->error('Non-zero exit status ' . $returnVar);

			throw new CommandException("Got bad status $returnVar for {$this->safeCommandStr}. Output: "
					. implode("\n", $output));
		}

		return $returnOutputAsString ?
				implode("\n", $output) :
				$output;
	}
}

