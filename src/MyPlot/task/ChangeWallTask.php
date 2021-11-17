<?php


namespace MyPlot\task;


use MyPlot\MyPlot;
use MyPlot\Plot;
use MyPlot\utils\Wall;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\world\World;

class ChangeWallTask extends Task
{
    private Plot $plot;
    private World $level;
    private int $height;
    private Block $plotWallBlock;
    private Vector3 $plotBeginPos;
    private int|float $xMax;
    private int|float $zMax;
    private ?Player $player;

    public function __construct(Plot $plot, Wall $wall, Player $player = null) {
        $this->plot = $plot;
        $this->plotBeginPos = MyPlot::getInstance()->getPlotPosition($plot);
        $this->level = $this->plotBeginPos->getWorld();
        $this->plotBeginPos = $this->plotBeginPos->subtract(1,0,1);
        $plotLevel = MyPlot::getInstance()->getLevelSettings($plot->levelName);
        $plotSize = $plotLevel->plotSize;
        $this->xMax = $this->plotBeginPos->x + $plotSize + 1;
        $this->zMax = $this->plotBeginPos->z + $plotSize + 1;
        $this->height = $plotLevel->groundHeight;
        $this->plotWallBlock = $wall->getBlock();
        $this->player = $player;
    }

    public function onRun() : void {
        for($x = $this->plotBeginPos->x; $x <= $this->xMax; $x++) {
            for ($y = 1; ($y < $this->height + 1); $y++) {
                $this->level->setBlock(new Vector3($x, $y, $this->plotBeginPos->z), $this->plotWallBlock, false);
                $this->level->setBlock(new Vector3($x, $y, $this->zMax), $this->plotWallBlock, false);
            }
        }
        for($z = $this->plotBeginPos->z; $z <= $this->zMax; $z++) {
            for ($y = 1; ($y < $this->height + 1); $y++) {
                $this->level->setBlock(new Vector3($this->plotBeginPos->x, $y, $z), $this->plotWallBlock, false);
                $this->level->setBlock(new Vector3($this->xMax, $y, $z), $this->plotWallBlock, false);
            }
        }
        if ($this->player !== null) {
            $this->player->sendMessage(MyPlot::getPrefix() . MyPlot::getInstance()->getLanguage()->translateString("wall.success", [$this->plot]));
        }
    }
}