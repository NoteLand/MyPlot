<?php

namespace MyPlot\utils;

use pocketmine\math\Facing;
use pocketmine\utils\SingletonTrait;

class DirectionFactory {
	use SingletonTrait;

	/** @var Direction[] $directions */
	private array $directions = [];

	public function __construct() {
		$this->register(new Direction(Facing::NORTH, "north", "south"));
		$this->register(new Direction(Facing::SOUTH, "south", "north"));
		$this->register(new Direction(Facing::EAST, "east", "west"));
		$this->register(new Direction(Facing::WEST, "west", "east"));
	}

	private function register(Direction $direction) : void {
		$this->directions[$direction->getFacing()] = $direction;
	}

	public function getDirection(int $direction) : ?Direction {
		return $this->directions[$direction] ?? null;
	}

	/**
	 * @internal
	 *
	 * @return Direction[]
	 */
	public function getAll() : array {
		return $this->directions;
	}

	public function facingFromString(string $direction) : int {
		$direction = substr($direction, 0, strpos($direction, 'merge'));
		return match ($direction) {
			"north" => Facing::NORTH,
			"south" => Facing::SOUTH,
			"east" => Facing::EAST,
			default => Facing::WEST
		};
	}
}