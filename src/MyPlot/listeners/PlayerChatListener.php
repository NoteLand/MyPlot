<?php


namespace MyPlot\listeners;


use MyPlot\MyPlot;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\TextFormat;

class PlayerChatListener implements Listener
{

    /**
     * @ignoreCancelled false
     * @priority MONITOR
     *
     * @param PlayerChatEvent $event
     */
    public function onChat(PlayerChatEvent $event) {
        if ($event->isCancelled()) return;
        $levelName = $event->getPlayer()->getLevel()->getFolderName();
        if(!MyPlot::getInstance()->isLevelLoaded($levelName)) return;
        $plot = MyPlot::getInstance()->getPlotByPosition($event->getPlayer());
        if($plot === null) return;

        $message = $event->getMessage();

        if ($plot->chat) {
            $event->setCancelled(true);
            $players = MyPlot::getInstance()->getServer()->getOnlinePlayers();
            foreach ($players as $player) {
                $playerplot = MyPlot::getInstance()->getPlotByPosition($player);
                if ($playerplot !== NULL) {
                    if ($playerplot === $plot) {
                        $player->sendMessage(TextFormat::GOLD . MyPlot::getInstance()->getLanguage()->translateString("chat.format", [$player->getName(),TextFormat::WHITE . $message]));
                    }
                }
            }
        }
    }
}