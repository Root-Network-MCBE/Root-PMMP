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

namespace pocketmine\inventory;

use pocketmine\entity\Entity;
use pocketmine\inventory\recipe\TradeRecipe;
use pocketmine\inventory\recipe\TradeRecipeData;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\network\mcpe\protocol\UpdateTradePacket;
use pocketmine\player\Player;

final class TradeInventory extends SimpleInventory {
	private CompoundTag $offers;

	public function __construct(
		private readonly string $name,
		private readonly Entity $entity,
		private readonly TradeRecipeData $recipeData
	) {
		parent::__construct(2);

		$this->parseTradeData();
	}

	public function getRecipeData(): TradeRecipeData {
		return $this->recipeData;
	}

	public function parseTradeData(): void {
		$recipeData = $this->getRecipeData();

		$recipes = $recipeData->getRecipes();

		$tierExpRequirements = [];

		foreach ($recipeData->getTierExpRequirements() as $tier => $expRequirement) {
			$tierExpRequirements[] = CompoundTag::create()
				->setInt((string) $tier, $expRequirement);
		}

		$recipesTag = new ListTag();
		for ($i = 0; $i < count($recipes); $i++) {
			$recipeNBT = $recipes[$i]->serialize();
			$recipeNBT->setInt(TradeRecipe::TAG_NET_ID, $i + 1);
			$recipesTag->push($recipeNBT);
		}

		$nbt = CompoundTag::create()
			->setTag(TradeRecipeData::TAG_RECIPES, $recipesTag)
			->setTag(TradeRecipeData::TAG_TIER_EXP_REQUIREMENTS, new ListTag($tierExpRequirements));

		$this->offers = $nbt;
	}

	public function getHolder(): Entity {
		return $this->entity;
	}

	public function onOpen(Player $who): void {
		parent::onOpen($who);

		$this->entity->getNetworkProperties()->setLong(EntityMetadataProperties::TRADING_PLAYER_EID, $who->getId());
	}

	public function onClose(Player $who): void {
		parent::onClose($who);

		$this->entity->getNetworkProperties()->setLong(EntityMetadataProperties::TRADING_PLAYER_EID, -1);
	}

	public function createInventoryOpenPackets(int $id): array {
		return [
			UpdateTradePacket::create(
				$id,
				WindowTypes::TRADING,
				0,
				$this->getRecipeData()->getTier(),
				$this->entity->getId(),
				-1,
				$this->name,
				true,
				true,
				new CacheableNbt($this->offers)
			)
		];
	}
}
