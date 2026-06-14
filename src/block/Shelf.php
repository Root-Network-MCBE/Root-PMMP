<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\HorizontalFacingTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\block\tile\Shelf as ShelfTile;

class Shelf extends Transparent implements HorizontalFacing {
	use HorizontalFacingTrait {
		describeBlockOnlyState as describeFacing;
	}

	public const TYPE_UNCONNECTED = 0;
	public const TYPE_RIGHT = 1;
	public const TYPE_CENTER = 2;
	public const TYPE_LEFT = 3;

	private const SLOT_COUNT = 3;

	private int $shelfType = self::TYPE_UNCONNECTED;
	private bool $powered = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$this->describeFacing($w);
		$w->boundedIntAuto(0, 3, $this->shelfType);
		$w->bool($this->powered);
	}

	public function getShelfType() : int{
		return $this->shelfType;
	}

	public function setShelfType(int $shelfType) : void{
		$this->shelfType = $shelfType;
	}

	public function isPowered() : bool{
		return $this->powered;
	}

	public function setPowered(bool $powered) : void{
		$this->powered = $powered;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->setFacing(Facing::opposite($player->getHorizontalFacing()));
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player === null || $player->isSneaking()){
			return false;
		}

		$tile = $this->position->getWorld()->getTile($this->position);
		if(!$tile instanceof ShelfTile){
			$tile?->close();
			$tile = new ShelfTile($this->position->getWorld(), $this->position);
			$this->position->getWorld()->addTile($tile);
		}

		$slot = $this->calculateSlot($clickVector);
		$inv = $tile->getInventory();

		$current = $inv->getItem($slot);
		$hand = $player->getInventory()->getItemInHand();

		if(!$player->isCreative()){
			$player->getInventory()->setItemInHand($current);
		}

		$inv->setItem($slot, $hand);
		$tile->setDirty();
		return true;
	}

	private function calculateSlot(Vector3 $click) : int{
		$facing = $this->getFacing();
		$x = Facing::axis($facing) === Axis::X ? $click->z : $click->x;

		if(Facing::isPositive(Facing::rotateY($facing, true))){
			$x = 1 - $x;
		}

		return $x < 1 / self::SLOT_COUNT
			? 0
			: ($x < 2 / self::SLOT_COUNT ? 1 : 2);
	}
}
