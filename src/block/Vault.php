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

use pocketmine\block\tile\Vault as TileVault;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\VaultState;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Vault extends Transparent implements HorizontalFacing{
	use FacesOppositePlacingPlayerTrait;

	private bool $ominous = false;
	private VaultState $state = VaultState::INACTIVE;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacing($this->facing);
		$w->enum($this->state);
		$w->bool($this->ominous);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player === null) return false;

		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);

		if($tile instanceof TileVault){
			return $tile->tryOpen($player, $item);
		}

		return false;
	}

	public function getState() : VaultState{
		return $this->state;
	}

	/** @return $this */
	public function setState(VaultState $state) : self{
		$this->state = $state;
		return $this;
	}

	public function isOminous() : bool{
		return $this->ominous;
	}

	/** @return $this */
	public function setOminous(bool $ominous) : self{
		$this->ominous = $ominous;
		return $this;
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if($tile instanceof TileVault && $tile->onUpdate()){
			$world->scheduleDelayedBlockUpdate($this->position, 1);
		}
	}
}
