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

use pocketmine\block\tile\BrushableBlock;
use pocketmine\block\utils\Fallable;
use pocketmine\block\utils\FallableTrait;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Location;
use pocketmine\entity\object\FallingBlock;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\BrushCompletedSound;

class SuspiciousGravel extends Opaque implements Fallable{
	use FallableTrait;

	public const TAG_BRUSH_LOOT = "BrushLoot";

	public const MIN_BRUSHED_PROGRESS = 0;
	public const MAX_BRUSHED_PROGRESS = 3;
	private const COMPLETE_BRUSH_TICKS = 96;

	private int $brushedProgress = self::MIN_BRUSHED_PROGRESS;
	private bool $hanging = false;
	private ?Item $itemInside = null;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(self::MIN_BRUSHED_PROGRESS, self::MAX_BRUSHED_PROGRESS, $this->brushedProgress);
		$w->bool($this->hanging);
	}

	public function getBrushedProgress() : int{
		return $this->brushedProgress;
	}

	/**
	 * @return $this
	 */
	public function setBrushedProgress(int $brushedProgress) : self{
		if($brushedProgress < self::MIN_BRUSHED_PROGRESS || $brushedProgress > self::MAX_BRUSHED_PROGRESS){
			throw new \InvalidArgumentException("Brushed progress must be in range " . self::MIN_BRUSHED_PROGRESS . " - " . self::MAX_BRUSHED_PROGRESS);
		}
		$this->brushedProgress = $brushedProgress;
		return $this;
	}

	public function isHanging() : bool{
		return $this->hanging;
	}

	/**
	 * @return $this
	 */
	public function setHanging(bool $hanging) : self{
		$this->hanging = $hanging;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setItemInside(?Item $item) : self{
		$this->itemInside = $item !== null && !$item->isNull() ? clone $item : null;
		return $this;
	}

	public function getItemInside() : ?Item{
		return $this->itemInside !== null ? clone $this->itemInside : null;
	}

	public function hasItemInside() : bool{
		return $this->itemInside !== null;
	}

	public function asItem() : Item{
		$item = parent::asItem();
		if($this->itemInside !== null){
			$tag = $item->getNamedTag();
			$tag->setTag(self::TAG_BRUSH_LOOT, $this->itemInside->nbtSerialize());
			$item->setNamedTag($tag);
		}
		return $item;
	}

	private function getItemInsideFromPlacedItem(Item $item) : ?Item{
		$itemTag = $item->getNamedTag()->getCompoundTag(self::TAG_BRUSH_LOOT);
		return $itemTag !== null ? Item::safeNbtDeserialize($itemTag, "SuspiciousGravel item inside") : null;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(!parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player)){
			return false;
		}

		$this->itemInside = $this->getItemInsideFromPlacedItem($item) ?? $this->itemInside ?? VanillaItems::DIAMOND();
		return true;
	}

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		$down = $world->getBlock($this->position->getSide(Facing::DOWN));
		if(!$down->canBeReplaced()){
			return;
		}

		$fallingBlock = clone $this;
		$tile = $world->getTile($this->position);
		if($tile instanceof BrushableBlock && $tile->hasItem()){
			$fallingBlock->setItemInside($tile->getItem());
		}

		$world->setBlock($this->position, VanillaBlocks::AIR());

		$fall = new FallingBlock(Location::fromObject($this->position->add(0.5, 0, 0.5), $world), $fallingBlock);
		$fall->spawnToAll();
	}

	public function onPostPlace() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if($tile instanceof BrushableBlock){
			$tile->setItem($this->itemInside ?? VanillaItems::DIAMOND());
			$tile->setBrushCount($this->brushedProgress);
			$tile->sendToViewers($this->getBedrockTypeId());
		}
	}

	public function brush(Vector3 $dropPosition, int $face, int $useDuration, int $currentTick) : bool{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);

		$targetProgress = $this->getBrushedProgressForDuration($useDuration);
		$shouldSyncTile = false;
		if($tile instanceof BrushableBlock){
			$tile->setBrushDirection($face);
			$tile->touchBrushing($currentTick);
			if($tile->getBrushCount() !== $targetProgress){
				$tile->setBrushCount($targetProgress);
				$shouldSyncTile = true;
			}
		}

		if($this->brushedProgress !== $targetProgress){
			$world->setBlock($this->position, (clone $this)->setBrushedProgress($targetProgress), false);
		}
		if($shouldSyncTile && $tile instanceof BrushableBlock){
			$tile->sendToViewers($this->getBedrockTypeId());
		}

		if($useDuration < self::COMPLETE_BRUSH_TICKS){
			return false;
		}

		if($tile instanceof BrushableBlock){
			$item = $tile->popItem();
			if(!$item->isNull()){
				$world->dropItem($dropPosition, $item);
			}
			$world->removeTile($tile);
		}
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new BrushCompletedSound($this));
		$world->setBlock($this->position, $this->getNormalBlock(), false);
		return true;
	}

	protected function getBedrockTypeId() : string{
		return BlockTypeNames::SUSPICIOUS_GRAVEL;
	}

	protected function getNormalBlock() : Block{
		return VanillaBlocks::GRAVEL();
	}

	private function getBrushedProgressForDuration(int $useDuration) : int{
		return match(true){
			$useDuration >= 68 => 3,
			$useDuration >= 40 => 2,
			$useDuration >= 8 => 1,
			default => 0
		};
	}

	public function getDrops(Item $item) : array{
		return [];
	}
}
