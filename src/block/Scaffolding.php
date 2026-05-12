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

namespace pocketmine\block;

use InvalidArgumentException;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;

class Scaffolding extends Transparent {
	protected int $stability = 0;
	protected bool $stabilityCheck = false;

	public function getSupportType(int $facing): SupportType {
		return SupportType::NONE;
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w): void{
		$w->boundedIntAuto(0, 7, $this->stability);
		$w->bool($this->stabilityCheck);
	}

	public function getStability(): int{
		return $this->stability;
	}

	public function setStability(int $stability): self{
		if ($stability < 0 || $stability > 7) {
			throw new InvalidArgumentException("Value must be between 0 and 7, got $stability");
		}

		$this->stability = $stability;
		return $this;
	}

	public function isStabilityCheck(): bool
	{
		return $this->stabilityCheck;
	}

	public function setStabilityCheck(bool $stabilityCheck): self
	{
		$this->stabilityCheck = $stabilityCheck;
		return $this;
	}
}