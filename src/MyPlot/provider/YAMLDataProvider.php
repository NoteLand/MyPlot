<?php
declare(strict_types=1);
namespace MyPlot\provider;

use MyPlot\MyPlot;
use MyPlot\Plot;
use pocketmine\level\Position;
use pocketmine\utils\Config;

class YAMLDataProvider extends DataProvider {
	/** @var MyPlot $plugin */
	protected $plugin;
	/** @var Config $yaml */
	private $yaml;

	/**
	 * YAMLDataProvider constructor.
	 *
	 * @param MyPlot $plugin
	 * @param int $cacheSize
	 */
	public function __construct(MyPlot $plugin, int $cacheSize = 0) {
		parent::__construct($plugin, $cacheSize);
		@mkdir($this->plugin->getDataFolder() . "Data");
		$this->yaml = new Config($this->plugin->getDataFolder() . "Data" . DIRECTORY_SEPARATOR . "plots.yml", Config::YAML, ["count" => -1, "plots" => []]);
	}

	public function savePlot(Plot $plot) : bool {
		$plots = $this->yaml->get("plots", []);
        if ($plot->spawn === null) {
            $spawn = "false";
        } else {
            $spawn = $plot->spawn->getFloorX() . ";" . $plot->spawn->getFloorY() . ";" . $plot->spawn->getFloorZ();
        }
		if($plot->id > -1) {
            $plots[$plot->id] = ["level" => $plot->levelName, "x" => $plot->X, "z" => $plot->Z, "description" => $plot->description, "name" => $plot->name, "owner" => $plot->owner, "helpers" => $plot->helpers, "denied" => $plot->denied, "biome" => $plot->biome, "pvp" => $plot->pvp, "price" => $plot->price, "merged_plots" => $plot->merged_plots, "flags" => $plot->flags, "spawn" => $spawn, "chat" => $plot->chat];
        }else{
			$id = $this->yaml->get("count", 0) + 1;
			$plot->id = $id;
            $plots[$id] = ["level" => $plot->levelName, "x" => $plot->X, "z" => $plot->Z, "description" => $plot->description, "name" => $plot->name, "owner" => $plot->owner, "helpers" => $plot->helpers, "denied" => $plot->denied, "biome" => $plot->biome, "pvp" => $plot->pvp, "price" => $plot->price, "merged_plots" => $plot->merged_plots, "flags" => $plot->flags, "spawn" => $spawn, "chat" => $plot->chat];
            $this->yaml->set("count", $id);
		}
		$this->yaml->set("plots", $plots);
		$this->cachePlot($plot);
		return $this->yaml->save();
	}

	public function deletePlot(Plot $plot) : bool {
		$plots = $this->yaml->get("plots", []);
		unset($plots[$plot->id]);
		$this->yaml->set("plots", $plots);
		$plot = new Plot($plot->levelName, $plot->X, $plot->Z);
		$this->cachePlot($plot);
		return $this->yaml->save();
	}

	public function getPlot(string $levelName, int $X, int $Z) : Plot {
		if(($plot = $this->getPlotFromCache($levelName, $X, $Z)) !== null) {
			return $plot;
		}
		$plots = $this->yaml->get("plots", []);
		$levelKeys = $xKeys = $zKeys = [];
		foreach($plots as $key => $plotData) {
			if($plotData["level"] === $levelName)
				$levelKeys[] = $key;
			if($plotData["x"] === $X)
				$xKeys[] = $key;
			if($plotData["z"] === $Z)
				$zKeys[] = $key;
		}
		/** @var int|null $key */
		$key = null;
		foreach($levelKeys as $levelKey) {
			foreach($xKeys as $xKey) {
				foreach($zKeys as $zKey) {
					if($zKey == $xKey and $xKey == $levelKey and $zKey == $levelKey) {
						$key = $levelKey;
						break 3;
					}
				}
			}
		}
		if(is_int($key)) {
            $description = (string)$plots[$key]["description"];
			$plotName = (string)$plots[$key]["name"];
			$owner = (string)$plots[$key]["owner"];
			$helpers = (array)$plots[$key]["helpers"];
			$denied = (array)$plots[$key]["denied"];
			$biome = strtoupper($plots[$key]["biome"]);
			$pvp = (bool)$plots[$key]["pvp"];
            $price = (float)$plots[$key]["price"];
            $merged_plots = (array)$plots[$key]["merged_plots"];
            $flags = (array)$plots[$key]["flags"];
            $spawn = (string)$plots[$key]["spawn"];
            if ($spawn === "false") {
                $spawn = null;
            } else {
                $spawn = explode(";", $spawn);
                $spawn = new Position($spawn[0], $spawn[1], $spawn[2], $this->plugin->getServer()->getLevelByName($levelName));
            }
            $chat = (bool)$plots[$key]["chat"];
            return new Plot($levelName, $X, $Z, $description, $plotName, $owner, $helpers, $denied, $biome, $pvp, $price, $merged_plots, $flags, $spawn, $chat, $key);
		}
		return new Plot($levelName, $X, $Z);
	}

