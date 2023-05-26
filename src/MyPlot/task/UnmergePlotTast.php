<?php
declare(strict_types=1);
namespace MyPlot\task;

use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\world\Position;

class UnmergePlotTast extends Task {

	private MyPlot $plugin;
	private int $direction;
	private Position $plotBeginPos;
	private Vector3 $startClearPos;
	private Vector3 $endClearPos;
	private int $startZ;
	protected Block $bottomBlock;
	protected Block $plotFillBlock;
	protected Block $roadBlock;
	protected Block $wallBlock;
	protected int $plotSize;
	protected int $roadSize;
	protected int $height;

	public function __construct(MyPlot $plugin, Plot $plot, int $direction) {
		$this->plugin = $plugin;
		$this->direction = $direction;
		$plotBeginPos = $plugin->getPlotPosition($plot);
		$this->plotBeginPos = $plotBeginPos;
		$plotLevel = $plugin->getLevelSettings($plot->levelName);
		$this->bottomBlock = $plotLevel->bottomBlock;
		$this->plotFillBlock = $plotLevel->plotFillBlock;
		$this->roadBlock = $plotLevel->roadBlock;
		if ($plot->owner === "") {
			$this->wallBlock = $plotLevel->wallBlock;
		} else {
			$plotsquared = new Config($plugin->getDataFolder() . "plotsquaredpm.yml");
			$claimBorder = $plotsquared->get("ClaimBorder", "quartz_slab");
			if (($parsedResult = StringToItemParser::getInstance()->parse($claimBorder)) != null) {
				$this->wallBlock = $parsedResult->getBlock();
			} else {
				$this->wallBlock = $plotLevel->wallBlock;
			}
		}
		$plotSize = $plotLevel->plotSize;
		$this->plotSize = $plotSize;
		$roadSize = $plotLevel->roadWidth;
		$this->roadSize = $roadSize;
		if ($direction === Facing::WEST) {
			$this->startClearPos = new Vector3($plotBeginPos->x - 1, 0, $plotBeginPos->z + $plotSize);
			$this->startZ = $this->startClearPos->z;
			$this->endClearPos = $plotBeginPos->add(-($roadSize + 1), 0, -2);
		} elseif ($direction === Facing::EAST) {
			$this->startClearPos = new Vector3($plotBeginPos->x + $plotSize + $roadSize - 1, 0, $plotBeginPos->z + $plotSize);
			$this->startZ = $this->startClearPos->z;
			$this->endClearPos = $plotBeginPos->add($plotSize - 1, 0, -2);
		} elseif ($direction === Facing::SOUTH) {
			$this->startClearPos = new Vector3($plotBeginPos->x + $plotSize, 0, $plotBeginPos->z + $plotSize + $roadSize - 1);
			$this->startZ = $this->startClearPos->z;
			$this->endClearPos = $plotBeginPos->add(-2, 0, $plotSize - 1);
		} elseif ($direction === Facing::NORTH) {
			$this->startClearPos = new Vector3($plotBeginPos->x + $plotSize, 0, $plotBeginPos->z - 1);
			$this->startZ = $this->startClearPos->z;
			$this->endClearPos = $plotBeginPos->add(-2, 0, -$roadSize - 1);
		} else {
			$this->startClearPos = $plotBeginPos->asVector3();
			$this->startZ = $this->startClearPos->z;
			$this->endClearPos = $plotBeginPos->asVector3();
		}
		$this->height = $plotLevel->groundHeight;
	}

