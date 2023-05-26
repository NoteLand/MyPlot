<?php
declare(strict_types=1);
namespace MyPlot\task;

use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\world\World;

class MergePlotTast extends Task {

	private MyPlot $plugin;
	private string $direction;
	private World $level;
	private int $height;
	private Block $plotWallBlock;
	private Position $plotBeginPos;
	private int $xMax;
	private int $zMax;
	private int $roadway;
	private int $xMax2;
	private int $xMax3;
	private int $zMax3;
	private int $zMax4;
	private Block $plotFloorBlock;
	private int $maxBlocksPerTick;
	private Vector3 $pos;
	private Vector3 $pos2;
	private Vector3 $pos3;
	private Vector3 $pos4;

	/**
	 * MergePlotTask constructor.
	 *
	 * @param MyPlot $plugin
	 * @param Plot $plot
	 * @param string $direction
	 */
	public function __construct(MyPlot $plugin, Plot $plot, string $direction) {
		$this->plugin = $plugin;
		$this->direction = $direction;
		$this->plotBeginPos = $plugin->getPlotPosition($plot);
		$this->level = $this->plotBeginPos->getWorld();
		$plotLevel = $plugin->getLevelSettings($plot->levelName);
		$plotSize = $plotLevel->plotSize;
		$this->roadway = $plotLevel->plotSize;
		$roadSize = $plotLevel->roadWidth;
		$this->xMax = $this->plotBeginPos->x - $roadSize - 1;
		$this->xMax2 = $this->plotBeginPos->x + $roadSize + $plotSize;
		$this->xMax3 = $this->plotBeginPos->x + $plotSize;
		$this->zMax = $this->plotBeginPos->z + $plotSize;
		$this->zMax3 = $this->plotBeginPos->z - $roadSize - 1;
		$this->zMax4 = $this->plotBeginPos->z + $roadSize + $plotSize;
		$this->height = $plotLevel->groundHeight;
		$this->plotFloorBlock = $plotLevel->plotFloorBlock;
		$this->maxBlocksPerTick = 7;
		$this->pos = new Vector3($this->plotBeginPos->x - 1, $this->height, $this->plotBeginPos->z);
		$this->pos2 = new Vector3($this->plotBeginPos->x + $plotSize, $this->height, $this->plotBeginPos->z);
		$this->pos3 = new Vector3($this->plotBeginPos->x, $this->height, $this->plotBeginPos->z - 1);
		$this->pos4 = new Vector3($this->plotBeginPos->x, $this->height, $this->plotBeginPos->z + $plotSize);
		$this->plugin = $plugin;
		if ($plot->owner === "") {
			$this->plotWallBlock = $plotLevel->wallBlock;
		} else {
			$plotsquared = new Config($plugin->getDataFolder() . "plotsquaredpm.yml");
			$claimBorder = $plotsquared->get("ClaimBorder", "quartz_slab");
			if (($parsedResult = StringToItemParser::getInstance()->parse($claimBorder)) != null) {
				$this->plotWallBlock = $parsedResult->getBlock();
			} else {
				$this->plotWallBlock = $plotLevel->wallBlock;
			}
		}
	}

