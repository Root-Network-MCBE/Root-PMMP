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
use pocketmine\entity\utils\ArmorStandPose;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;

final class PlayerChangeArmorStandPoseEvent extends EntityEvent implements Cancellable{
	use CancellableTrait;

	protected ArmorStandPose $oldPose;
	protected ArmorStandPose $newPose;
	protected Player $causer;

	public function __construct(ArmorStand $entity, ArmorStandPose $oldPose, ArmorStandPose $newPose, Player $causer){
		$this->entity = $entity;
		$this->oldPose = $oldPose;
		$this->newPose = $newPose;
		$this->causer = $causer;
	}

	public function getOldPose() : ArmorStandPose{
		return $this->oldPose;
	}

	public function getNewPose() : ArmorStandPose{
		return $this->newPose;
	}

	public function setNewPose(ArmorStandPose $newPose) : void{
		$this->newPose = $newPose;
	}

	public function getCauser() : Player{
		return $this->causer;
	}
}