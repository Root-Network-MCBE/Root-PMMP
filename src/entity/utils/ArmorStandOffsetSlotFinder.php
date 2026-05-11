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

namespace pocketmine\entity\utils;

use pocketmine\inventory\ArmorInventory;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

final class ArmorStandOffsetSlotFinder{
	public static function getArmorInventorySlotBoxes() : \Generator{
		yield ArmorInventory::SLOT_HEAD => ArmorStandEquipmentBoxes::HELMET();
		yield ArmorInventory::SLOT_CHEST => ArmorStandEquipmentBoxes::CHESTPLATE();
		yield ArmorInventory::SLOT_LEGS => ArmorStandEquipmentBoxes::LEGGINGS();
		yield ArmorInventory::SLOT_FEET => ArmorStandEquipmentBoxes::BOOTS();
	}

	public static function isOffsetInsideBB(Vector3 $offset, AxisAlignedBB $bb) : bool{
		return $bb->isVectorInXY($offset) || $bb->isVectorInYZ($offset);
	}

	public static function findArmorInventorySlot(Vector3 $offset) : ?int{
		foreach(self::getArmorInventorySlotBoxes() as $slot => $bb){
			if(self::isOffsetInsideBB($offset, $bb)){
				return $slot;
			}
		}
		return null;
	}

	public static function isRightArm(Vector3 $offset) : bool{
		return self::isOffsetInsideBB($offset, ArmorStandEquipmentBoxes::RIGHT_ARM());
	}
}