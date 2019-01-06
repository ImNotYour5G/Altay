<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\event\player;

use pocketmine\event\Event;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\NetworkInterface;
use pocketmine\Player;
use function is_a;

/**
 * Allows the creation of players overriding the base Player class
 */
class PlayerCreationEvent extends Event{

	/** @var NetworkSession */
	private $session;

	/** @var Player::class */
	private $baseClass = Player::class;
	/** @var Player::class */
	private $playerClass = Player::class;


	/**
	 * @param NetworkSession $session
	 */
	public function __construct(NetworkSession $session){
		$this->session = $session;
	}

	/**
	 * @return NetworkInterface
	 */
	public function getInterface() : NetworkInterface{
		return $this->session->getInterface();
	}

	/**
	 * @return NetworkSession
	 */
	public function getNetworkSession() : NetworkSession{
		return $this->session;
	}

	/**
	 * @return string
	 */
	public function getAddress() : string{
		return $this->session->getIp();
	}

	/**
	 * @return int
	 */
	public function getPort() : int{
		return $this->session->getPort();
	}

	/**
	 * @return Player::class
	 */
	public function getBaseClass(){
		return $this->baseClass;
	}

	/**
	 * @param Player::class $class
	 */
	public function setBaseClass($class){
		if(!is_a($class, $this->baseClass, true)){
			throw new \RuntimeException("Base class $class must extend " . $this->baseClass);
		}

		$this->baseClass = $class;
	}

	/**
	 * @return Player::class
	 */
	public function getPlayerClass(){
		return $this->playerClass;
	}

	/**
	 * @param Player::class $class
	 */
	public function setPlayerClass($class){
		if(!is_a($class, $this->baseClass, true)){
			throw new \RuntimeException("Class $class must extend " . $this->baseClass);
		}

		$this->playerClass = $class;
	}

}