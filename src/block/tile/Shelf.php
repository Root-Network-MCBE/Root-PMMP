<?php

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\block\inventory\ShelfInventory;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\World;

class Shelf extends Spawnable implements Container {
	use ContainerTrait;

	protected ShelfInventory $inventory;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new ShelfInventory($this->position);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadItems($nbt);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveItems($nbt);
	}

	public function close() : void{
		if(!$this->closed){
			$this->inventory->removeAllViewers();
			parent::close();
		}
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$items = [];
		foreach($this->getRealInventory()->getContents() as $slot => $item){
			$items[] = $item->nbtSerialize($slot);
		}

		$nbt->setTag(Container::TAG_ITEMS, new ListTag($items, NBT::TAG_Compound));
	}

	public function getInventory() : ShelfInventory{
		return $this->inventory;
	}

	public function getRealInventory() : ShelfInventory{
		return $this->inventory;
	}
}