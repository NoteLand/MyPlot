<?php


namespace MyPlot\subcommand;


use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use MyPlot\forms\MyPlotForm;
use MyPlot\MyPlot;
use MyPlot\task\ChangeBorderTask;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class BorderSubCommand extends SubCommand
{

    public function canUse(CommandSender $sender): bool
    {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.border");
    }

    /**
     * @param Player $sender
     * @param string[] $args
     *
     * @return bool
     */
    public function execute(CommandSender $sender, array $args): bool
    {
        $plot = $this->getPlugin()->getPlotByPosition($sender);
        if($plot === null) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notinplot"));
            return true;
        }
        if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.border")) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
            return true;
        }
        $elements = [];
        foreach (MyPlot::$borders as $border) {
            $elements[] = new MenuOption("§c" . $border->getName());
        }
        $form = new MenuForm(
            $this->translateString("border.title"),
            $this->translateString("border.content"),
            $elements,

            /**
             * Called when the player submits the form.
             *
             * @param Player $submitter
             * @param int    $selected
             */
            function(Player $submitter, int $selected) use ($plot) : void{
                $border = MyPlot::$borders[$selected];
                MyPlot::getInstance()->getScheduler()->scheduleTask(new ChangeBorderTask($plot, $border, $submitter));
            }
        );
        $sender->sendForm($form);
        return true;
    }

    public function getForm(?Player $player = null) : ?MyPlotForm {
        return null;
    }
}