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

namespace pocketmine\block;

use pocketmine\block\utils\SaplingType;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\TreeGrowerTrait;
use pocketmine\block\utils\TreeTypeProvider;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Fertilizer;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\generator\object\TreeType;
use function max;
use function min;
use function mt_rand;

final class MangrovePropagule extends Flowable implements TreeTypeProvider{
	use StaticSupportTrait;
	use TreeGrowerTrait;

	private bool $hanging = false;
	private int $stage = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->hanging);
		$w->boundedIntAuto(0, 4, $this->stage);
	}

	public function isHanging() : bool{ return $this->hanging; }
	/** @return $this */
	public function setHanging(bool $hanging) : self{ $this->hanging = $hanging; return $this; }

	public function getStage() : int{ return $this->stage; }
	/** @return $this */
	public function setStage(int $stage) : self{
		$this->stage = max(0, min(4, $stage));
		return $this;
	}

	public function getTreeType() : TreeType{
		return SaplingType::MANGROVE->getTreeType();
	}

	private function canBeSupportedAt(Block $block) : bool{
		if($this->hanging){
			return !$block->getSide(Facing::UP)->isTransparent();
		}
		$support = $block->getSide(Facing::DOWN);
		return $support->hasTypeTag(BlockTypeTags::DIRT) || $support->hasTypeTag(BlockTypeTags::MUD);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if(!$item instanceof Fertilizer){
			return false;
		}

		// stage first, then grow
		if($this->stage < 4){
			$this->stage++;
			$this->position->getWorld()->setBlock($this->position, $this);
			$item->pop();
			return true;
		}

		if($this->hanging){
			return true;
		}

		if($this->growTree($this, $player)){
			$item->pop();
			return true;
		}

		return false;
	}

	public function ticksRandomly() : bool{ return true; }

	public function onRandomTick() : void{
		$world = $this->position->getWorld();
		if($world->getFullLightAt($this->position->getFloorX(), $this->position->getFloorY(), $this->position->getFloorZ()) < 8){
			return;
		}

		if($this->stage < 4){
			if(mt_rand(1, 7) === 1){
				$this->stage++;
				$world->setBlock($this->position, $this);
			}
			return;
		}

		if(mt_rand(1, 7) === 1){
			$this->growTree($this, null);
		}
	}

	public function getFuelTime() : int{ return 100; }
}
