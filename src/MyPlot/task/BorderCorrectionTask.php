<?php
declare(strict_types=1);

namespace MyPlot\task;


use Exception;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIds;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;

class BorderCorrectionTask extends Task {
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
	/** @var Position|Vector3|null $plotBeginPos */
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
	/** @var Plot $endPlot */
	protected $endPlot;
	/** @var bool $fillCorner */
	protected $fillCorner;
	/** @var int $cornerDirection */
	protected $cornerDirection;
	/** @var AxisAlignedBB $mergeBB */
	protected $mergeBB;
	/** @var int $maxBlocksPerTick */
	protected $maxBlocksPerTick;
	/** @var Vector3 $pos */
	protected $pos;

	//private $roadCounts, $plots, $level, $height, $bottomBlock, $plotFillBlock, $plotFloorBlock, $plotBeginPos, $xMax, $zMax, $maxBlocksPerTick;

	/**
	 * PlotMergeTask constructor.
	 *
	 * @param MyPlot $plugin
	 * @param Plot $start
	 * @param Plot $end
	 * @param bool $fillCorner
	 * @param int $cornerDirection
	 * @param int $maxBlocksPerTick
	 *
	 * @throws Exception
	 */
	public function __construct(MyPlot $plugin, Plot $start, Plot $end, bool $fillCorner = false, int $cornerDirection = -1, int $maxBlocksPerTick = 256) {
		if($start->isSame($end, false)) {
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
		$this->endPlot = $end;
		$this->fillCorner = $fillCorner;
		$this->cornerDirection = $cornerDirection;

		$this->mergeBB = new AxisAlignedBB(min($this->plotBeginPos->x, $this->plotBeginPos->x + $plotSize + $roadWidth), 0, min($this->plotBeginPos->z, $this->plotBeginPos->z + $plotSize + $roadWidth), max($this->plotBeginPos->x, $this->plotBeginPos->x + $plotSize + $roadWidth), $this->plotBeginPos->getLevelNonNull()->getWorldHeight(), max($this->plotBeginPos->z, $this->plotBeginPos->z + $plotSize + $roadWidth));

		if(($start->Z - $end->Z) === 1) { // North Z-
			$this->plotBeginPos = $this->plotBeginPos->subtract(0, 0, $roadWidth);
			$this->xMax = (int)($this->plotBeginPos->x + $plotSize);
			$this->zMax = (int)($this->plotBeginPos->z + $roadWidth);
			$this->direction = Vector3::SIDE_NORTH;
		}elseif(($start->X - $end->X) === -1) { // East X+
			$this->plotBeginPos = $this->plotBeginPos->add($plotSize);
			$this->xMax = (int)($this->plotBeginPos->x + $roadWidth);
			$this->zMax = (int)($this->plotBeginPos->z + $plotSize);
			$this->direction = Vector3::SIDE_EAST;
		}elseif(($start->Z - $end->Z) === -1) { // South Z+
			$this->plotBeginPos = $this->plotBeginPos->add(0, 0, $plotSize);
			$this->xMax = (int)($this->plotBeginPos->x + $plotSize);
			$this->zMax = (int)($this->plotBeginPos->z + $roadWidth);
			$this->direction = Vector3::SIDE_SOUTH;
		}elseif(($start->X - $end->X) === 1) { // West X-
			$this->plotBeginPos = $this->plotBeginPos->subtract($roadWidth);
			$this->xMax = (int)($this->plotBeginPos->x + $roadWidth);
			$this->zMax = (int)($this->plotBeginPos->z + $plotSize);
			$this->direction = Vector3::SIDE_WEST;
		}

		$this->height = $plotLevel->groundHeight;
		$this->plotWallBlock = $plotLevel->wallBlock;
		$this->roadBlock = $plotLevel->plotFloorBlock;
		$this->groundBlock = $plotLevel->plotFillBlock;
		$this->bottomBlock = $plotLevel->bottomBlock;

		$this->maxBlocksPerTick = $maxBlocksPerTick;
		$this->pos = new Vector3($this->plotBeginPos->x, 0, $this->plotBeginPos->z);

		$plugin->getLogger()->debug("Border Correction Task started between plots {$start->X};{$start->Z} and {$end->X};{$end->Z}");
	}

	public function onRun(int $currentTick) : void {
		$blocks = 0;
		if($this->direction === Vector3::SIDE_NORTH or $this->direction === Vector3::SIDE_SOUTH) {
			for(; $this->pos->z < $this->zMax; ++$this->pos->z) {
				for(; $this->pos->y < $this->level->getWorldHeight(); ++$this->pos->y) {
					if($this->pos->y > $this->height + 1) {
						$block = BlockFactory::get(BlockIds::AIR);
					}elseif($this->pos->y === $this->height + 1) {
						// TODO: change by x/z coords
						$block = $this->plotWallBlock;
					}elseif($this->pos->y === $this->height) {
						$block = $this->roadBlock;
					}elseif($this->pos->y === 0) {
						$block = $this->bottomBlock;
					}else//if($y < $this->height)
					{
						$block = $this->groundBlock;
					}
					$this->level->setBlock(new Vector3($this->pos->x - 1, $this->pos->y, $this->pos->z), $block, false, false);
					$this->level->setBlock(new Vector3($this->xMax, $this->pos->y, $this->pos->z), $block, false, false);
					$blocks += 2;
					if($blocks >= $this->maxBlocksPerTick) {
						$this->setHandler();
						$this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						return;
					}
				}
			}
		}else {
			for(; $this->pos->x < $this->xMax; ++$this->pos->x) {
				for(; $this->pos->y < $this->level->getWorldHeight(); ++$this->pos->y) {
					if($this->pos->y > $this->height + 1) {
						$block = BlockFactory::get(BlockIds::AIR);
					}elseif($this->pos->y === $this->height + 1) {
						// TODO: change by z/x coords
						$block = $this->plotWallBlock;
					}elseif($this->pos->y === $this->height) {
						$block = $this->roadBlock;
					}elseif($this->pos->y === 0) {
						$block = $this->bottomBlock;
					}else//if($y < $this->height)
					{
						$block = $this->groundBlock;
					}
					$this->level->setBlock(new Vector3($this->pos->x, $this->pos->y, $this->pos->z - 1), $block, false, false);
					$this->level->setBlock(new Vector3($this->pos->x, $this->pos->y, $this->zMax), $block, false, false);
					$blocks += 2;
					if($blocks >= $this->maxBlocksPerTick) {
						$this->setHandler();
						$this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						return;
					}
				}
			}
		}
		$this->plugin->getLogger()->debug("Border Correction Task completed");
		if($this->fillCorner)
			$this->plugin->getScheduler()->scheduleDelayedTask(new CornerCorrectionTask($this->plugin, $this->plot, $this->endPlot, $this->cornerDirection), 10);

	}
}