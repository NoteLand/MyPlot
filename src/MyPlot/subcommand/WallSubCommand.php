<?php


namespace MyPlot\subcommand;


use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use MyPlot\forms\MyPlotForm;
use MyPlot\MyPlot;
use MyPlot\task\ChangeWallTask;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class WallSubCommand extends SubCommand
{

    public function canUse(CommandSender $sender): bool
    {
        return ($sender instanceof Player) and $sender->hasPermission("myplot.command.wall");
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
        if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.wall")) {
            $sender->sendMessage(MyPlot::getPrefix() . TextFormat::RED . $this->translateString("notowner"));
            return true;
        }
        $elements = [];
        foreach (MyPlot::$walls as $wall) {
            $elements[] = new MenuOption("§c" . $wall->getName());
        }
        $form = new MenuForm(
            $this->translateString("wall.title"),
            $this->translateString("wall.content"),
            $elements,

            /**
             * Called when the player submits the form.
             *
             * @param Player $submitter
             * @param int    $selected
             */
            function(Player $submitter, int $selected) use ($plot) : void{
                $wall = MyPlot::$walls[$selected];
                MyPlot::getInstance()->getScheduler()->scheduleTask(new ChangeWallTask($plot, $wall, $submitter));
            }
        );
        $sender->sendForm($form);
        return true;
    }

    public function getForm(?Player $player = null) : ?MyPlotForm {
        return null;
    }
}