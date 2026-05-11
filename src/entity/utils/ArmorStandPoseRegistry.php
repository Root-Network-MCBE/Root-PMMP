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

final class ArmorStandPoseRegistry{
	public static function instance() : self{
		static $instance = null;
		return $instance ??= new self();
	}

	/** @var array<string, ArmorStandPose> */
	private array $poses = [];

	/** @var string[] */
	private array $identifiers = [];

	/** @var string[] */
	private array $nexts = [];

	private function __construct(){
		$this->register("default", new SimpleArmorStandPose("Default", 0));
		$this->register("no", new SimpleArmorStandPose("No", 1));
		$this->register("solemn", new SimpleArmorStandPose("Solemn", 2));
		$this->register("athena", new SimpleArmorStandPose("Athena", 3));
		$this->register("brandish", new SimpleArmorStandPose("Brandish", 4));
		$this->register("honor", new SimpleArmorStandPose("Honor", 5));
		$this->register("entertain", new SimpleArmorStandPose("Entertain", 6));
		$this->register("salute", new SimpleArmorStandPose("Salute", 7));
		$this->register("hero", new SimpleArmorStandPose("Hero", 8));
		$this->register("riposte", new SimpleArmorStandPose("Riposte", 9));
		$this->register("zombie", new SimpleArmorStandPose("Zombie", 10));
		$this->register("cancan_a", new SimpleArmorStandPose("CanCan A", 11));
		$this->register("cancan_b", new SimpleArmorStandPose("CanCan B", 12));
	}

	public function register(string $identifier, ArmorStandPose $pose) : void{
		$last = array_key_last($this->identifiers);
		if($last !== null){
			$this->nexts[$last] = $identifier;
		}

		$this->poses[$identifier] = $pose;
		$this->identifiers[spl_object_id($pose)] = $identifier;
	}

	public function get(string $identifier) : ArmorStandPose{
		return $this->poses[$identifier];
	}

	public function getIdentifier(ArmorStandPose $pose) : string{
		return $this->identifiers[spl_object_id($pose)];
	}

	/**
	 * @return array<string, ArmorStandPose>
	 */
	public function getAll() : array{
		return $this->poses;
	}

	public function default() : ArmorStandPose{
		return $this->poses["default"];
	}

	public function next(ArmorStandPose $pose) : ArmorStandPose{
		return isset($this->nexts[$id = spl_object_id($pose)]) ? $this->poses[$this->nexts[$id]] : $this->default();
	}
}