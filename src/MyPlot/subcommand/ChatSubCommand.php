<?php

declare(strict_types=1);

namespace MyPlot\subcommand;

use MyPlot\forms\subforms\ChatForm;
use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\command\CommandSender;
use MyPlot\forms\MyPlotForm;
use pocketmine\player\Player;

class ChatSubCommand extends SubCommand
{
	/**
	 * @param CommandSender $sender
	 *
	 * @return bool
	 */
	public function canUse(CommandSender $sender) : bool {
		return ($sender instanceof Player) and $sender->hasPermission("myplot.command.chat");
	}

	/**
	 * @param Player $sender
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, array $args): bool{
	    $plot = $this->getOwningPlugin()->getPlotByPosition($sender->getPosition());
		if($plot === null) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notinplot"));
			return true;
		}
		if(empty($args)) {
			$sender->sendMessage(MyPlot::getPrefix() . $this->translateString("chat.define.message"));
			return false;
		}
		if ($args[0] === "toggle") {
            if($plot->owner !== $sender->getName() and !$sender->hasPermission("myplot.admin.chat")) {
                $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("notowner"));
                return true;
            }
		    if ($plot->getFlag("chat")) {
		        $plot->setFlag("chat", false);
                $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("chat.toggle_off"));
            } else {
                $plot->setFlag("chat", true);
                $sender->sendMessage(MyPlot::getPrefix() . $this->translateString("chat.toggle_on"));
            }
		    $this->getOwningPlugin()->getProvider()->savePlot($plot);
		    return true;
        }

		$message = implode(" ", $args);

		$players = $this->getOwningPlugin()->getServer()->getOnlinePlayers();
		foreach ($players as $player) {
			$playerplot = $this->getOwningPlugin()->getPlotByPosition($player->getPosition());
			if ($playerplot !== null) {
				if ($playerplot === $plot) {
					$player->sendMessage($this->translateString("chat.format", [$sender->getName(), $message]));
				}
			}
		}
		return true;
	}

	public function getForm(?Player $player = null) : ?MyPlotForm {
        if($player !== null and $this->getOwningPlugin()->getPlotByPosition($player->getPosition()) instanceof Plot)
            return new ChatForm($player);
		return null;
	}
}