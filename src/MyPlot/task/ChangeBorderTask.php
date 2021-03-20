<?php


namespace MyPlot\task;


use MyPlot\MyPlot;
use MyPlot\Plot;
use MyPlot\utils\Border;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class ChangeBorderTask extends Task
{

    private $plot, $level, $height, $plotWallBlock, $plotBeginPos, $xMax, $zMax;

    public function __construct(Plot $plot, Border $border, Player $player = null) {
        $this->plot = $plot;
        $this->plotBeginPos = MyPlot::getInstance()->getPlotPosition($plot);
        $this->level = $this->plotBeginPos->getLevel();
        $this->plotBeginPos = $this->plotBeginPos->subtract(1,0,1);
        $plotLevel = MyPlot::getInstance()->getLevelSettings($plot->levelName);
        $plotSize = $plotLevel->plotSize;
        $this->xMax = $this->plotBeginPos->x + $plotSize + 1;
        $this->zMax = $this->plotBeginPos->z + $plotSize + 1;
        $this->height = $plotLevel->groundHeight;
        $this->plotWallBlock = $border->getBlock();
    }

    public function onRun(int $currentTick) : void {
        for($x = $this->plotBeginPos->x; $x <= $this->xMax; $x++) {
            $this->level->setBlock(new Vector3($x, $this->height + 1, $this->plotBeginPos->z), $this->plotWallBlock, false, false);
            $this->level->setBlock(new Vector3($x, $this->height + 1, $this->zMax), $this->plotWallBlock, false, false);
        }
        for($z = $this->plotBeginPos->z; $z <= $this->zMax; $z++) {
            $this->level->setBlock(new Vector3($this->plotBeginPos->x, $this->height + 1, $z), $this->plotWallBlock, false, false);
            $this->level->setBlock(new Vector3($this->xMax, $this->height + 1, $z), $this->plotWallBlock, false, false);
        }
    }
}