<?php

declare(strict_types=1);

namespace pocketmine\block\utils;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\data\bedrock\EffectIds;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;

final class BeaconLogic{

	public const MAX_LEVEL = 4;

	private function __construct(){
		//NOOP
	}

	/**
	 * The pyramid level (0-4) from the completeness of each base layer below the beacon, top layer first. Levels must be
	 * complete consecutively from the top: a gap stops counting.
	 *
	 * @param bool[] $layersComplete
	 */
	public static function pyramidLevel(array $layersComplete) : int{
		$level = 0;
		foreach($layersComplete as $complete){
			if(!$complete){
				break;
			}
			$level++;
			if($level >= self::MAX_LEVEL){
				break;
			}
		}
		return $level;
	}

	/**
	 * Horizontal effect range in blocks: 20 at level 1 up to 50 at level 4.
	 */
	public static function effectRange(int $level) : int{
		return $level * 10 + 10;
	}

	/**
	 * Effect duration in ticks; refreshed periodically while the beacon is active.
	 */
	public static function effectDurationTicks(int $level) : int{
		return (9 + $level * 2) * 20;
	}

	public static function isBeaconBaseBlock(Block $block) : bool{
		return match($block->getTypeId()){
			BlockTypeIds::IRON,
			BlockTypeIds::GOLD,
			BlockTypeIds::EMERALD,
			BlockTypeIds::DIAMOND,
			BlockTypeIds::NETHERITE => true,
			default => false
		};
	}

	public static function isValidPayment(Item $item) : bool{
		return match($item->getTypeId()){
			ItemTypeIds::IRON_INGOT,
			ItemTypeIds::GOLD_INGOT,
			ItemTypeIds::EMERALD,
			ItemTypeIds::DIAMOND,
			ItemTypeIds::NETHERITE_INGOT => true,
			default => false
		};
	}

	/**
	 * The Bedrock effect ids selectable as the primary power at the given level (cumulative as the pyramid grows).
	 *
	 * @return int[]
	 */
	public static function allowedPrimaryEffects(int $level) : array{
		$effects = [];
		if($level >= 1){
			$effects[] = EffectIds::SPEED;
			$effects[] = EffectIds::HASTE;
		}
		if($level >= 2){
			$effects[] = EffectIds::RESISTANCE;
			$effects[] = EffectIds::JUMP_BOOST;
		}
		if($level >= 3){
			$effects[] = EffectIds::STRENGTH;
		}
		return $effects;
	}

	public static function isAllowedPrimary(int $level, int $effectId) : bool{
		return in_array($effectId, self::allowedPrimaryEffects($level), true);
	}

	/**
	 * The secondary power is only available on a full level-4 pyramid, and may be either Regeneration or the same effect
	 * as the primary (which upgrades the primary to amplifier II). 0 means "no secondary".
	 */
	public static function isAllowedSecondary(int $level, int $primaryId, int $secondaryId) : bool{
		if($secondaryId === 0){
			return true;
		}
		if($level < self::MAX_LEVEL){
			return false;
		}
		return $secondaryId === EffectIds::REGENERATION || $secondaryId === $primaryId;
	}

	/**
	 * Whether choosing $secondaryId on a level-4 pyramid upgrades the primary effect to amplifier II (rather than adding a
	 * distinct second effect).
	 */
	public static function secondaryUpgradesPrimary(int $level, int $primaryId, int $secondaryId) : bool{
		return $level >= self::MAX_LEVEL && $secondaryId !== 0 && $secondaryId === $primaryId;
	}
}