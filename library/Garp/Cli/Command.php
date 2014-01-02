<?php
/**
 * Garp_Cli_Command
 * Blueprint for command line commands (usually triggered 
 * from /garp/scripts/garp.php).
 * @author Harmen Janssen | grrr.nl
 * @modifiedby $LastChangedBy: $
 * @version $Revision: $
 * @package Garp
 * @subpackage Db
 * @lastmodified $Date: $
 */
abstract class Garp_Cli_Command {
	/**
	 * Central start method
	 * By default expects the first parameter (index 1 in $args) to be the requested method.
	 * @param Array $args Various options. Must contain at least a method name as the first parameter.
	 * @return Void
	 */
	public function main(array $args = array()) {
		$reflect = new ReflectionClass($this);
		$publicMethods = $reflect->getMethods(ReflectionMethod::IS_PUBLIC);
		$publicMethods = array_map(function($m) {
			return $m->name;
		}, $publicMethods);
		$publicMethods = array_filter($publicMethods, function($m) {
			return $m != 'main';
		});

		if (!array_key_exists(1, $args)) {
			Garp_Cli::errorOut("No method selected. Available methods: \n ".implode("\n ", $publicMethods));
			return;
		}

		$methodName = $args[1];
		if (in_array($methodName, $publicMethods)) {
			unset($args[1]);
			$args = $this->_remapArguments($args);
			call_user_func_array(array($this, $methodName), array($args));
		} else {
			Garp_Cli::errorOut('Unknown command \''.$methodName.'\'');
		}
	}


	/**
	 * Remap the numeric keys of a given arguments array, so they make sense in a different
	 * context.
	 * For example, this command:
	 * $ garp/scripts/garp Db replace monkeys hippos
	 * would result in the following arguments array:
	 * [0] => Db
	 * [1] => replace
	 * [2] => monkeys
	 * [3] => hippos
	 * 
	 * When this abstract class passes along the call to a specific command, in this case
	 * Garp_Cli_Command_Db::replace(), it's better to start the array at index 0 being "monkeys".
	 *
	 * @param Array $args
	 * @return Array
	 */
	protected function _remapArguments(array $args = array()) {
		$out = array();
		$i = 0;
		foreach ($args as $key => $value) {
			if (is_numeric($key)) {
				$out[$i++] = $value;
			} else {
				$out[$key] = $value;
			}
		}
		return $out;
	}
}
