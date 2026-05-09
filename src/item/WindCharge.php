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

namespace pocketmine\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\entity\projectile\WindCharge as WindChargeEntity;
use pocketmine\player\Player;

class WindCharge extends ProjectileItem{
	public function getMaxStackSize() : int{
		return 64;
	}

	protected function createEntity(Location $location, Player $thrower) : Throwable{
		return new WindChargeEntity($location, $thrower);
	}

	public function getThrowForce() : float{
		return 1.5;
	}

	public function getCooldownTicks() : int{
		return 10;
	}
}