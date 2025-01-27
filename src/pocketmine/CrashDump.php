<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;
use pocketmine\plugin\PluginManager;
use pocketmine\utils\Utils;
use pocketmine\utils\VersionString;
use raklib\RakLib;
use function base64_encode;
use function date;
use function error_get_last;
use function fclose;
use function file;
use function file_exists;
use function fopen;
use function fwrite;
use function implode;
use function is_dir;
use function is_resource;
use function json_encode;
use function json_last_error_msg;
use function max;
use function mkdir;
use function php_uname;
use function phpversion;
use function str_split;
use function strpos;
use function substr;
use function time;
use function zend_version;
use function zlib_encode;
use const E_COMPILE_ERROR;
use const E_COMPILE_WARNING;
use const E_CORE_ERROR;
use const E_CORE_WARNING;
use const E_DEPRECATED;
use const E_ERROR;
use const E_NOTICE;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_STRICT;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;
use const FILE_IGNORE_NEW_LINES;
use const JSON_UNESCAPED_SLASHES;
use const PHP_EOL;
use const PHP_OS;

class CrashDump{

	/**
	 * Crashdump data format version, used by the crash archive to decide how to decode the crashdump
	 * This should be incremented when backwards incompatible changes are introduced, such as fields being removed or
	 * having their content changed, version format changing, etc.
	 * It is not necessary to increase this when adding new fields.
	 */
	private const FORMAT_VERSION = 2;

	private const PLUGIN_INVOLVEMENT_NONE = "none";
	private const PLUGIN_INVOLVEMENT_DIRECT = "direct";
	private const PLUGIN_INVOLVEMENT_INDIRECT = "indirect";

	/** @var Server */
	private $server;
	private $fp;
	private $time;
	private $data = [];
	/** @var string */
	private $encodedData = "";
	/** @var string */
	private $path;

	public function __construct(Server $server){
		$this->time = time();
		$this->server = $server;
		if(!is_dir($this->server->getDataPath() . "crashdumps")){
			mkdir($this->server->getDataPath() . "crashdumps");
		}
		$this->path = $this->server->getDataPath() . "crashdumps/" . date("D_M_j-H.i.s-T_Y", $this->time) . ".log";
		$this->fp = @fopen($this->path, "wb");
		if(!is_resource($this->fp)){
			throw new \RuntimeException("Could not create Crash Dump");
		}
		$this->data["format_version"] = self::FORMAT_VERSION;
		$this->data["time"] = $this->time;
		$this->addLine($this->server->getName() . " Crash Dump " . date("D M j H:i:s T Y", $this->time));
		$this->addLine();
		$this->baseCrash();
		$this->generalData();
		$this->pluginsData();

		$this->encodeData();

		fclose($this->fp);
	}

	public function getPath() : string{
		return $this->path;
	}

	public function getEncodedData(){
		return $this->encodedData;
	}

	public function getData() : array{
		return $this->data;
	}

	private function encodeData(){
		$this->addLine();
		$this->addLine("----------------------REPORT THE DATA BELOW THIS LINE-----------------------");
		$this->addLine();
		$this->addLine("===BEGIN CRASH DUMP===");
		$json = json_encode($this->data, JSON_UNESCAPED_SLASHES);
		if($json === false){
			throw new \RuntimeException("Failed to encode crashdump JSON: " . json_last_error_msg());
		}
		$this->encodedData = zlib_encode($json, ZLIB_ENCODING_DEFLATE, 9);
		foreach(str_split(base64_encode($this->encodedData), 76) as $line){
			$this->addLine($line);
		}
		$this->addLine("===END CRASH DUMP===");
	}

	private function pluginsData(){
		if($this->server->getPluginManager() instanceof PluginManager){
			$this->addLine();
			$this->addLine("Loaded plugins:");
			$this->data["plugins"] = [];
			foreach($this->server->getPluginManager()->getPlugins() as $p){
				$d = $p->getDescription();
				$this->data["plugins"][$d->getName()] = [
					"name" => $d->getName(),
					"version" => $d->getVersion(),
					"authors" => $d->getAuthors(),
					"api" => $d->getCompatibleApis(),
					"enabled" => $p->isEnabled(),
					"depends" => $d->getDepend(),
					"softDepends" => $d->getSoftDepend(),
					"main" => $d->getMain(),
					"load" => $d->getOrder() === PluginLoadOrder::POSTWORLD ? "POSTWORLD" : "STARTUP",
					"website" => $d->getWebsite()
				];
				$this->addLine($d->getName() . " " . $d->getVersion() . " by " . implode(", ", $d->getAuthors()) . " for API(s) " . implode(", ", $d->getCompatibleApis()));
			}
		}
	}

