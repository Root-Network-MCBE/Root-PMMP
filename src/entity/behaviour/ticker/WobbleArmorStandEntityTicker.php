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

namespace pocketmine\entity\behaviour\ticker;

use pocketmine\entity\ArmorStand;

final class WobbleArmorStandEntityTicker implements ArmorStandEntityTicker{
	public const DATA_PROPERTY_WOBBLE = 11;
	public const DEFAULT_TICKS = 9;

	private int $ticks;

	public function __construct(ArmorStand $entity, int $ticks = self::DEFAULT_TICKS){
		$this->ticks = $ticks;
		$this->send($entity);
	}

	public function tick(ArmorStand $entity) : bool{
		$this->send($entity);
		return --$this->ticks >= 0;
	}

	private function send(ArmorStand $entity) : void{
		$entity->getNetworkProperties()->setInt(self::DATA_PROPERTY_WOBBLE, $this->ticks);
	}
}