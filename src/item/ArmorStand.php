<?php

/**
 * .--.  .--.  .--. .---.
 * |   ):    ::    :  |
 * |--' |    ||    |  |
 * |  \ :    ;:    ;  |
 * '   ` `--'  `--'   '
 *       by Valres.
 *
 * FRA:
 * Ce code source est la propriété exclusive de Valres.
 * Toute utilisation, reproduction, modification ou distribution de ce code
 * sans autorisation écrite explicite est strictement interdite.
 *
 * ENG:
 * This source code is the exclusive property of Valres.
 * Any use, reproduction, modification, or distribution of this code
 * without explicit written authorization is strictly prohibited.
 */

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\entity\ArmorStand as ArmorStandEntity;
use pocketmine\entity\Location;
use pocketmine\event\player\PlayerPlaceArmorStandEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class ArmorStand extends Item{
	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if(!$blockClicked->isSolid()){
			return parent::onInteractBlock($player, $blockReplace, $blockClicked, $face, $clickVector, $returnedItems);
		}

		$pos = $blockClicked->getPosition();
		$world = $pos->getWorld();
		$spawnPos = $pos->addVector(Vector3::zero()->getSide($face))->add(0.5, 0.0, 0.5);
		foreach($world->getNearbyEntities((new AxisAlignedBB(-0.5, 0.0, -0.5, 0.5, 1.0, 0.5))->offset($spawnPos->x, $spawnPos->y, $spawnPos->z)) as $entity){
			if($entity instanceof ArmorStandEntity){
				return ItemUseResult::NONE();
			}
		}

		$yaw = fmod($player->getLocation()->getYaw() + 180.0, 360.0);
		$yaw = round($yaw / 45.0) * 45.0;

		($ev = new PlayerPlaceArmorStandEvent($player, Location::fromObject($spawnPos, $world, $yaw, 0.0)))->call();
		if($ev->isCancelled()){
			return ItemUseResult::NONE();
		}

		$entity = new ArmorStandEntity($ev->getLocation());
		$entity->spawnToAll();

		$this->pop();
		return ItemUseResult::SUCCESS();
	}
}