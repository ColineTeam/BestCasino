<?php


namespace Coline\Money\providers;

/**
 * Description of MoneyProvider
 *
 * @author Alexey
 */
class MoneyProvider {
    public $player;
    
    public function __construct(\pocketmine\Player $player) {
        $this->player = $player;
    }
//    public function getMoney();
//    public function reduceMoney($amount);
}
