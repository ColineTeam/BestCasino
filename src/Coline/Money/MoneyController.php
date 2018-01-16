<?php
namespace Coline\Money;

use pocketmine\Player;
/**
 * Description of MoneyController
 *
 * @author Alexey
 */
class MoneyController {
    public $provider_id;
    public function __construct(int $provider_id) {
        $this->provider_id = $provider_id;
    }
    public function getMoneyProviderByPlayer(Player $player): providers\MoneyProvider{
        switch ($this->provider_id) {
            case 1: return (new providers\EconomyApi($player));
           
        }
    }
}
