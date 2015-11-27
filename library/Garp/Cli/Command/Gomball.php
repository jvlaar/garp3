<?php
/**
 * Garp_Cli_Command_Gomball
 * Create a packaged version of the project, including database and source files.
 *
 * @author       Harmen Janssen | grrr.nl
 * @version      0.1.0
 * @package      Garp_Cli_Command
 */
class Garp_Cli_Command_Gomball extends Garp_Cli_Command {
	const PROMPT_OVERWRITE = 'Existing gomball found for version %s. Do you wish to overwrite?';
	const PROMPT_SOURCE_DATABASE_ENVIRONMENT = 'Take database from which environment? (production)';
	const DEFAULT_SOURCE_DATABASE_ENVIRONMENT = 'production';

	const ABORT_NO_OVERWRITE = 'Stopping gomball creation, existing gomball stays untouched.';
	const ABORT_CANT_MKDIR_GOMBALLS = 'Error: cannot create gomballs directory';
	const ABORT_CANT_MKDIR_TARGET_DIRECTORY = 'Error: cannot create target directory';
	const ABORT_CANT_COPY_SOURCEFILES = 'Error: cannot copy source files to target directory';
	const ABORT_CANT_WRITE_ZIP = 'Error: cannot create zip file';
	const ABORT_DATADUMP_FAILED = 'Error: datadump failed';

	public function make($args = array()) {
		$version = new Garp_Semver();
		Garp_Cli::lineOut('Creating gomball ' . $version, Garp_Cli::PURPLE);

		$fromEnv = Garp_Cli::prompt(self::PROMPT_SOURCE_DATABASE_ENVIRONMENT) ?:
			self::DEFAULT_SOURCE_DATABASE_ENVIRONMENT;

		$gomball = new Garp_Gomball($version, $fromEnv);

		if ($gomball->exists() &&
			!Garp_Cli::confirm(sprintf(self::PROMPT_OVERWRITE, $version))) {
			Garp_Cli::lineOut(self::ABORT_NO_OVERWRITE, Garp_Cli::YELLOW);
			exit(1);
		}

		if (!$this->_createGomballDirectory()) {
			Garp_Cli::errorOut(self::ABORT_CANT_MKDIR_GOMBALLS);
			exit(1);
		}

		try {
			$gomball->make();
		} catch (Garp_Gomball_Exception_CannotWriteTargetDirectory $e) {
			Garp_Cli::errorOut(self::ABORT_CANT_MKDIR_TARGET_DIRECTORY);
			exit(1);
		} catch (Garp_Gomball_Exception_CannotCopySourceFiles $e) {
			Garp_Cli::errorOut(self::ABORT_CANT_COPY_SOURCEFILES);
			exit(1);
		} catch (Garp_Gomball_Exception_CannotCreateZip $e) {
			Garp_Cli::errorOut(self::ABORT_CANT_WRITE_ZIP);
			exit(1);
		} catch (Garp_Gomball_Exception_DatadumpFailed $e) {
			Garp_Cli::errorOut(self::ABORT_DATADUMP_FAILED);
			exit(1);
		}

	}

	protected function _createGomballDirectory() {
		if (!file_exists($this->_getGomballDirectory())) {
			return mkdir($this->_getGomballDirectory());
		}
		return true;
	}

	protected function _getGomballDirectory() {
		return APPLICATION_PATH . '/../gomballs';
	}
}
