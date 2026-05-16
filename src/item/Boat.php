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

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\block\utils\WaterHelper;
use pocketmine\entity\Location;
use pocketmine\entity\object\Boat as BoatEntity;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function fmod;

class Boat extends Item{
	private BoatType $boatType;
	private bool $withChest;

	public function __construct(ItemIdentifier $identifier, string $name, BoatType $boatType, bool $withChest = false){
		parent::__construct($identifier, $name);
		$this->boatType = $boatType;
		$this->withChest = $withChest;
	}

	public function getType() : BoatType{
		return $this->boatType;
	}

	public function hasChest() : bool{
		return $this->withChest;
	}

	public function getFuelTime() : int{
		return 1200; //400 in PC
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		$spawnInWater = WaterHelper::getWater($blockClicked) !== null;
		$spawnBase = $spawnInWater ? $blockClicked->getPosition() : $blockReplace->getPosition();
		$world = $spawnBase->getWorld();
		$spawnPos = $spawnBase->add(0.5, $spawnInWater ? 0.625 : 0.0, 0.5);
		$collisionBox = (new AxisAlignedBB(-0.7, 0.0, -0.7, 0.7, 0.6, 0.7))->offset($spawnPos->x, $spawnPos->y, $spawnPos->z);

		if($world->getBlockCollisionBoxes($collisionBox) !== []){
			return ItemUseResult::NONE;
		}
		foreach($world->getNearbyEntities($collisionBox) as $entity){
			if($entity->canBeCollidedWith()){
				return ItemUseResult::NONE;
			}
		}

		$yaw = fmod($player->getLocation()->getYaw() + 180.0, 360.0);
		$yaw = round($yaw / 90.0) * 90.0;
		$entity = new BoatEntity(Location::fromObject($spawnPos, $world, $yaw, 0.0), $this->boatType, $this->withChest);
		$entity->spawnToAll();

		$this->pop();
		return ItemUseResult::SUCCESS;
	}
}
