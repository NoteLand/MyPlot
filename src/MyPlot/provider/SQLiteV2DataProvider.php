<?php


namespace MyPlot\provider;


use Exception;
use MyPlot\MyPlot;
use MyPlot\Plot;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

class SQLiteV2DataProvider extends DataProvider
{
	private SQLite3 $db;
	protected SQLite3Stmt $sqlGetPlot;
	protected SQLite3Stmt $sqlSavePlot;
	protected SQLite3Stmt $sqlRemovePlot;
	protected SQLite3Stmt $sqlGetPlotsByOwner;
	protected SQLite3Stmt $sqlGetPlotsByOwnerAndLevel;
	protected SQLite3Stmt $sqlGetExistingXZ;

	/**
	 * SQLiteDataProvider constructor.
	 *
	 * @param MyPlot $plugin
	 * @param int    $cacheSize
	 *
	 * @throws Exception
	 */
	public function __construct(MyPlot $plugin, int $cacheSize = 0) {
		parent::__construct($plugin, $cacheSize);
		$this->db = new SQLite3($this->plugin->getDataFolder() . "plots.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS plotsV2
			(level TEXT, X INTEGER, Z INTEGER, name TEXT,
			 owner TEXT, helpers TEXT, denied TEXT, biome TEXT, pvp INTEGER, price FLOAT, PRIMARY KEY (level, X, Z));");
		$this->db->exec("ALTER TABLE plots ADD merged_plots TEXT;");
		$this->db->exec("ALTER TABLE plots ADD flags TEXT;");
		$this->db->exec("ALTER TABLE plotsV2 ADD merged_plots TEXT;");
		$this->db->exec("ALTER TABLE plotsV2 ADD flags TEXT;");
		if($this->db->querySingle("SELECT count(name) FROM sqlite_Master WHERE type='table' AND name='plots';") > 0)
			$this->db->exec("INSERT OR IGNORE INTO plotsV2 (level, X, Z, name, owner, helpers, denied, biome, pvp, price, merged_plots, flags) SELECT level, X, Z, name, owner, helpers, denied, biome, pvp, price, merged_plots, flags FROM plots;");
		$this->prepare();
		$this->plugin->getLogger()->debug("SQLite data provider registered");
	}

	public function savePlot(Plot $plot) : bool {
		$helpers = implode(",", $plot->helpers);
		$denied = implode(",", $plot->denied);
		$merged_plots = implode(",", $plot->merged_plots);
		$flags = json_encode($plot->flags, JSON_FORCE_OBJECT);
		$stmt = $this->sqlSavePlot;
		$stmt->bindValue(":level", $plot->levelName, SQLITE3_TEXT);
		$stmt->bindValue(":X", $plot->X, SQLITE3_INTEGER);
		$stmt->bindValue(":Z", $plot->Z, SQLITE3_INTEGER);
		$stmt->bindValue(":name", $plot->name, SQLITE3_TEXT);
		$stmt->bindValue(":owner", $plot->owner, SQLITE3_TEXT);
		$stmt->bindValue(":helpers", $helpers, SQLITE3_TEXT);
		$stmt->bindValue(":denied", $denied, SQLITE3_TEXT);
		$stmt->bindValue(":biome", $plot->biome, SQLITE3_TEXT);
		$stmt->bindValue(":pvp", $plot->pvp, SQLITE3_INTEGER);
		$stmt->bindValue(":price", $plot->price, SQLITE3_FLOAT);
		$stmt->bindValue(":merged_plots", $merged_plots, SQLITE3_TEXT);
		$stmt->bindValue(":flags", $flags, SQLITE3_TEXT);
		$stmt->reset();
		$result = $stmt->execute();
		if(!$result instanceof SQLite3Result) {
			return false;
		}
		$this->cachePlot($plot);
		return true;
	}

	public function deletePlot(Plot $plot) : bool {
		$stmt = $this->sqlRemovePlot;
		$stmt->bindValue(":level", $plot->levelName, SQLITE3_TEXT);
		$stmt->bindValue(":X", $plot->X, SQLITE3_INTEGER);
		$stmt->bindValue(":Z", $plot->Z, SQLITE3_INTEGER);
		$stmt->reset();
		$result = $stmt->execute();
		if(!$result instanceof SQLite3Result) {
			return false;
		}
		$plot = new Plot($plot->levelName, $plot->X, $plot->Z);
		$this->cachePlot($plot);
		return true;
	}

	public function getPlot(string $levelName, int $X, int $Z) : Plot {
		if(($plot = $this->getPlotFromCache($levelName, $X, $Z)) !== null) {
			return $plot;
		}
		$this->sqlGetPlot->bindValue(":level", $levelName, SQLITE3_TEXT);
		$this->sqlGetPlot->bindValue(":X", $X, SQLITE3_INTEGER);
		$this->sqlGetPlot->bindValue(":Z", $Z, SQLITE3_INTEGER);
		$this->sqlGetPlot->reset();
		$result = $this->sqlGetPlot->execute();
		if($result !== false and ($val = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
			if($val["helpers"] === null or $val["helpers"] === "") {
				$helpers = [];
			}else{
				$helpers = explode(",", (string) $val["helpers"]);
			}
			if($val["denied"] === null or $val["denied"] === "") {
				$denied = [];
			}else{
				$denied = explode(",", (string) $val["denied"]);
			}
			$pvp = is_numeric($val["pvp"]) ? (bool)$val["pvp"] : null;
			if($val["merged_plots"] === null or $val["merged_plots"] === "") {
				$merged_plots = [];
			}else{
				$merged_plots = explode(",", (string) $val["merged_plots"]);
			}
			if ($val['flags'] === '{}' or $val['flags'] === '' or $val['flags'] === null) {
				$flags = [];
			} else $flags = json_decode($val['flags'], true);
			$plot = new Plot($levelName, $X, $Z, (string) $val["name"], (string) $val["owner"], $helpers, $denied, (string) $val["biome"], $pvp, (float) $val["price"], $merged_plots, $flags);
		}else{
			$plot = new Plot($levelName, $X, $Z);
		}
		$this->cachePlot($plot);
		return $plot;
	}

	/**
	 * @param string $owner
	 * @param string $levelName
	 *
	 * @return Plot[]
	 */
	public function getPlotsByOwner(string $owner, string $levelName = "") : array {
		if($levelName === "") {
			$stmt = $this->sqlGetPlotsByOwner;
		}else{
			$stmt = $this->sqlGetPlotsByOwnerAndLevel;
			$stmt->bindValue(":level", $levelName, SQLITE3_TEXT);
		}
		$stmt->bindValue(":owner", $owner, SQLITE3_TEXT);
		$plots = [];
		$stmt->reset();
		$result = $stmt->execute();
		while($result !== false and ($val = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
			$helpers = explode(",", (string) $val["helpers"]);
			$denied = explode(",", (string) $val["denied"]);
			$pvp = is_numeric($val["pvp"]) ? (bool)$val["pvp"] : null;
			$merged_plots = explode(",", (string) $val["merged_plots"]);
			if ($val['flags'] === '{}' or $val['flags'] === '' or $val['flags'] === null) {
				$flags = [];
			} else $flags = json_decode($val['flags'], true);
			$plots[] = new Plot((string) $val["level"], (int) $val["X"], (int) $val["Z"], (string) $val["name"], (string) $val["owner"], $helpers, $denied, (string) $val["biome"], $pvp, (float) $val["price"], $merged_plots, $flags);
		}
		// Remove unloaded plots
		$plots = array_filter($plots, function(Plot $plot) : bool {
			return $this->plugin->isLevelLoaded($plot->levelName);
		});
		// Sort plots by level
		usort($plots, function(Plot $plot1, Plot $plot2) : int {
			return strcmp($plot1->levelName, $plot2->levelName);
		});
		return $plots;
	}

	public function getNextFreePlot(string $levelName, int $limitXZ = 0) : ?Plot {
		$this->sqlGetExistingXZ->bindValue(":level", $levelName, SQLITE3_TEXT);
		$i = 0;
		$this->sqlGetExistingXZ->bindParam(":number", $i, SQLITE3_INTEGER);
		for(; $limitXZ <= 0 or $i < $limitXZ; $i++) {
			$this->sqlGetExistingXZ->reset();
			$result = $this->sqlGetExistingXZ->execute();
			$plots = [];
			while($result != false and ($val = $result->fetchArray(SQLITE3_NUM)) !== false) {
				$plots[$val[0]][$val[1]] = true;
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
		$this->db->close();
		$this->plugin->getLogger()->debug("SQLite database closed!");
	}

	/**
	 * @throws Exception
	 */
	private function prepare() : void {
		$stmt = $this->db->prepare("SELECT name, owner, helpers, denied, biome, pvp, price, merged_plots, flags FROM plotsV2 WHERE level = :level AND X = :X AND Z = :Z;");
		if($stmt === false)
			throw new Exception();
		$this->sqlGetPlot = $stmt;
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO plotsV2 (level, X, Z, name, owner, helpers, denied, biome, pvp, price, merged_plots, flags) VALUES (:level, :X, :Z, :name, :owner, :helpers, :denied, :biome, :pvp, :price, :merged_plots, :flags);");
		if($stmt === false)
			throw new Exception();
		$this->sqlSavePlot = $stmt;
		$stmt = $this->db->prepare("DELETE FROM plotsV2 WHERE level = :level AND X = :X AND Z = :Z;");
		if($stmt === false)
			throw new Exception();
		$this->sqlRemovePlot = $stmt;
		$stmt = $this->db->prepare("SELECT * FROM plotsV2 WHERE owner = :owner;");
		if($stmt === false)
			throw new Exception();
		$this->sqlGetPlotsByOwner = $stmt;
		$stmt = $this->db->prepare("SELECT * FROM plotsV2 WHERE owner = :owner AND level = :level;");
		if($stmt === false)
			throw new Exception();
		$this->sqlGetPlotsByOwnerAndLevel = $stmt;
		$stmt = $this->db->prepare("SELECT X, Z FROM plotsV2 WHERE (
				level = :level
				AND (
					(abs(X) = :number AND abs(Z) <= :number) OR
					(abs(Z) = :number AND abs(X) <= :number)
				)
			);");
		if($stmt === false)
			throw new Exception();
		$this->sqlGetExistingXZ = $stmt;
	}
}