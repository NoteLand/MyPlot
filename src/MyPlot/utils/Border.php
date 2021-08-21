<?php


namespace MyPlot\utils;

use pocketmine\block\Block;

class Border
{
    /** @var string $name */
    public $name;

    /** @var Block $block */
    public $block;

    /** @var string $permission */
    public $permission;

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