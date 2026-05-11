<?php

declare(strict_types=1);

namespace pocketmine\entity\behaviour;

use pocketmine\entity\ArmorStand;
use pocketmine\event\entity\PlayerChangeArmorStandHeldItemEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class HeldItemArmorStandBehaviour implements ArmorStandBehaviour{
	public function __construct(){
	}

	public function handleEquipment(Player $player, ArmorStand $entity, Vector3 $click_pos) : void{
		$inventory = $player->getInventory();
		$item = $inventory->getItemInHand();

		if($item->isNull()){
			return;
		}

		$oldItem = $entity->getItemInHand();
		$newItem = $item->pop();

		$ev = new PlayerChangeArmorStandHeldItemEvent($entity, $oldItem, $newItem, $player);
		$ev->call();

		if($ev->isCancelled()){
			return;
		}

		$inventory->setItemInHand($item);

		foreach($inventory->addItem($oldItem) as $dropped){
			$player->getWorld()->dropItem($player->getEyePos(), $dropped);
		}

		$entity->setItemInHand($ev->getNewItem());
	}
}