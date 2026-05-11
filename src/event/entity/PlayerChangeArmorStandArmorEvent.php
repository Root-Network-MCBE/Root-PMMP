<?php

/**
 * .--.  .--.  .--. .---.
 * |   ):    ::    :  |
 * |--' |    ||    |  |
 * |  \ :    ;:    ;  |
 * '   ` `--'  `--'   '
 *       by Valres.
 *
 * FRA:
 * Ce code source est la propriété exclusive de Valres.
 * Toute utilisation, reproduction, modification ou distribution de ce code
 * sans autorisation écrite explicite est strictement interdite.
 *
 * ENG:
 * This source code is the exclusive property of Valres.
 * Any use, reproduction, modification, or distribution of this code
 * without explicit written authorization is strictly prohibited.
 */

declare(strict_types=1);

namespace pocketmine\event\entity;

use pocketmine\entity\ArmorStand;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class PlayerChangeArmorStandArmorEvent extends PlayerChangeArmorStandItemEvent{
	protected int $slot;

	public function __construct(ArmorStand $entity, int $slot, Item $oldItem, Item $newItem, Player $causer){
		parent::__construct($entity, $oldItem, $newItem, $causer);
		$this->slot = $slot;
	}

	public function getSlot() : int{
		return $this->slot;
	}
}