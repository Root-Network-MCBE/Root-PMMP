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

namespace pocketmine\item;

use pocketmine\entity\Entity;
use pocketmine\item\enchantment\VanillaEnchantments;
use function max;
use function min;

class Mace extends Tool{
	private const ATTACK_POINTS = 6;
	private const MAX_DURABILITY = 500;
	private const MINIMUM_SMASH_FALL_DISTANCE = 1.5;

	public function getAttackPoints() : int{
		return self::ATTACK_POINTS;
	}

	public function getMaxDurability() : int{
		return self::MAX_DURABILITY;
	}

	public function getEnchantability() : int{
		return 15;
	}

	public function canSmashAttack(float $fallDistance) : bool{
		return $fallDistance > self::MINIMUM_SMASH_FALL_DISTANCE;
	}

	public function getSmashAttackDamage(float $fallDistance) : float{
		$fallenBlocks = max(0.0, $fallDistance);
		$damage = min($fallenBlocks, 3.0) * 4.0;

		if($fallenBlocks > 3.0){
			$damage += min($fallenBlocks - 3.0, 5.0) * 2.0;
		}
		if($fallenBlocks > 8.0){
			$damage += $fallenBlocks - 8.0;
		}

		$densityLevel = $this->getEnchantmentLevel(VanillaEnchantments::DENSITY());
		if($densityLevel > 0){
			$damage += $fallenBlocks * 0.5 * $densityLevel;
		}

		return $damage;
	}

	public function getBreachArmorReduction() : float{
		return min(1.0, $this->getEnchantmentLevel(VanillaEnchantments::BREACH()) * 0.15);
	}

	public function getWindBurstLevel() : int{
		return $this->getEnchantmentLevel(VanillaEnchantments::WIND_BURST());
	}

	public function onAttackEntity(Entity $victim, array &$returnedItems) : bool{
		return $this->applyDamage(1);
	}
}
