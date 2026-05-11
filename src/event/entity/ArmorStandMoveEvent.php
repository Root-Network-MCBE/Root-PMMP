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
use pocketmine\entity\Location;

final class ArmorStandMoveEvent extends EntityEvent{
	private Location $from;
	private Location $to;

	public function __construct(ArmorStand $entity, Location $from, Location $to){
		$this->entity = $entity;
		$this->from = $from;
		$this->to = $to;
	}

	public function getFrom() : Location{
		return $this->from->asLocation();
	}

	public function getTo() : Location{
		return $this->to->asLocation();
	}
}