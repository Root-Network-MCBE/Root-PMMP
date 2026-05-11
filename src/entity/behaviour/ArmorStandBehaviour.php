<?php

declare(strict_types=1);

namespace pocketmine\entity\behaviour;

use pocketmine\entity\ArmorStand;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

interface ArmorStandBehaviour{
	public function handleEquipment(Player $player, ArmorStand $entity, Vector3 $click_pos) : void;
}