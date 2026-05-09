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

namespace pocketmine\inventory\transaction;

use pocketmine\inventory\recipe\TradeRecipe;
use pocketmine\inventory\recipe\TradeRecipeData;
use pocketmine\inventory\TradeInventory;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use function array_key_last;
use function getrandmax;
use function rand;
use function round;

final class TradingTransaction extends InventoryTransaction{
	private Item $buyA;
	private ?Item $buyB = null;

	public function __construct(
		Player $source,
		private readonly TradeRecipeData $recipeData,
		private readonly TradeRecipe $recipe
	){
		parent::__construct($source);

		$this->buyA = $recipe->getBuyA();
		$this->buyB = $recipe->getBuyB();
	}

	public function validate() : void{
		$window = $this->source->getCurrentWindow();

		if(!$window instanceof TradeInventory) {
			throw new TransactionValidationException("Transaction expected " . TradeInventory::class . " class, but received " . $window::class);
		}

		if($this->recipe->isDisabled()) {
			throw new TransactionValidationException("Tried to execute transaction on disabled trade recipe");
		}

		$buyA = $window->getItem(0);
		$buyB = $window->getItem(1);

		$logger = $this->source->getServer()->getLogger();
		$logger->debug("[TradingTransaction] Validate — window slot0: " . $buyA->getName() . " x" . $buyA->getCount() . " (null=" . ($buyA->isNull() ? 'yes' : 'no') . ")");
		$logger->debug("[TradingTransaction] Validate — recipe buyA: " . $this->buyA->getName() . " x" . $this->buyA->getCount());
		$logger->debug("[TradingTransaction] Validate — equals=" . ($buyA->equals($this->buyA) ? 'yes' : 'no') . " countOK=" . ($buyA->getCount() >= $this->buyA->getCount() ? 'yes' : 'no'));
		if($this->buyB !== null){
			$logger->debug("[TradingTransaction] Validate — window slot1: " . $buyB->getName() . " x" . $buyB->getCount());
			$logger->debug("[TradingTransaction] Validate — recipe buyB: " . $this->buyB->getName() . " x" . $this->buyB->getCount());
		}

		if($buyA->isNull()) {
			throw new TransactionValidationException("No item inputs");
		}
		if(!$buyA->equals($this->buyA) || $buyA->getCount() < $this->buyA->getCount()) {
			throw new TransactionValidationException("Invalid buyA item input");
		}

		if($this->buyB !== null && (!$buyB->equals($this->buyB) || $buyB->getCount() < $this->buyB->getCount())) {
			throw new TransactionValidationException("Invalid buyB item input");
		}
	}

	public function execute() : void{
		parent::execute();

		$this->recipe->setUses($this->recipe->getUses() + 1);
		$this->recipeData->setTradeExperience($this->recipeData->getTradeExperience() + $this->recipe->getTraderExp());
		$this->source->getWorld()->dropExperience(($position = $this->source->getPosition()), $this->recipe->getTraderExp());
		$this->source->getNetworkSession()->sendDataPacket(PlaySoundPacket::create((bool) rand(0, 1) ? "mob.wanderingtrader.idle" : "mob.wanderingtrader.yes", $position->x, $position->y, $position->z, 1, round(0.8 + 0.4 * (rand() / getrandmax()), 2), null));
		$tierExpRequirements = $this->recipeData->getTierExpRequirements();
		$nextTier = $this->recipeData->getTier() + 1;
		if($nextTier >= ($last = (int) array_key_last($tierExpRequirements))) {
			$nextTier = $last;
		}

		if(isset($tierExpRequirements[$nextTier]) && $this->recipeData->getTradeExperience() >= $tierExpRequirements[$nextTier]) {
			$this->recipeData->setTier($nextTier);
		}
	}

	protected function callExecuteEvent() : bool{
		return true;
	}
}
