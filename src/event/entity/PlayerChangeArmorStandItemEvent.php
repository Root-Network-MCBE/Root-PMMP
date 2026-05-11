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
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\item\Item;
use pocketmine\player\Player;

abstract class PlayerChangeArmorStandItemEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	protected Item $oldItem;
	protected Item $newItem;
	protected Player $causer;

	public function __construct(ArmorStand $entity, Item $old_item, Item $new_item, Player $causer){
		$this->entity = $entity;
		$this->oldItem = $old_item;
		$this->newItem = $new_item;
		$this->causer = $causer;
	}

	final public function getOldItem() : Item{
		return $this->oldItem;
	}

	final public function getNewItem() : Item{
		return $this->newItem;
	}

	final public function setNewItem(Item $newItem) : void{
		$this->newItem = $newItem;
	}

	final public function getCauser() : Player{
		return $this->causer;
	}
}