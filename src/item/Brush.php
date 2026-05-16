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
use pocketmine\block\SuspiciousGravel;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\particle\BrushDustParticle;
use pocketmine\world\sound\BrushSound;

final class Brush extends Tool implements ItemUseOnBlockHandler{
	private const BRUSH_TICK_INTERVAL = 10;

	public function getMaxDurability() : int{
		return 64;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		return $blockClicked instanceof SuspiciousGravel ? ItemUseResult::SUCCESS : ItemUseResult::NONE;
	}

	public function canStartUsingItemOnBlock(Player $player, Block $block, int $face, Vector3 $clickVector) : bool{
		return $block instanceof SuspiciousGravel;
	}

	public function onUsingItemOnBlockTick(Player $player, Block $block, int $face, Vector3 $clickVector, int $useDuration, array &$returnedItems) : ItemUseResult{
		if($useDuration % self::BRUSH_TICK_INTERVAL !== 0){
			return ItemUseResult::NONE;
		}

		[$offsetX, $offsetY, $offsetZ] = Facing::OFFSET[$face];
		$particlePos = $block->getPosition()->add(
			$offsetX * 0.51 + $clickVector->x,
			$offsetY * 0.51 + $clickVector->y,
			$offsetZ * 0.51 + $clickVector->z
		);

		$world = $player->getWorld();
		$world->addParticle($particlePos, new BrushDustParticle($block));
		$world->addSound($particlePos, new BrushSound($block));

		if($block instanceof SuspiciousGravel){
			$block->brush($particlePos, $face, $useDuration, $player->getServer()->getTick());
		}
		$this->applyDamage(1);

		return ItemUseResult::SUCCESS;
	}
}
