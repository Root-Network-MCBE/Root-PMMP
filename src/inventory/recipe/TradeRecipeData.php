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

namespace pocketmine\inventory\recipe;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\Tag;

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

	public function addRecipe(TradeRecipe ...$recipe): void {
		$this->recipes = $recipe;
	}

	public function setTierExpRequirement(int $tier, int $expRequirement): void {
		$this->tierExpRequirements[$tier] = $expRequirement;
	}

	public function setTier(int $tier): void {
		$this->tier = $tier;
	}

	public function setTradeExperience(int $tradeExperience): void {
		$this->tradeExperience = $tradeExperience;
	}

	public function getRecipes(): array {
		return $this->recipes;
	}

	public function getTierExpRequirements(): array {
		return $this->tierExpRequirements;
	}

	public function getRecipe(int $index): ?TradeRecipe {
		return $this->recipes[$index] ?? null;
	}

	public function getTier(): int {
		return $this->tier;
	}

	public function getTradeExperience(): int {
		return $this->tradeExperience;
	}

	public function getItems(): array {
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

	public function serialize(CompoundTag $nbt): CompoundTag {
		$nbt->setInt(self::TAG_TRADE_EXPERIENCE, $this->tradeExperience);
		$nbt->setInt(self::TAG_TRADE_TIER, $this->tier);

		$offers = CompoundTag::create();

		$recipes = $this->recipes;
		$recipesTag = array_map(static function (TradeRecipe $recipe): Tag {
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