	public function onRun() : void {
		$blocks = 0;
		$world = $this->plotBeginPos->getWorld();
		while($this->startClearPos->x > $this->endClearPos->x) {
			while($this->startClearPos->z > $this->endClearPos->z) {
				while($this->startClearPos->y < $world->getMaxY()) {
					if($this->startClearPos->y === 0) {
						$block = $this->bottomBlock;
					}elseif($this->startClearPos->y < $this->height) {
						$block = $this->plotFillBlock;
					}elseif($this->startClearPos->y === $this->height) {
						$block = $this->roadBlock;
					}else{
						$block = VanillaBlocks::AIR();
					}
					$world->setBlock($this->startClearPos, $block, false);
					$blocks++;
					if($blocks >= 256) {
						$this->setHandler(null);
						$this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						return;
					}
					$this->startClearPos->y++;
				}
				$this->startClearPos->y = 0;
				$this->startClearPos->z--;
			}
			$this->startClearPos->z = $this->startZ;
			$this->startClearPos->x--;
		}
		if ($this->direction === Facing::WEST) {
			$start1 = $this->plotBeginPos->add(-1, 0, $this->plotSize);
			$start2 = $this->plotBeginPos->add(-$this->roadSize, 0, $this->plotSize);
			$end1 = $this->plotBeginPos->add(-1, 0, -1);
			$end2 = $this->plotBeginPos->add(-$this->roadSize, 0, -1);
			for ($z = $start1->z; $z >= $end1->z; $z--) {
				$world->setBlock(new Vector3($start1->x, $this->height + 1, $z), $this->wallBlock, false);
			}
			for ($z = $start2->z; $z >= $end2->z; $z--) {
				$world->setBlock(new Vector3($start2->x, $this->height + 1, $z), $this->wallBlock, false);
			}
		} elseif ($this->direction === Facing::EAST) {
			$start1 = $this->plotBeginPos->add($this->plotSize, 0, $this->plotSize);
			$start2 = $this->plotBeginPos->add($this->plotSize + ($this->roadSize - 1), 0, $this->plotSize);
			$end1 = $this->plotBeginPos->add($this->plotSize, 0, -1);
			$end2 = $this->plotBeginPos->add($this->plotSize + ($this->roadSize - 1), 0, -1);
			for ($z = $start1->z; $z >= $end1->z; $z--) {
				$world->setBlock(new Vector3($start1->x, $this->height + 1, $z), $this->wallBlock, false);
			}
			for ($z = $start2->z; $z >= $end2->z; $z--) {
				$world->setBlock(new Vector3($start2->x, $this->height + 1, $z), $this->wallBlock, false);
			}
		} elseif ($this->direction === Facing::EAST) {
			$start1 = $this->plotBeginPos->add($this->plotSize, 0, $this->plotSize);
			$start2 = $this->plotBeginPos->add($this->plotSize + ($this->roadSize - 1), 0, $this->plotSize);
			$end1 = $this->plotBeginPos->add($this->plotSize, 0, -1);
			$end2 = $this->plotBeginPos->add($this->plotSize + ($this->roadSize - 1), 0, -1);
			for ($z = $start1->z; $z >= $end1->z; $z--) {
				$world->setBlock(new Vector3($start1->x, $this->height + 1, $z), $this->wallBlock, false);
			}
			for ($z = $start2->z; $z >= $end2->z; $z--) {
				$world->setBlock(new Vector3($start2->x, $this->height + 1, $z), $this->wallBlock, false);
			}
		} elseif ($this->direction === Facing::SOUTH) {
			$start1 = $this->plotBeginPos->add($this->plotSize, 0, $this->plotSize);
			$start2 = $this->plotBeginPos->add($this->plotSize, 0, $this->plotSize + ($this->roadSize - 1));
			$end1 = $this->plotBeginPos->add(-1, 0, $this->plotSize);
			$end2 = $this->plotBeginPos->add(-1, 0, $this->plotSize + ($this->roadSize - 1));
			for ($x = $start1->x; $x >= $end1->x; $x--) {
				$world->setBlock(new Vector3($x, $this->height + 1, $start1->z), $this->wallBlock, false);
			}
			for ($x = $start2->x; $x >= $end2->x; $x--) {
				$world->setBlock(new Vector3($x, $this->height + 1, $start2->z), $this->wallBlock, false);
			}
		} elseif ($this->direction === Facing::NORTH) {
			$start1 = $this->plotBeginPos->add($this->plotSize, 0, -1);
			$start2 = $this->plotBeginPos->add($this->plotSize, 0, -$this->roadSize);
			$end1 = $this->plotBeginPos->add(-1, 0, -1);
			$end2 = $this->plotBeginPos->add(-1, 0, -$this->roadSize);
			for ($x = $start1->x; $x >= $end1->x; $x--) {
				$world->setBlock(new Vector3($x, $this->height + 1, $start1->z), $this->wallBlock, false);
			}
			for ($x = $start2->x; $x >= $end2->x; $x--) {
				$world->setBlock(new Vector3($x, $this->height + 1, $start2->z), $this->wallBlock, false);
			}
		}
    }
}
