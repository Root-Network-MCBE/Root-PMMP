<?php

declare(strict_types=1);

namespace pocketmine\block\utils;

use pocketmine\utils\LegacyEnumShimTrait;
use pocketmine\world\generator\object\TreeType;

/**
 * TODO: These tags need to be removed once we get rid of LegacyEnumShimTrait (PM6)
 *  These are retained for backwards compatibility only.
 *
 * @method static SaplingType ACACIA()
 * @method static SaplingType AZALEA()
 * @method static SaplingType BIRCH()
 * @method static SaplingType CHERRY()
 * @method static SaplingType DARK_OAK()
 * @method static SaplingType JUNGLE()
 * @method static SaplingType MANGROVE()
 * @method static SaplingType OAK()
 * @method static SaplingType PALE_OAK()
 * @method static SaplingType SPRUCE()
 */
enum SaplingType{
	use LegacyEnumShimTrait;

	case OAK;
	case SPRUCE;
	case BIRCH;
	case JUNGLE;
	case ACACIA;
	case DARK_OAK;
	case CHERRY;
	case AZALEA;
	case MANGROVE;
	case PALE_OAK;

	public function getTreeType() : TreeType{
		return match($this){
			self::OAK => TreeType::OAK,
			self::SPRUCE => TreeType::SPRUCE,
			self::BIRCH => TreeType::BIRCH,
			self::JUNGLE => TreeType::JUNGLE,
			self::ACACIA => TreeType::ACACIA,
			self::DARK_OAK => TreeType::DARK_OAK,
			self::CHERRY => TreeType::CHERRY,
			self::AZALEA => TreeType::AZALEA,
			self::MANGROVE => TreeType::MANGROVE,
			self::PALE_OAK => TreeType::PALE_OAK,
		};
	}

	public function getDisplayName() : string{
		return $this->getTreeType()->getDisplayName();
	}
}
