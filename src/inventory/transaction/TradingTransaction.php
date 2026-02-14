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

namespace pocketmine\inventory\transaction;

use pocketmine\inventory\recipe\TradeRecipe;
use pocketmine\inventory\recipe\TradeRecipeData;
use pocketmine\inventory\TradeInventory;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;

final class TradingTransaction extends InventoryTransaction {
	private Item $buyA;
	private ?Item $buyB = null;

	public function __construct(
		Player $source,
		private readonly TradeRecipeData $recipeData,
		private readonly TradeRecipe $recipe
	) {
		parent::__construct($source);

		$this->buyA = $recipe->getBuyA();
		$this->buyB = $recipe->getBuyB();
	}

	public function validate(): void {
		$window = $this->source->getCurrentWindow();

		if (!$window instanceof TradeInventory) throw new TransactionValidationException("Transaction expected " . TradeInventory::class . " class, but received " . $window::class);

		if ($this->recipe->isDisabled()) throw new TransactionValidationException("Tried to execute transaction on disabled trade recipe");

		$buyA = $window->getItem(0);
		$buyB = $window->getItem(1);

		if ($buyA->isNull()) throw new TransactionValidationException("No item inputs");
		if (!$buyA->equals($this->buyA) || $buyA->getCount() < $this->buyA->getCount()) throw new TransactionValidationException("Invalid buyA item input");

		if ($this->buyB !== null && (!$buyB->equals($this->buyB) || $buyB->getCount() < $this->buyB->getCount())) throw new TransactionValidationException("Invalid buyB item input");
	}

	public function execute(): void {
		parent::execute();

		$this->recipe->setUses($this->recipe->getUses() + 1);
		$this->recipeData->setTradeExperience($this->recipeData->getTradeExperience() + $this->recipe->getTraderExp());
		$this->source->getWorld()->dropExperience(($position = $this->source->getPosition()), $this->recipe->getTraderExp());
		$this->source->getNetworkSession()->sendDataPacket(PlaySoundPacket::create((bool) rand(0, 1) ? "mob.wanderingtrader.idle" : "mob.wanderingtrader.yes", $position->x, $position->y, $position->z, 1, round(0.8 + 0.4 * (rand() / getrandmax()), 2)));
		$tierExpRequirements = $this->recipeData->getTierExpRequirements();
		$nextTier = $this->recipeData->getTier() + 1;
		if ($nextTier >= ($last = (int) array_key_last($tierExpRequirements))) $nextTier = $last;

		if (isset($tierExpRequirements[$nextTier]) && $this->recipeData->getTradeExperience() >= $tierExpRequirements[$nextTier]) $this->recipeData->setTier($nextTier);
	}

	protected function callExecuteEvent(): bool {
		return true;
	}
}
