<?php
declare(strict_types=1);

namespace MyPlot\task;

use Exception;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class CornerCorrectionTask extends Task {

	/** @var MyPlot $plugin */
	protected $plugin;
	/** @var Plot $plot */
	protected $plot;
	/** @var Level $level */
	protected $level;
	/** @var int $height */
	protected $height;
	/** @var Block $plotWallBlock */
	protected $plotWallBlock;
	/** @var int $maxBlocksPerTick */
	protected $maxBlocksPerTick;
	/** @var Vector3 $plotBeginPos */
	protected $plotBeginPos;
	/** @var int $xMax */
	protected $xMax;
	/** @var int $zMax */
	protected $zMax;
	/** @var int $direction */
	protected $direction;
	/** @var Block $roadBlock */
	protected $roadBlock;
	/** @var Block $groundBlock */
	protected $groundBlock;
	/** @var Block $bottomBlock */
	protected $bottomBlock;
	/** @var Vector3 $pos */
	protected $pos;
	/** @var AxisAlignedBB $mergeBB */
	protected $mergeBB;

	public function __construct(MyPlot $plugin, Plot $start, Plot $end, int $cornerDirection, int $maxBlocksPerTick = 256) {
		if($start->isSame($end)) {
			throw new Exception("Invalid Plot arguments");
		}

		$this->plugin = $plugin;
		$this->plot = $start;
		$this->plotBeginPos = $plugin->getPlotPosition($start, false);
		$this->level = $this->plotBeginPos->getLevelNonNull();

		$plotLevel = $plugin->getLevelSettings($start->levelName);
		$plotSize = $plotLevel->plotSize;
		$roadWidth = $plotLevel->roadWidth;
		$this->xMax = (int)($this->plotBeginPos->x + $plotSize + 1);
		$this->zMax = (int)($this->plotBeginPos->z + $plotSize + 1);

		$this->mergeBB = new AxisAlignedBB(min($this->plotBeginPos->x, $this->plotBeginPos->x + $plotSize + $roadWidth), 0, min($this->plotBeginPos->z, $this->plotBeginPos->z + $plotSize + $roadWidth), max($this->plotBeginPos->x, $this->plotBeginPos->x + $plotSize + $roadWidth), $this->plotBeginPos->getLevelNonNull()->getWorldHeight(), max($this->plotBeginPos->z, $this->plotBeginPos->z + $plotSize + $roadWidth));

		if(($start->Z - $end->Z) === 1) { // North Z-
			if($cornerDirection === Vector3::SIDE_EAST) {
				$this->plotBeginPos = $this->plotBeginPos->subtract(-$plotSize, 0, $roadWidth);
			}elseif($cornerDirection === Vector3::SIDE_WEST) {
				$this->plotBeginPos = $this->plotBeginPos->subtract($roadWidth, 0, $roadWidth);
			}
		}elseif(($start->X - $end->X) === -1) { // East X+
			if($cornerDirection === Vector3::SIDE_NORTH) {
				$this->plotBeginPos = $this->plotBeginPos->subtract(-$plotSize, 0, $roadWidth);
			}elseif($cornerDirection === Vector3::SIDE_SOUTH) {
				$this->plotBeginPos = $this->plotBeginPos->add($plotSize, 0, $plotSize);
			}
		}elseif(($start->Z - $end->Z) === -1) { // South Z+
			if($cornerDirection === Vector3::SIDE_EAST) {
				$this->plotBeginPos = $this->plotBeginPos->add($plotSize * 2, 0, $plotSize);
			}elseif($cornerDirection === Vector3::SIDE_WEST) {
				$this->plotBeginPos = $this->plotBeginPos->add(-$roadWidth, 0, $plotSize);
			}
		}elseif(($start->X - $end->X) === 1) { // West X-
			if($cornerDirection === Vector3::SIDE_NORTH) {
				$this->plotBeginPos = $this->plotBeginPos->subtract($roadWidth, 0, $roadWidth);
			}elseif($cornerDirection === Vector3::SIDE_SOUTH) {
				$this->plotBeginPos = $this->plotBeginPos->add(-$roadWidth, 0, $plotSize);
			}
		}

		$this->height = $plotLevel->groundHeight;
		$this->plotWallBlock = $plotLevel->wallBlock;
		$this->roadBlock = $plotLevel->plotFloorBlock;
		$this->groundBlock = $plotLevel->plotFillBlock;
		$this->bottomBlock = $plotLevel->bottomBlock;

		$this->maxBlocksPerTick = $maxBlocksPerTick;
		$this->pos = new Vector3($this->plotBeginPos->x, 0, $this->plotBeginPos->z);

		$plugin->getLogger()->debug("Corner Correction Task started between plots {$start->X};{$start->Z} and {$end->X};{$end->Z}");
	}

	public function onRun(int $currentTick) : void {
		foreach($this->level->getEntities() as $entity) {
			if($entity->x > $this->plotBeginPos->x - 1 and $entity->x < $this->xMax + 1) {
				if($entity->z > $this->plotBeginPos->z - 1 and $entity->z < $this->zMax + 1) {
					if(!$entity instanceof Player) {
						$entity->flagForDespawn();
					}else {
						$this->plugin->teleportPlayerToPlot($entity, $this->plot);
					}
				}
			}
		}
		$blocks = 0;
		while($this->pos->x < $this->xMax) {
			while($this->pos->z < $this->zMax) {
				while($this->pos->y < $this->level->getWorldHeight()) {
					if($this->pos->y === 0) {
						$block = $this->bottomBlock;
					}elseif($this->pos->y < $this->height) {
						$block = $this->groundBlock;
					}elseif($this->pos->y === $this->height) {
						$block = $this->roadBlock;
					}else {
						$block = Block::get(Block::AIR);
					}
					$this->level->setBlock($this->pos, $block, false, false);
					$blocks++;
					if($blocks >= $this->maxBlocksPerTick) {
						$this->setHandler();
						$this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						return;
					}
					$this->pos->y++;
				}
				$this->pos->y = 0;
				$this->pos->z++;
			}
			$this->pos->z = $this->plotBeginPos->z;
			$this->pos->x++;
		}
		$this->plugin->getLogger()->debug("Corner Correction Task completed");
	}
}