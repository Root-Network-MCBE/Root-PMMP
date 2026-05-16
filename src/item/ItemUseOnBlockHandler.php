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
use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * Implemented by items which continue doing work while the player holds the use button on a block.
 */
interface ItemUseOnBlockHandler{

	public function canStartUsingItemOnBlock(Player $player, Block $block, int $face, Vector3 $clickVector) : bool;

	/**
	 * @param Item[] &$returnedItems
	 */
	public function onUsingItemOnBlockTick(Player $player, Block $block, int $face, Vector3 $clickVector, int $useDuration, array &$returnedItems) : ItemUseResult;
}
