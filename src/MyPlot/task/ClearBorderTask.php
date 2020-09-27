<?php
declare(strict_types=1);
namespace MyPlot\task;

use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\Position;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;

class ClearBorderTask extends Task {
	/** @var MyPlot $plugin */
	protected $plugin;
	/** @var Plot $plot */
	protected $plot;
	/** @var \pocketmine\world\World|null $level */
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
	/** @var Block $roadBlock */
	protected $roadBlock;
	/** @var Block $groundBlock */
	protected $groundBlock;
	/** @var Block $bottomBlock */
	protected $bottomBlock;

	/**
	 * ClearBorderTask constructor.
	 *
	 * @param MyPlot $plugin
	 * @param Plot $plot
	 */
	public function __construct(MyPlot $plugin, Plot $plot) {
		$this->plugin = $plugin;
		$this->plot = $plot;
		$this->plotBeginPos = $plugin->getPlotPosition($plot);
		$this->level = $this->plotBeginPos->getWorld();
		$this->plotBeginPos = $this->plotBeginPos->subtract(1,0,1);
		$plotLevel = $plugin->getLevelSettings($plot->levelName);
		$plotSize = $plotLevel->plotSize;
		$this->xMax = (int)($this->plotBeginPos->x + $plotSize + 1);
		$this->zMax = (int)($this->plotBeginPos->z + $plotSize + 1);
		$this->height = $plotLevel->groundHeight;
		$this->plotWallBlock = $plotLevel->wallBlock;
		$this->roadBlock = $plotLevel->roadBlock;
		$this->groundBlock = $plotLevel->plotFillBlock;
		$this->bottomBlock = $plotLevel->bottomBlock;
		$plugin->getLogger()->debug("Border Clear Task started at plot {$plot->X};{$plot->Z}");
	}

	public function onRun() : void {
		for($x = $this->plotBeginPos->x; $x <= $this->xMax; $x++) {
			for($y = 0; $y < $this->level->getWorldHeight(); ++$y) {
				if($y > $this->height + 1)
					$block = VanillaBlocks::AIR();
				elseif($y === $this->height + 1)
					$block = $this->plotWallBlock;
				elseif($y === $this->height)
					$block = $this->roadBlock;
				elseif($y === 0)
					$block = $this->bottomBlock;
				else//if($y < $this->height)
					$block = $this->groundBlock;
				$this->level->setBlock(new Vector3($x, $y, $this->plotBeginPos->z), $block, false);
				$this->level->setBlock(new Vector3($x, $y, $this->zMax), $block, false);
			}
		}
		for($z = $this->plotBeginPos->z; $z <= $this->zMax; $z++) {
			for($y = 0; $y < $this->level->getWorldHeight(); ++$y) {
				if($y > $this->height+1)
					$block = VanillaBlocks::AIR();
				elseif($y === $this->height + 1)
					$block = $this->plotWallBlock;
				elseif($y === $this->height)
					$block = $this->roadBlock;
				elseif($y === 0)
					$block = $this->bottomBlock;
				else//if($y < $this->height)
					$block = $this->groundBlock;
				$this->level->setBlock(new Vector3($this->plotBeginPos->x, $y, $z), $block, false);
				$this->level->setBlock(new Vector3($this->xMax, $y, $z), $block, false);
			}
		}
		$this->plugin->getLogger()->debug("Border Clear Task completed");
	}
}