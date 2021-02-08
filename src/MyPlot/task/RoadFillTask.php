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

class RoadFillTask extends Task {
	/** @var MyPlot $plugin */
	protected $plugin;
	/** @var Plot $plot */
	protected $plot;
	/** @var Plot $endPlot */
	protected $endPlot;
	/** @var Level $level */
	protected $level;
	/** @var int $height */
	protected $height;
	/** @var Block $plotWallBlock */
	protected $plotWallBlock;
	/** @var Vector3 $plotBeginPos */
	protected $plotBeginPos;
	/** @var int $xMax */
	protected $xMax;
	/** @var int $zMax */
	protected $zMax;
	/** @var Block $roadBlock */
	protected $roadBlock;
	/** @var Block $groundBlock */
	protected $groundBlock;
	/** @var Block $bottomBlock */
	protected $bottomBlock;
	/** @var int $maxBlocksPerTick */
	protected $maxBlocksPerTick;
	/** @var Vector3 $pos */
	protected $pos;
	/** @var bool $fillDot */
	protected $fillDot;
	/** @var int $dotDirection */
	protected $dotDirection = -1;
	/** @var AxisAlignedBB $mergeBB */
	protected $mergeBB;

	//private $roadCounts, $plots, $level, $height, $bottomBlock, $plotFillBlock, $plotFloorBlock, $plotBeginPos, $xMax, $zMax, $maxBlocksPerTick;

	/**
	 * PlotMergeTask constructor.
	 *
	 * @param MyPlot $plugin
	 * @param Plot $start
	 * @param Plot $end
	 * @param bool $fillDot
	 * @param int $direction
	 * @param int $maxBlocksPerTick
	 */
	public function __construct(MyPlot $plugin, Plot $start, Plot $end, bool $fillDot = false, int $direction = -1, int $maxBlocksPerTick = 256) {
		if($start->isSame($end, false)) {
			throw new Exception("Invalid Plot arguments");
		}

		$this->plugin = $plugin;
		$this->plot = $start;
		$this->endPlot = $end;
		$this->plotBeginPos = $plugin->getPlotPosition($start);
		$this->level = $this->plotBeginPos->getLevelNonNull();
		$this->fillDot = $fillDot;

		$plotLevel = $plugin->getLevelSettings($start->levelName);
		$plotSize = $plotLevel->plotSize;
		$roadWidth = $plotLevel->roadWidth;

		$this->mergeBB = new AxisAlignedBB(min($this->plotBeginPos->x, $this->plotBeginPos->x + $plotSize + $roadWidth), 0, min($this->plotBeginPos->z, $this->plotBeginPos->z + $plotSize + $roadWidth), max($this->plotBeginPos->x, $this->plotBeginPos->x + $plotSize + $roadWidth), $this->plotBeginPos->getLevelNonNull()->getWorldHeight(), max($this->plotBeginPos->z, $this->plotBeginPos->z + $plotSize + $roadWidth));

		if(($start->Z - $end->Z) === 1) { // North Z-
			$this->plotBeginPos = $this->plotBeginPos->subtract(0, 0, $roadWidth);
			$this->xMax = (int)($this->plotBeginPos->x + $plotSize);
			$this->zMax = (int)($this->plotBeginPos->z + $roadWidth);
			if($fillDot) {
				if($direction === Vector3::SIDE_EAST) {
					$this->dotDirection = Vector3::SIDE_WEST;
				}elseif($direction === Vector3::SIDE_WEST) {
					$this->dotDirection = Vector3::SIDE_EAST;
				}
			}
		}elseif(($start->X - $end->X) === -1) { // East X+
			$this->plotBeginPos = $this->plotBeginPos->add($plotSize);
			$this->xMax = (int)($this->plotBeginPos->x + $roadWidth);
			$this->zMax = (int)($this->plotBeginPos->z + $plotSize);
			if($fillDot) {
				if($direction === Vector3::SIDE_NORTH) {
					$this->dotDirection = Vector3::SIDE_SOUTH;
				}elseif($direction === Vector3::SIDE_SOUTH) {
					$this->dotDirection = Vector3::SIDE_NORTH;
				}
			}
		}elseif(($start->Z - $end->Z) === -1) { // South Z+
			$this->plotBeginPos = $this->plotBeginPos->add(0, 0, $plotSize);
			$this->xMax = (int)($this->plotBeginPos->x + $plotSize);
			$this->zMax = (int)($this->plotBeginPos->z + $roadWidth);
			if($fillDot) {
				if($direction === Vector3::SIDE_EAST) {
					$this->dotDirection = Vector3::SIDE_WEST;
				}elseif($direction === Vector3::SIDE_WEST) {
					$this->dotDirection = Vector3::SIDE_EAST;
				}
			}
		}elseif(($start->X - $end->X) === 1) { // West X-
			$this->plotBeginPos = $this->plotBeginPos->subtract($roadWidth);
			$this->xMax = (int)($this->plotBeginPos->x + $roadWidth);
			$this->zMax = (int)($this->plotBeginPos->z + $plotSize);
			if($fillDot) {
				if($direction === Vector3::SIDE_NORTH) {
					$this->dotDirection = Vector3::SIDE_SOUTH;
				}elseif($direction === Vector3::SIDE_SOUTH) {
					$this->dotDirection = Vector3::SIDE_NORTH;
				}
			}
		}

		$this->height = $plotLevel->groundHeight;
		$this->roadBlock = $plotLevel->plotFloorBlock;
		$this->groundBlock = $plotLevel->plotFillBlock;
		$this->bottomBlock = $plotLevel->bottomBlock;

		$this->maxBlocksPerTick = $maxBlocksPerTick;
		$this->pos = new Vector3($this->plotBeginPos->x, 0, $this->plotBeginPos->z);

		$plugin->getLogger()->debug("Road Clear Task started between plots {$start->X};{$start->Z} and {$end->X};{$end->Z}");
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
		$this->plugin->getLogger()->debug("Plot Road Clear task completed at {$this->plot->X};{$this->plot->Z}");
		$this->plugin->getScheduler()->scheduleTask(new BorderCorrectionTask($this->plugin, $this->plot, $this->endPlot, $this->fillDot, $this->dotDirection));
	}
}