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

namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;

final class CherryTree extends Tree {
	private const MIN_HEIGHT = 3;

	private ?Vector3 $mainBranchTip = null;
	private ?Vector3 $secondBranchTip = null;

	public function __construct(){
		parent::__construct(
			VanillaBlocks::CHERRY_LOG(),
			VanillaBlocks::CHERRY_LEAVES(),
			0
		);
	}

	protected function generateTrunkHeight(Random $random) : int{
		return self::MIN_HEIGHT + $random->nextRange(0, 2) + $random->nextRange(0, 2);
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());

		$firstBranchHeight = $trunkHeight - 1 - $random->nextRange(0, 3);

		for($yy = 0; $yy <= $firstBranchHeight; ++$yy){
			$transaction->addBlockAt($x, $y + $yy, $z, $this->trunkBlock);
		}

		$mainBranchFacing = Facing::HORIZONTAL[array_rand(Facing::HORIZONTAL)];

		$this->mainBranchTip = $this->placeBranch(
			$transaction,
			new Vector3($x, $y + $firstBranchHeight, $z),
			$mainBranchFacing,
			$random->nextRange(1, 3),
			$trunkHeight - $firstBranchHeight
		);

		$secondBranchFacing = Facing::HORIZONTAL[array_rand(Facing::HORIZONTAL)];
		if($secondBranchFacing !== $mainBranchFacing){
			$secondBranchLength = $random->nextRange(1, 3);
			$this->secondBranchTip = $this->placeBranch(
				$transaction,
				new Vector3($x, $y + ($firstBranchHeight - $random->nextRange(0, 1)), $z),
				$secondBranchFacing,
				$secondBranchLength,
				$secondBranchLength
			);
		}
	}

	protected function placeBranch(BlockTransaction $transaction, Vector3 $start, int $branchFacing, int $maxDiagonal, int $length) : Vector3{
		$diagonalPlaced = 0;

		$nextBlockPos = $start;
		for($yy = 0; $yy < $length; $yy++){
			$nextBlockPos = $nextBlockPos->up();
			if($diagonalPlaced < $maxDiagonal){
				$nextBlockPos = $nextBlockPos->getSide($branchFacing);
				$diagonalPlaced++;
			}
			$transaction->addBlock($nextBlockPos, $this->trunkBlock);
		}

		return $nextBlockPos;
	}

	protected function placeCanopyLayer(BlockTransaction $transaction, Vector3 $center, int $radius, int $maxTaxicabDistance) : void{
		$centerX = $center->getFloorX();
		$centerY = $center->getFloorY();
		$centerZ = $center->getFloorZ();

		for($x = $centerX - $radius; $x <= $centerX + $radius; ++$x){
			for($z = $centerZ - $radius; $z <= $centerZ + $radius; ++$z){
				if(
					abs($x - $centerX) + abs($z - $centerZ) <= $maxTaxicabDistance &&
					$transaction->fetchBlockAt($x, $centerY, $z)->canBeReplaced()
				){
					$transaction->addBlockAt($x, $centerY, $z, $this->leafBlock);
				}
			}
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		$mainBranchTip = $this->mainBranchTip;
		if($mainBranchTip !== null){
			$this->placeCanopyLayer($transaction, $mainBranchTip, radius: 3, maxTaxicabDistance: 5);
			$this->placeCanopyLayer($transaction, $mainBranchTip->up(), radius: 2, maxTaxicabDistance: 2);
		}
		$secondBranchTip = $this->secondBranchTip;
		if($secondBranchTip !== null){
			$this->placeCanopyLayer($transaction, $secondBranchTip, radius: 2, maxTaxicabDistance: 3);
			$this->placeCanopyLayer($transaction, $secondBranchTip->up(), radius: 1, maxTaxicabDistance: 2);
		}
	}
}