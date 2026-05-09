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

namespace pocketmine\inventory\recipe;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\Tag;
use function array_map;

final class TradeRecipeData
{
	public const TAG_TRADE_TIER = "TradeTier";
	public const TAG_OFFERS = "Offers";
	public const TAG_RECIPES = "Recipes";
	public const TAG_TRADE_EXPERIENCE = "TradeExperience";
	public const TAG_TIER_EXP_REQUIREMENTS = "TierExpRequirements";

	public const DEFAULT_TIER_EXP_REQUIREMENTS = [
		0 => 0,
		1 => 10,
		2 => 70,
		3 => 150,
		4 => 250
	];

	public function __construct(
		private array $recipes,
		private int $tier = 0,
		private int $tradeExperience = 0,
		private array $tierExpRequirements = self::DEFAULT_TIER_EXP_REQUIREMENTS,
	) {
	}

	public function addRecipe(TradeRecipe ...$recipe) : void {
		$this->recipes = $recipe;
	}

	public function setTierExpRequirement(int $tier, int $expRequirement) : void {
		$this->tierExpRequirements[$tier] = $expRequirement;
	}

	public function setTier(int $tier) : void {
		$this->tier = $tier;
	}

	public function setTradeExperience(int $tradeExperience) : void {
		$this->tradeExperience = $tradeExperience;
	}

	public function getRecipes() : array {
		return $this->recipes;
	}

	public function getTierExpRequirements() : array {
		return $this->tierExpRequirements;
	}

	public function getRecipe(int $index) : ?TradeRecipe {
		return $this->recipes[$index] ?? null;
	}

	public function getTier() : int {
		return $this->tier;
	}

	public function getTradeExperience() : int {
		return $this->tradeExperience;
	}

	public function getItems() : array {
		$items = [];

		/** @var TradeRecipe $recipe */
		foreach ($this->recipes as $recipe) {
			$items[] = [
				"buyA" => $recipe->getBuyA(),
				"buyB" => $recipe->getBuyB(),
				"sell" => $recipe->getSell()
			];
		}

		return $items;
	}

	public function serialize(CompoundTag $nbt) : CompoundTag {
		$nbt->setInt(self::TAG_TRADE_EXPERIENCE, $this->tradeExperience);
		$nbt->setInt(self::TAG_TRADE_TIER, $this->tier);

		$offers = CompoundTag::create();

		$recipes = $this->recipes;
		$recipesTag = array_map(static function (TradeRecipe $recipe) : Tag {
			return $recipe->serialize();
		}, $recipes);
		$offers->setTag(self::TAG_RECIPES, new ListTag($recipesTag));

		$tierExpRequirements = [];
		foreach ($this->tierExpRequirements as $tier => $expRequirement) {
			$tierExpRequirements[] = CompoundTag::create()->setInt((string) $tier, $expRequirement);
		}
		$offers->setTag(self::TAG_TIER_EXP_REQUIREMENTS, new ListTag($tierExpRequirements));

		$nbt->setTag(self::TAG_OFFERS, $offers);

		return $nbt;
	}
}
