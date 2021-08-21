<?php


namespace MyPlot\forms\subforms;


use dktapps\pmforms\MenuOption;
use MyPlot\forms\SimpleMyPlotForm;
use MyPlot\MyPlot;
use MyPlot\task\ChangeWallTask;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class WallForm extends SimpleMyPlotForm
{
    public function __construct()
    {
        $elements = [];
        foreach (MyPlot::$walls as $wall) {
            $elements[] = new MenuOption("§c" . $wall->getName());
        }
        parent::__construct(
            MyPlot::getInstance()->getLanguage()->translateString("wall.title"),
            MyPlot::getInstance()->getLanguage()->translateString("wall.content"),
            $elements,

            /**
             * Called when the player submits the form.
             *
             * @param Player $submitter
             * @param int    $selected
             */
            function(Player $submitter, int $selected) : void{
                $wall = MyPlot::$walls[$selected];
                $plot = $this->getPlot();
                if($plot === null) {
                    $submitter->sendMessage(MyPlot::getPrefix() . TextFormat::RED . MyPlot::getInstance()->getLanguage()->translateString("notinplot"));
                    return;
                }
                if($plot->owner !== $submitter->getName() and !$submitter->hasPermission("myplot.admin.wall")) {
                    $submitter->sendMessage(MyPlot::getPrefix() . TextFormat::RED . MyPlot::getInstance()->getLanguage()->translateString("notowner"));
                    return;
                }
                if (!$submitter->hasPermission($wall->getPermission())) {
                    $submitter->sendMessage(MyPlot::getPrefix() . TextFormat::RED . MyPlot::getInstance()->getLanguage()->translateString("no.permissions"));
                    return;
                }
                MyPlot::getInstance()->getScheduler()->scheduleTask(new ChangeWallTask($plot, $wall, $submitter));
            }
        );
    }
}