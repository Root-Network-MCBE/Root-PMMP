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

namespace pocketmine\block;

use pocketmine\block\utils\SulfurSpikeThickness;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class SulfurSpike extends Flowable{
	private SulfurSpikeThickness $thickness = SulfurSpikeThickness::TIP;
	private bool $hanging = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->enum($this->thickness);
		$w->bool($this->hanging);
	}

	public function getThickness() : SulfurSpikeThickness{
		return $this->thickness;
	}

	/** @return $this */
	public function setThickness(SulfurSpikeThickness $thickness) : self{
		$this->thickness = $thickness;
		return $this;
	}

	public function isHanging() : bool{
		return $this->hanging;
	}

	/** @return $this */
	public function setHanging(bool $hanging) : self{
		$this->hanging = $hanging;
		return $this;
	}

	private function canBeSupportedAt(Block $block, bool $hanging) : bool{
		$supportFace = $hanging ? Facing::UP : Facing::DOWN;
		$support = $block->getSide($supportFace);

		return (
			$support instanceof self &&
			$support->isHanging() === $hanging
		) || $block->getAdjacentSupportType($supportFace)->hasCenterSupport();
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return parent::canBePlacedAt($blockReplace, $clickVector, $face, $isClickedBlock) && match($face){
			Facing::DOWN => $this->canBeSupportedAt($blockReplace, true),
			Facing::UP => $this->canBeSupportedAt($blockReplace, false),
			default => $this->canBeSupportedAt($blockReplace, true) || $this->canBeSupportedAt($blockReplace, false)
		};
	}

	private function getTipFacing() : int{
		return $this->hanging ? Facing::DOWN : Facing::UP;
	}

	private function getRootFacing() : int{
		return Facing::opposite($this->getTipFacing());
	}

	private function calculateThicknessFromNeighbors(Block $rootNeighbor, Block $tipNeighbor) : SulfurSpikeThickness{
		if($tipNeighbor instanceof self){
			if($tipNeighbor->isHanging() !== $this->hanging){
				return SulfurSpikeThickness::MERGE;
			}

			if(!$rootNeighbor instanceof self || $rootNeighbor->isHanging() !== $this->hanging){
				return SulfurSpikeThickness::BASE;
			}

			return $tipNeighbor->getThickness() === SulfurSpikeThickness::TIP || $tipNeighbor->getThickness() === SulfurSpikeThickness::MERGE ?
				SulfurSpikeThickness::FRUSTUM :
				SulfurSpikeThickness::MIDDLE;
		}

		return SulfurSpikeThickness::TIP;
	}

	private function calculateThickness(Block $block) : SulfurSpikeThickness{
		return $this->calculateThicknessFromNeighbors($block->getSide($this->getRootFacing()), $block->getSide($this->getTipFacing()));
	}

	private function recalculateThickness() : void{
		$this->thickness = $this->calculateThickness($this);
	}

	private function addNeighborThicknessUpdates(BlockTransaction $tx, Block $blockReplace) : void{
		$rootNeighbor = $blockReplace->getSide($this->getRootFacing());
		if($rootNeighbor instanceof self && $rootNeighbor->isHanging() === $this->hanging){
			$tx->addBlock(
				$rootNeighbor->getPosition(),
				(clone $rootNeighbor)->setThickness(
					$rootNeighbor->calculateThicknessFromNeighbors($rootNeighbor->getSide($rootNeighbor->getRootFacing()), $this)
				)
			);
		}

		$tipNeighbor = $blockReplace->getSide($this->getTipFacing());
		if($tipNeighbor instanceof self && $tipNeighbor->isHanging() === $this->hanging){
			$tx->addBlock(
				$tipNeighbor->getPosition(),
				(clone $tipNeighbor)->setThickness(
					$tipNeighbor->calculateThicknessFromNeighbors($this, $tipNeighbor->getSide($tipNeighbor->getTipFacing()))
				)
			);
		}
	}

	private function tryPlace(BlockTransaction $tx, Block $blockReplace, bool $hanging) : bool{
		$this->hanging = $hanging;
		if(!$this->canBeSupportedAt($blockReplace, $hanging)){
			return false;
		}

		$this->thickness = $this->calculateThickness($blockReplace);
		$tipNeighbor = $blockReplace->getSide($this->getTipFacing());
		if($tipNeighbor instanceof self && $tipNeighbor->isHanging() !== $this->hanging){
			$tx->addBlock($tipNeighbor->getPosition(), (clone $tipNeighbor)->setThickness(SulfurSpikeThickness::MERGE));
		}
		$this->addNeighborThicknessUpdates($tx, $blockReplace);

		$tx->addBlock($blockReplace->getPosition(), $this);
		return true;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		return match($face){
			Facing::DOWN => $this->tryPlace($tx, $blockReplace, true),
			Facing::UP => $this->tryPlace($tx, $blockReplace, false),
			default => $clickVector->y > 0.5 ?
				($this->tryPlace($tx, $blockReplace, true) || $this->tryPlace($tx, $blockReplace, false)) :
				($this->tryPlace($tx, $blockReplace, false) || $this->tryPlace($tx, $blockReplace, true))
		};
	}

	public function onNearbyBlockChange() : void{
		if(!$this->canBeSupportedAt($this, $this->hanging)){
			$this->position->getWorld()->useBreakOn($this->position);
			return;
		}

		$oldThickness = $this->thickness;
		$this->recalculateThickness();
		if($this->thickness !== $oldThickness){
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}
}
