<?php

declare(strict_types=1);

namespace pocketmine\entity\behaviour;

use InvalidArgumentException;
use pocketmine\entity\ArmorStand;
use pocketmine\entity\utils\ArmorStandOffsetSlotFinder;
use pocketmine\event\entity\PlayerChangeArmorStandArmorEvent;
use pocketmine\event\entity\PlayerChangeArmorStandHeldItemEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class NullItemArmorStandBehaviour implements ArmorStandBehaviour{
	public function __construct(){
	}

	public function handleEquipment(Player $player, ArmorStand $entity, Vector3 $click_pos) : void{
		$inventory = $player->getInventory();
		$item = $inventory->getItemInHand();

		if(!$item->isNull()){
			throw new InvalidArgumentException(self::class . " does not accept item {$item}");
		}

		$offset = $click_pos;
		$newItem = VanillaItems::AIR();

		if(ArmorStandOffsetSlotFinder::isRightArm($offset)){
			$oldItem = $entity->getItemInHand();

			if($oldItem->isNull()){
				return;
			}

			$ev = new PlayerChangeArmorStandHeldItemEvent($entity, $oldItem, $newItem, $player);
			$ev->call();

			if($ev->isCancelled()){
				return;
			}

			$inventory->setItemInHand($oldItem);
			$entity->setItemInHand($ev->getNewItem());
			return;
		}

		$armorSlot = ArmorStandOffsetSlotFinder::findArmorInventorySlot($offset) ?? ArmorInventory::SLOT_HEAD;
		$entityInventory = $entity->getArmorInventory();
		$oldItem = $entityInventory->getItem($armorSlot);

		if($oldItem->isNull()){
			return;
		}

		$ev = new PlayerChangeArmorStandArmorEvent($entity, $armorSlot, $oldItem, $newItem, $player);
		$ev->call();

		if($ev->isCancelled()){
			return;
		}

		$inventory->setItemInHand($oldItem);
		$entityInventory->setItem($armorSlot, $ev->getNewItem());
	}
}