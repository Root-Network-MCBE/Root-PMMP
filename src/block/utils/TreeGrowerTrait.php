<?php

namespace pocketmine\block\utils;

use pocketmine\event\block\StructureGrowEvent;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\generator\object\TreeFactory;

trait TreeGrowerTrait{

	private function growTree(TreeTypeProvider $provider, ?Player $player = null) : bool{
		$random = new Random(mt_rand());
		$tree = TreeFactory::get($random, $provider->getTreeType());
		$pos = $this->position;

		$transaction = $tree?->getBlockTransaction(
			$pos->getWorld(),
			$pos->getFloorX(),
			$pos->getFloorY(),
			$pos->getFloorZ(),
			$random
		);

		if($transaction === null){
			return false;
		}

		$ev = new StructureGrowEvent($this, $transaction, $player);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}
		return $transaction->apply();
	}
}