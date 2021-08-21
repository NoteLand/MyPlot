<?php


namespace MyPlot\forms\subforms;


use dktapps\pmforms\MenuOption;
use MyPlot\forms\SimpleMyPlotForm;
use MyPlot\MyPlot;
use MyPlot\task\ChangeBorderTask;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class BorderForm extends SimpleMyPlotForm
{
    public function __construct()
    {
        $elements = [];
        foreach (MyPlot::$borders as $border) {
            $elements[] = new MenuOption("§c" . $border->getName());
        }
        parent::__construct(
            MyPlot::getInstance()->getLanguage()->translateString("border.title"),
            MyPlot::getInstance()->getLanguage()->translateString("border.content"),
            $elements,

            /**
             * Called when the player submits the form.
             *
             * @param Player $submitter
             * @param int    $selected
             */
            function(Player $submitter, int $selected) : void{
                $border = MyPlot::$borders[$selected];
                $plot = $this->getPlot();
                if($plot === null) {
                    $submitter->sendMessage(MyPlot::getPrefix() . TextFormat::RED . MyPlot::getInstance()->getLanguage()->translateString("notinplot"));
                    return;
                }
                if($plot->owner !== $submitter->getName() and !$submitter->hasPermission("myplot.admin.border")) {
                    $submitter->sendMessage(MyPlot::getPrefix() . TextFormat::RED . MyPlot::getInstance()->getLanguage()->translateString("notowner"));
                    return;
                }
                if (!$submitter->hasPermission($border->getPermission())) {
                    $submitter->sendMessage(MyPlot::getPrefix() . TextFormat::RED . MyPlot::getInstance()->getLanguage()->translateString("no.permissions"));
                    return;
                }
                MyPlot::getInstance()->getScheduler()->scheduleTask(new ChangeBorderTask($plot, $border, $submitter));
            }
        );
    }
}