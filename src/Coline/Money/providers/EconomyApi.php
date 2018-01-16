<?php

namespace Coline\Money\providers;

/**
 * Description of EconomyApi
 *
 * @author Alexey
 */
class EconomyApi extends MoneyProvider{
    
    public function getMoney(){
        return $this->moneyPlugin()->myMoney($this->getPlayer()); 
    }
    public function reduceMoney( $amount){
        return $this->moneyPlugin()->reduceMoney($this->getPlayer(), $amount);
    }
    public function moneyPlugin(){
        return \onebone\economyapi\EconomyAPI::getInstance();
    }
    private function getPlayer(){
        return $this->player;
    }
}
