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

namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class PlayerFishEvent extends PlayerEvent implements Cancellable
{
	use CancellableTrait;

	public function __construct(
		Player $player,
		protected Item $fishingRod,
		protected array $loots,
		protected int $experience
	) {
		$this->player = $player;
	}

	public function getFishingRod(): Item {
		return $this->fishingRod;
	}

	public function setFishingRod(Item $fishingRod) : void {
		$this->fishingRod = $fishingRod;
	}

	public function getLoots() : array {
		return $this->loots;
	}

	public function setLoot(array $loots) : void {
		$this->loots = $loots;
	}

	public function getExperience(): int {
		return $this->experience;
	}

	public function setExperience(int $experience): void {
		$this->experience = $experience;
	}
}