	public function onRun() : void {
		$blocks = 0;
	    if ($this->direction == "west") {
			$roadpos = new Vector3($this->plotBeginPos->x - 1, $this->height + 1, $this->plotBeginPos->z - 1);
			$roadpos2 = new Vector3($this->plotBeginPos->x - 1, $this->height + 1, $this->zMax);
			$block = $this->plotWallBlock;
			for ($x = $roadpos->x; $x > $this->xMax; $x--) {
				$this->level->setBlock(new Vector3($x, $this->height + 1, $roadpos->z), $block, false);
			}
			for ($x = $roadpos2->x; $x > $this->xMax; $x--) {
				$this->level->setBlock(new Vector3($x, $this->height + 1, $roadpos2->z), $block, false);
			}
		    while ($this->pos->x > $this->xMax) {
			    while ($this->pos->z < $this->zMax) {
					while ($this->pos->y < ($this->height + 2)) {
					    if ($this->pos->y === $this->height) {
							$block = $this->plotFloorBlock;
						} else {
							$block = VanillaBlocks::AIR();
						}
					    $this->level->setBlock($this->pos, $block, false);
					    $blocks++;
					    if ($blocks >= $this->maxBlocksPerTick) {
							$this->setHandler(null);
						    $this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						    return;
					    }
					    $this->pos->y++;
				    }
				    $this->pos->y = $this->height;
				    $this->pos->z++;
			    }
			    $this->pos->z = $this->plotBeginPos->z - 1;
			    $this->pos->x--;
		    }
		} elseif ($this->direction == "east") {
			$roadpos = new Vector3($this->plotBeginPos->x + $this->roadway, $this->height + 1, $this->plotBeginPos->z - 1);
			$roadpos2 = new Vector3($this->plotBeginPos->x + $this->roadway, $this->height + 1, $this->zMax);
			$block = $this->plotWallBlock;
			for ($x = $roadpos->x; $x < $this->xMax2; $x++) {
				$this->level->setBlock(new Vector3($x, $this->height + 1, $roadpos->z), $block, false);
			}
			for ($x = $roadpos2->x; $x < $this->xMax2; $x++) {
				$this->level->setBlock(new Vector3($x, $this->height + 1, $roadpos2->z), $block, false);
			}
		    while ($this->pos2->x < $this->xMax2) {
			    while ($this->pos2->z < $this->zMax) {
					while ($this->pos2->y < ($this->height + 2)) {
					    #$block = $this->plotFloorBlock;
					    if ($this->pos2->y === $this->height) {
							$block = $this->plotFloorBlock;
						} else {
							$block = VanillaBlocks::AIR();
						}
					    $this->level->setBlock($this->pos2, $block, false);
					    $blocks++;
					    if ($blocks >= $this->maxBlocksPerTick) {
							$this->setHandler(null);
						    $this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						    return;
					    }
					    $this->pos2->y++;
				    }
				    $this->pos2->y = $this->height;
				    $this->pos2->z++;
			    }
			    $this->pos2->z = $this->plotBeginPos->z;
			    $this->pos2->x++;
		    }
		} elseif ($this->direction == "north") {
			$roadpos = new Vector3($this->plotBeginPos->x - 1, $this->height + 1, $this->plotBeginPos->z - 1);
			$roadpos2 = new Vector3($this->plotBeginPos->x + $this->roadway, $this->height + 1, $this->plotBeginPos->z - 1);
			$block = $this->plotWallBlock;
			for ($z = $roadpos->z; $z > $this->zMax3; $z--) {
				$this->level->setBlock(new Vector3($roadpos->x, $this->height + 1, $z), $block, false);
			}
			for ($z = $roadpos2->z; $z > $this->zMax3; $z--) {
				$this->level->setBlock(new Vector3($roadpos2->x, $this->height + 1, $z), $block, false);
			}
		    while ($this->pos3->x < $this->xMax3) {
			    while ($this->pos3->z > $this->zMax3) {
					while ($this->pos3->y < ($this->height + 2)) {
					    if ($this->pos3->y === $this->height) {
							$block = $this->plotFloorBlock;
						} else {
							$block = VanillaBlocks::AIR();
						}
					    $this->level->setBlock($this->pos3, $block, false);
					    $blocks++;
					    if ($blocks >= $this->maxBlocksPerTick) {
							$this->setHandler(null);
						    $this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						    return;
					    }
					    $this->pos3->y++;
				    }
				    $this->pos3->y = $this->height;
				    $this->pos3->z--;
			    }
			    $this->pos3->z = $this->plotBeginPos->z - 1;
			    $this->pos3->x++;
		    }
		} elseif ($this->direction == "south") {
			$roadpos = new Vector3($this->plotBeginPos->x - 1, $this->height + 1, $this->plotBeginPos->z + $this->roadway);
			$roadpos2 = new Vector3($this->plotBeginPos->x + $this->roadway, $this->height + 1, $this->plotBeginPos->z + $this->roadway);
			$block = $this->plotWallBlock;
			for ($z = $roadpos->z; $z < $this->zMax4; $z++) {
				$this->level->setBlock(new Vector3($roadpos->x, $this->height + 1, $z), $block, false);
			}
			for ($z = $roadpos2->z; $z < $this->zMax4; $z++) {
				$this->level->setBlock(new Vector3($roadpos2->x, $this->height + 1, $z), $block, false);
			}
		    while ($this->pos4->x < $this->xMax3) {
			    while ($this->pos4->z < $this->zMax4) {
					while ($this->pos4->y < ($this->height + 2)) {
					    if ($this->pos4->y === $this->height) {
							$block = $this->plotFloorBlock;
						} else {
							$block = VanillaBlocks::AIR();
						}
					    $this->level->setBlock($this->pos4, $block, false);
					    $blocks++;
					    if ($blocks >= $this->maxBlocksPerTick) {
							$this->setHandler(null);
						    $this->plugin->getScheduler()->scheduleDelayedTask($this, 1);
						    return;
					    }
					    $this->pos4->y++;
				    }
				    $this->pos4->y = $this->height;
				    $this->pos4->z++;
			    }
			    $this->pos4->z = $this->plotBeginPos->z + $this->roadway;
			    $this->pos4->x++;
		    }
		}
    }
}
