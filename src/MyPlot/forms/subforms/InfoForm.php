<?php
declare(strict_types=1);
namespace MyPlot\forms\subforms;

use MyPlot\forms\SimpleMyPlotForm;
use MyPlot\MyPlot;
use MyPlot\utils\Flags;
use MyPlot\utils\FlagsFactory;
use pocketmine\player\Player;

class InfoForm extends SimpleMyPlotForm {
	public function __construct(Player $player) {
        if(!isset($this->plot))
            $this->plot = MyPlot::getInstance()->getPlotByPosition($player->getPosition());
        if(!isset($this->plot))
            return;

        if (MyPlot::getInstance()->getServer()->getPlayerExact($this->plot->owner)) {
            $owner = $this->plot->owner . " §a(Online)";
        } else {
            $owner = $this->plot->owner . " §c(Offline)";
        }
        $helpers = implode(", ", $this->plot->helpers);
        $denied = implode(", ", $this->plot->denied);
        if (!$this->plot->getFlag(Flags::DESCRIPTION)) {
            $description = "";
        } else {
            $description = $this->plot->getFlag(Flags::DESCRIPTION);
        }
		$flag_names = [];
		foreach (array_keys($this->plot->flags) as $flag_name) {
			if (($type = FlagsFactory::getInstance()->getFlagType($flag_name)) != -1) {
				if ($type == FlagsFactory::TYPE_BOOLEAN) {
					if ($this->plot->getFlag($flag_name))
						$flag_names[] = $flag_name;
				}
			}
		}
		$flags = implode(", ", $flag_names);
		parent::__construct(
		    MyPlot::getInstance()->getLanguage()->translateString("info.title"),
            MyPlot::getInstance()->getLanguage()->translateString("info.content", [$this->plot, $owner, $description, $this->plot->name, $helpers, $denied, $flags]),
            [],
            function(Player $submitter, int $selected) : void {},
            function () : void {}
        );
	}
}