	private function baseCrash(){
		global $lastExceptionError, $lastError;

		if(isset($lastExceptionError)){
			$error = $lastExceptionError;
		}else{
			$error = (array) error_get_last();
			$error["trace"] = Utils::currentTrace(3); //Skipping CrashDump->baseCrash, CrashDump->construct, Server->crashDump
			$errorConversion = [
				E_ERROR => "E_ERROR",
				E_WARNING => "E_WARNING",
				E_PARSE => "E_PARSE",
				E_NOTICE => "E_NOTICE",
				E_CORE_ERROR => "E_CORE_ERROR",
				E_CORE_WARNING => "E_CORE_WARNING",
				E_COMPILE_ERROR => "E_COMPILE_ERROR",
				E_COMPILE_WARNING => "E_COMPILE_WARNING",
				E_USER_ERROR => "E_USER_ERROR",
				E_USER_WARNING => "E_USER_WARNING",
				E_USER_NOTICE => "E_USER_NOTICE",
				E_STRICT => "E_STRICT",
				E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
				E_DEPRECATED => "E_DEPRECATED",
				E_USER_DEPRECATED => "E_USER_DEPRECATED"
			];
			$error["fullFile"] = $error["file"];
			$error["file"] = Utils::cleanPath($error["file"]);
			$error["type"] = $errorConversion[$error["type"]] ?? $error["type"];
			if(($pos = strpos($error["message"], "\n")) !== false){
				$error["message"] = substr($error["message"], 0, $pos);
			}
		}

		if(isset($lastError)){
			if(isset($lastError["trace"])){
				$lastError["trace"] = Utils::printableTrace($lastError["trace"]);
			}
			$this->data["lastError"] = $lastError;
		}

		$this->data["error"] = $error;
		unset($this->data["error"]["fullFile"]);
		unset($this->data["error"]["trace"]);
		$this->addLine("Error: " . $error["message"]);
		$this->addLine("File: " . $error["file"]);
		$this->addLine("Line: " . $error["line"]);
		$this->addLine("Type: " . $error["type"]);

		$this->data["plugin_involvement"] = self::PLUGIN_INVOLVEMENT_NONE;
		if(!$this->determinePluginFromFile($error["fullFile"], true)){ //fatal errors won't leave any stack trace
			foreach($error["trace"] as $frame){
				if(!isset($frame["file"])){
					continue; //PHP core
				}
				if($this->determinePluginFromFile($frame["file"], false)){
					break;
				}
			}
		}

		$this->addLine();
		$this->addLine("Code:");
		$this->data["code"] = [];

		if($this->server->getProperty("auto-report.send-code", true) !== false and file_exists($error["fullFile"])){
			$file = @file($error["fullFile"], FILE_IGNORE_NEW_LINES);
			for($l = max(0, $error["line"] - 10); $l < $error["line"] + 10 and isset($file[$l]); ++$l){
				$this->addLine("[" . ($l + 1) . "] " . $file[$l]);
				$this->data["code"][$l + 1] = $file[$l];
			}
		}

		$this->addLine();
		$this->addLine("Backtrace:");
		foreach(($this->data["trace"] = Utils::printableTrace($error["trace"])) as $line){
			$this->addLine($line);
		}
		$this->addLine();
	}

	private function determinePluginFromFile(string $filePath, bool $crashFrame) : bool{
		$frameCleanPath = Utils::cleanPath($filePath); //this will be empty in phar stub
		if(strpos($frameCleanPath, "plugins") === 0 and file_exists($filePath)){
			$this->addLine();
			if($crashFrame){
				$this->addLine("THIS CRASH WAS CAUSED BY A PLUGIN");
				$this->data["plugin_involvement"] = self::PLUGIN_INVOLVEMENT_DIRECT;
			}else{
				$this->addLine("A PLUGIN WAS INVOLVED IN THIS CRASH");
				$this->data["plugin_involvement"] = self::PLUGIN_INVOLVEMENT_INDIRECT;
			}

			$reflection = new \ReflectionClass(PluginBase::class);
			$file = $reflection->getProperty("file");
			$file->setAccessible(true);
			foreach($this->server->getPluginManager()->getPlugins() as $plugin){
				$filePath = Utils::cleanPath($file->getValue($plugin));
				if(strpos($frameCleanPath, $filePath) === 0){
					$this->data["plugin"] = $plugin->getName();
					$this->addLine("BAD PLUGIN: " . $plugin->getDescription()->getFullName());
					break;
				}
			}
			return true;
		}
		return false;
	}

	private function generalData(){
		$version = new VersionString(\pocketmine\BASE_VERSION, \pocketmine\IS_DEVELOPMENT_BUILD, \pocketmine\BUILD_NUMBER);
		$this->data["general"] = [];
		$this->data["general"]["name"] = $this->server->getName();
		$this->data["general"]["base_version"] = \pocketmine\BASE_VERSION;
		$this->data["general"]["build"] = \pocketmine\BUILD_NUMBER;
		$this->data["general"]["is_dev"] = \pocketmine\IS_DEVELOPMENT_BUILD;
		$this->data["general"]["protocol"] = ProtocolInfo::CURRENT_PROTOCOL;
		$this->data["general"]["git"] = \pocketmine\GIT_COMMIT;
		$this->data["general"]["raklib"] = RakLib::VERSION;
		$this->data["general"]["uname"] = php_uname("a");
		$this->data["general"]["php"] = phpversion();
		$this->data["general"]["zend"] = zend_version();
		$this->data["general"]["php_os"] = PHP_OS;
		$this->data["general"]["os"] = Utils::getOS();
		$this->addLine($this->server->getName() . " version: " . $version->getFullVersion(true) . " [Protocol " . ProtocolInfo::CURRENT_PROTOCOL . "]");
		$this->addLine("Git commit: " . GIT_COMMIT);
		$this->addLine("uname -a: " . php_uname("a"));
		$this->addLine("PHP Version: " . phpversion());
		$this->addLine("Zend version: " . zend_version());
		$this->addLine("OS : " . PHP_OS . ", " . Utils::getOS());
	}

	public function addLine($line = ""){
		fwrite($this->fp, $line . PHP_EOL);
	}

	public function add($str){
		fwrite($this->fp, $str);
	}
}
