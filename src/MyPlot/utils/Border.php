<?php


namespace MyPlot\utils;

use pocketmine\block\Block;

class Border
{
    public string $name;
    public Block $block;
    public string $permission;

    /**
     * Border constructor.
     * @param string $name
     * @param Block $block
     * @param string $permission
     */
    public function __construct(string $name, Block $block, string $permission)
    {
        $this->name = $name;
        $this->block = $block;
        $this->permission = $permission;
    }

    /** @return string */
    public function getName(): string
    {
        return $this->name;
    }

    /** @return Block */
    public function getBlock(): Block
    {
        return $this->block;
    }

    /** @return string */
    public function getPermission(): string
    {
        return $this->permission;
    }
}