	/**
	 * @param string $owner
	 * @param string $levelName
	 *
	 * @return Plot[]
	 */
	public function getPlotsByOwner(string $owner, string $levelName = "") : array {
		$plots = $this->yaml->get("plots", []);
		$ownerPlots = [];
        if($levelName != "") {
            /** @var int[] $levelKeys */
            $levelKeys = array_keys($plots, $levelName, true);
            /** @var int[] $ownerKeys */
            $ownerKeys = array_keys($plots, $owner, true);
            foreach($levelKeys as $levelKey) {
                foreach($ownerKeys as $ownerKey) {
                    if($levelKey === $ownerKey) {
                        $X = $plots[$levelKey]["x"];
                        $Z = $plots[$levelKey]["z"];
                        $description = $plots[$levelKey]["description"] == "" ? "" : $plots[$levelKey]["description"];
                        $plotName = $plots[$levelKey]["name"] == "" ? "" : $plots[$levelKey]["name"];
                        $owner = $plots[$levelKey]["owner"] == "" ? "" : $plots[$levelKey]["owner"];
                        $helpers = $plots[$levelKey]["helpers"] == [] ? [] : $plots[$levelKey]["helpers"];
                        $denied = $plots[$levelKey]["denied"] == [] ? [] : $plots[$levelKey]["denied"];
                        $biome = strtoupper($plots[$levelKey]["biome"]) == "PLAINS" ? "PLAINS" : strtoupper($plots[$levelKey]["biome"]);
                        $pvp = $plots[$levelKey]["pvp"] == null ? false : $plots[$levelKey]["pvp"];
                        $price = $plots[$levelKey]["price"] == null ? 0.0 : $plots[$levelKey]["price"];
                        $merged_plots = $plots[$levelKey]["merged_plots"] == [] ? [] : $plots[$levelKey]["merged_plots"];
                        $flags = $plots[$levelKey]["flags"] == [] ? [] : $plots[$levelKey]["flags"];
                        $spawn = $plots[$levelKey]["spawn"] == "false" ? "false" : $plots[$levelKey]["spawn"];
                        if ($spawn === "false") {
                            $spawn = null;
                        } else {
                            $spawn = explode(";", $spawn);
                            $spawn = new Position($spawn[0], $spawn[1], $spawn[2], $this->plugin->getServer()->getLevelByName($levelName));
                        }
                        $chat = $plots[$levelKey]["chat"] == false ? false : $plots[$levelKey]["chat"];
                        $ownerPlots[] = new Plot($levelName, $X, $Z, $description, $plotName, $owner, $helpers, $denied, $biome, $pvp, $price, $merged_plots, $flags, $spawn, $chat, $levelKey);
                    }
                }
            }
        }else{
            /** @var int[] $ownerKeys */
            $ownerKeys = array_keys($plots, $owner, true);
            foreach($ownerKeys as $key) {
                $levelName = $plots[$key]["level"];
                $X = $plots[$key]["x"];
                $Z = $plots[$key]["z"];
                $description = $plots[$key]["description"] == "" ? "" : $plots[$key]["description"];
                $plotName = $plots[$key]["name"] == "" ? "" : $plots[$key]["name"];
                $owner = $plots[$key]["owner"] == "" ? "" : $plots[$key]["owner"];
                $helpers = $plots[$key]["helpers"] == [] ? [] : $plots[$key]["helpers"];
                $denied = $plots[$key]["denied"] == [] ? [] : $plots[$key]["denied"];
                $biome = strtoupper($plots[$key]["biome"]) == "PLAINS" ? "PLAINS" : strtoupper($plots[$key]["biome"]);
                $pvp = $plots[$key]["pvp"] == null ? false : $plots[$key]["pvp"];
                $price = $plots[$key]["price"] == null ? 0.0 : $plots[$key]["price"];
                $merged_plots = $plots[$key]["merged_plots"] == [] ? [] : $plots[$key]["merged_plots"];
                $flags = $plots[$key]["flags"] == [] ? [] : $plots[$key]["flags"];
                $spawn = $plots[$key]["spawn"] == "false" ? "false" : $plots[$key]["spawn"];
                if ($spawn === "false") {
                    $spawn = null;
                } else {
                    $spawn = explode(";", $spawn);
                    $spawn = new Position($spawn[0], $spawn[1], $spawn[2], $this->plugin->getServer()->getLevelByName($levelName));
                }
                $chat = $plots[$key]["chat"] == false ? false : $plots[$key]["chat"];
                $ownerPlots[] = new Plot($levelName, $X, $Z, $description, $plotName, $owner, $helpers, $denied, $biome, $pvp, $price, $merged_plots, $flags, $spawn, $chat, $key);
            }
        }
		return $ownerPlots;
	}

	public function getNextFreePlot(string $levelName, int $limitXZ = 0) : ?plot {
		$plotsArr = $this->yaml->get("plots", []);
		for($i = 0; $limitXZ <= 0 or $i < $limitXZ; $i++) {
			$existing = [];
			foreach($plotsArr as $id => $data) {
				if($data["level"] === $levelName) {
					if(abs($data["x"]) === $i and abs($data["z"]) <= $i) {
						$existing[] = [$data["x"], $data["z"]];
					}elseif(abs($data["z"]) === $i and abs($data["x"]) <= $i) {
						$existing[] = [$data["x"], $data["z"]];
					}
				}
			}
			$plots = [];
			foreach($existing as $arr) {
				$plots[$arr[0]][$arr[1]] = true;
			}
			if(count($plots) === max(1, 8 * $i)) {
				continue;
			}
			if(($ret = self::findEmptyPlotSquared(0, $i, $plots)) !== null) {
				[$X, $Z] = $ret;
				$plot = new Plot($levelName, $X, $Z);
				$this->cachePlot($plot);
				return $plot;
			}
			for($a = 1; $a < $i; $a++) {
				if(($ret = self::findEmptyPlotSquared($a, $i, $plots)) !== null) {
					[$X, $Z] = $ret;
					$plot = new Plot($levelName, $X, $Z);
					$this->cachePlot($plot);
					return $plot;
				}
			}
			if(($ret = self::findEmptyPlotSquared($i, $i, $plots)) !== null) {
				[$X, $Z] = $ret;
				$plot = new Plot($levelName, $X, $Z);
				$this->cachePlot($plot);
				return $plot;
			}
		}
		return null;
	}

	public function close() : void {
		unset($this->yaml);
	}
}