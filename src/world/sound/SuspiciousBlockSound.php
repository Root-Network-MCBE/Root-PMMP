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

namespace pocketmine\world\sound;

use pocketmine\block\Block;
use pocketmine\block\SuspiciousSand;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use function mt_rand;

final class SuspiciousBlockSound implements Sound{

	private function __construct(
		private Block $block,
		private string $action,
		private float $volume,
		private float $pitch
	){}

	public static function break(Block $block) : self{
		return new self($block, "break", 1.0, self::randomBlockPitch());
	}

	public static function place(Block $block) : self{
		return new self($block, "place", 1.0, self::randomBlockPitch());
	}

	public static function hit(Block $block) : self{
		return new self($block, "hit", 0.23, 0.5);
	}

	public static function step(Block $block, float $volume = 0.2) : self{
		return new self($block, "step", $volume, 1.0);
	}

	private static function randomBlockPitch() : float{
		return mt_rand(80, 100) / 100;
	}

	public function encode(Vector3 $pos) : array{
		$suffix = $this->block instanceof SuspiciousSand ? "suspicious_sand" : "suspicious_gravel";
		return [PlaySoundPacket::create(
			$this->action . "." . $suffix,
			$pos->x,
			$pos->y,
			$pos->z,
			$this->volume,
			$this->pitch,
			null
		)];
	}
}
