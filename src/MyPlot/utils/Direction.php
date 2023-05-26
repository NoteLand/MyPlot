<?php

namespace MyPlot\utils;

class Direction {

	private int $facing;
	private string $directionName;
	private string $oppositeDirectionName;

	public function __construct(int $facing, string $directionName, string $oppositeDirectionName) {
		$this->facing = $facing;
		$this->directionName = $directionName;
		$this->oppositeDirectionName = $oppositeDirectionName;
	}

	public function getFacing() : int {
		return $this->facing;
	}

	public function getDirectionName() : string {
		return $this->directionName;
	}

	public function getOppositeDirectionName() : string {
		return $this->oppositeDirectionName;
	}
}