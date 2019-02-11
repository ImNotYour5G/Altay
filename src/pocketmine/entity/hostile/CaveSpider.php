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

namespace pocketmine\entity\hostile;

use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\nbt\tag\CompoundTag;

class CaveSpider extends Spider{

	public const NETWORK_ID = self::CAVE_SPIDER;

	public $width = 0.7;
	public $height = 0.5;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->setMaxHealth(12);
		$this->setHealth(12);
	}

	public function getName() : string{
		return "CaveSpider";
	}

	public function onCollideWithEntity(Entity $entity) : void{
		parent::onCollideWithEntity($entity);

		if($entity instanceof Living){
			if($this->getTargetEntity() === $entity){
				$entity->addEffect(new EffectInstance(Effect::getEffect(Effect::POISON), 7 * 20, 1));
			}
		}
	}
}