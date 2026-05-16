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

namespace pocketmine\block\tile;

use pocketmine\block\SuspiciousSand;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\world\World;
use function max;
use function min;

class BrushableBlock extends Spawnable{
	public const TAG_ITEM = "item";
	public const TAG_BRUSH_COUNT = "brush_count";
	public const TAG_BRUSH_DIRECTION = "brush_direction";
	public const TAG_TYPE = "type";

	private int $brushCount = 0;
	private int $brushDirection = 0;
	private int $lastBrushTick = 0;
	private int $decayDelayTicks = 60;
	private int $decayStepTicks = 20;

	private Item $item;

	public function __construct(World $world, Vector3 $pos){
		$this->item = VanillaItems::AIR();
		parent::__construct($world, $pos);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->brushCount = $nbt->getInt(self::TAG_BRUSH_COUNT, 0);
		$this->brushDirection = $nbt->getByte(self::TAG_BRUSH_DIRECTION, 0);
		if(($itemTag = $nbt->getCompoundTag(self::TAG_ITEM)) !== null){
			$this->item = Item::safeNbtDeserialize($itemTag, "BrushableBlock ($this->position) item");
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_BRUSH_COUNT, $this->brushCount);
		$nbt->setByte(self::TAG_BRUSH_DIRECTION, $this->brushDirection);
		if(!$this->item->isNull()){
			$nbt->setTag(self::TAG_ITEM, $this->item->nbtSerialize());
		}
	}

	public function getBrushCount() : int{
		return $this->brushCount;
	}

	public function setBrushCount(int $brushCount) : void{
		$this->brushCount = max(0, min(3, $brushCount));
		$this->clearSpawnCompoundCache();
	}

	public function getBrushDirection() : int{
		return $this->brushDirection;
	}

	public function setBrushDirection(int $brushDirection) : void{
		$this->brushDirection = $brushDirection & 0xff;
		$this->clearSpawnCompoundCache();
	}

	public function touchBrushing(int $currentTick) : void{
		$this->lastBrushTick = $currentTick;
	}

	public function getLastBrushTick() : int{
		return $this->lastBrushTick;
	}

	public function getDecayDelayTicks() : int{
		return $this->decayDelayTicks;
	}

	public function getDecayStepTicks() : int{
		return $this->decayStepTicks;
	}

	public function getItem() : Item{
		return clone $this->item;
	}

	public function setItem(?Item $item) : void{
		$this->item = $item !== null && !$item->isNull() ? clone $item : VanillaItems::AIR();
		$this->clearSpawnCompoundCache();
	}

	public function hasItem() : bool{
		return !$this->item->isNull();
	}

	public function popItem() : Item{
		$item = $this->item;
		$this->setItem(null);
		return $item;
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_BRUSH_COUNT, $this->brushCount);
		$nbt->setByte(self::TAG_BRUSH_DIRECTION, $this->brushDirection);
		$nbt->setString(self::TAG_TYPE, $this->position->getWorld()->getBlock($this->position) instanceof SuspiciousSand ? BlockTypeNames::SUSPICIOUS_SAND : BlockTypeNames::SUSPICIOUS_GRAVEL);
		if(!$this->item->isNull()){
			$nbt->setTag(self::TAG_ITEM, $this->item->nbtSerialize());
		}
	}

	public function sendToViewers(string $typeId = BlockTypeNames::SUSPICIOUS_GRAVEL) : void{
		$pos = $this->getPosition();

		$nbt = CompoundTag::create()
			->setString(self::TAG_ID, "brushable_block")
			->setInt(self::TAG_X, $this->position->x)
			->setInt(self::TAG_Y, $this->position->y)
			->setInt(self::TAG_Z, $this->position->z)
			->setInt(self::TAG_BRUSH_COUNT, $this->brushCount)
			->setByte(self::TAG_BRUSH_DIRECTION, $this->brushDirection)
			->setString(self::TAG_TYPE, $typeId);
		if(!$this->item->isNull()){
			$nbt->setTag(self::TAG_ITEM, $this->item->nbtSerialize());
		}

		$pk = new BlockActorDataPacket();
		$pk->blockPosition = BlockPosition::fromVector3($pos);
		$pk->nbt = new CacheableNbt($nbt);

		$world = $pos->getWorld();
		foreach ($world->getPlayers() as $p) {
			if ($p->getWorld() === $world && $p->getPosition()->distanceSquared($pos) <= 128*128) {
				$p->getNetworkSession()->sendDataPacket($pk);
			}
		}
	}
}
