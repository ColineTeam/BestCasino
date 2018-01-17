<?php

/* MIT License

Copyright (c) 2017 Alexey Lozovjagin

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
 */

namespace Coline\Casino;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\math\Vector3;
use pocketmine\block\BlockIds;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\scheduler\PluginTask;
use pocketmine\tile\ItemFrame;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
/**
 * BestCasinoMain от Алексея Лозовягина (https://vk.com/olekseyua)
 * License: MIT 
 * @author Alexey
 */
class BestCasinoMain extends PluginBase implements Listener{
    public $items = [
        1 => BlockIds::GOLD_BLOCK,
        2 => BlockIds::DIAMOND_BLOCK,
        3 => BlockIds::IRON_BLOCK,
    ];
    public $rotationTask = false;
    private $scopes = [];
    
    public $framesetting = [];
    public $gamevariables = [
        'player' => null,
        'started' => false
    ];
    public $vectors = [], $frames = [], $button, $mode;
    public function onLoad() {
        $frames = @json_decode(file_get_contents($this->getDataFolder()."frames.json"), true);
        $this->button = @$frames['button'];
        $this->mode = @$frames['mode'];
        $frames = $frames['frames'];
        if(@$frames != null){
        foreach ($frames as $num => $frame){
            $this->frames[$num] =[
                'vector' => $frame,
                "activated" => false
            ];
        }
        }
        
        
    }
    public function onEnable() {
        (new \ColineServices\Updater($this, 195, $this->getFile()))->update();
        $this->getServer()->getScheduler()->scheduleDelayedTask(new ClearTask($this), 5*20);
         $this->getServer()->getPluginManager()->registerEvents($this, $this);
         
         foreach ($this->frames as $frame => $data){ //даже не спрашивайте что здесь
             $xyz = explode(":", $data['vector']);
             
             $this->vectors[$xyz[0].":". $xyz[1].":". $xyz[2]] = true;
             $this->vectors[$xyz[0] .":". $xyz[1].":". ($xyz[2] + 1)] = true;
             $this->vectors[$xyz[0] .":". $xyz[1].":". ($xyz[2] - 1)] = true;
             $this->vectors[$xyz[0] + 1 .":". $xyz[1].":". ($xyz[2] + 1)] = true;
             $this->vectors[$xyz[0] - 1 .":". $xyz[1].":". ($xyz[2] - 1)] = true;
             $this->vectors[$xyz[0] + 1 .":". $xyz[1].":". $xyz[2]] = true;
             $this->vectors[$xyz[0] - 1 .":". $xyz[1].":". $xyz[2]] = true;
             $this->vectors[$xyz[0] + 2 .":". $xyz[1].":". $xyz[2]] = true;
             $this->vectors[$xyz[0] - 2 .":". $xyz[1].":". $xyz[2]] = true;
             $this->vectors[$xyz[0] + 2 .":". $xyz[1].":". $xyz[2]] = true;
             $this->vectors[$xyz[0] + 1 .":". $xyz[1].":". ($xyz[2] -1)] = true;
             $this->vectors[$xyz[0] - 1 .":". $xyz[1].":". ($xyz[2] +1)] = true;
         }
         $this->saveDefaultConfig();
    }
    public function Interact(\pocketmine\event\player\PlayerInteractEvent $event){
        $block = $event->getBlock();
        foreach ($this->frames as $data){ // не лапать рамки!
            $vector = $data['vector'];
            if($vector == $block->x.":".$block->y.":".$block->z){
                $event->setCancelled();
            }
        }
    }
    public function onMove(\pocketmine\event\player\PlayerMoveEvent $event){
        $player = $event->getPlayer();
        if(@!isset($player->lastpostion)) $player->lastpostion = NULL;;
       
       
        if($player->lastpostion == NULL){
            $player->lastpostion = $player->x.":".$player->y.":".$player->z;
        }
        if($player->lastpostion != round($player->x).":".round($player->y).":".round($player->z)){
             $vectorstring = round($player->x).":".round($player->y).":".round($player->z);
           
            
            if($this->gamevariables['player'] != $player){
                if(@!is_null($this->vectors[$vectorstring])){
                    $laspostion = explode(":", $player->lastpostion);
                  $mode = $this->mode;
                    
                   $x = 0;
                   $z = 0;
                  if($mode == 1){
                      if(($laspostion[0] - $player->x) > 0){
                           $x = 1.25;
                      }else{ 
                         $x = -1.25;
                      }
                  }else if($mode == 2) {
                      if(($laspostion[2] - $player->z) > 0){
                           $z = 1.25;
                      }else{ 
                        $z = -1.25;
                      }
                     
                  }

                   
                    $player->setMotion(new Vector3($x, 0.1, $z));  
                    
                }
            }
             $player->lastpostion = $vectorstring;
            
        }
    }

    public function onStart(\pocketmine\event\player\PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if($block->getId() == BlockIds::STONE_BUTTON){
            if($block->getX() == $this->button['x'] && $block->getY() == $this->button['y'] && $block->getZ() == $this->button['z']){
                    if($this->gamevariables['started'] == FALSE){
                        if($this->getMoney()->getMoneyProviderByPlayer($player)->getMoney($this->_getConfig()['money'])   >= $this->_getConfig()['money']){
                            $this->getMoney()->getMoneyProviderByPlayer($player)->reduceMoney($this->_getConfig()['money']);
                            $this->gamevariables['player'] = $player;
                            $this->start();
                            $this->clearALL();
                           
                        }else{
                            $player->sendPopup("На вашем счету не достаточно {$this->_getConfig()['currency_name']}. Для запуска игры нужно {$this->_getConfig()['money']} {$this->_getConfig()['currency_name']}");
                        }
                    }else{
                        $player->sendMessage(TF::YELLOW."Лотерея уже запущена");
                    }
            }
            if($block->getId() == 199){
                $event->setCancelled();
            }
        }
    }
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        
        
        if(!($sender instanceof \pocketmine\command\ConsoleCommandSender)){
            $player = $sender;
            if($command->getName() == "settingcasino"){
                if($player->isOp()){
                    $this->scopes[$player->getName()] = 1;
                    $player->sendMessage("Нажмите на 1 рамку, желательно сделать как на схеме. Cхема:".PHP_EOL
                            ."[1][4][7]".PHP_EOL.
                             "[2][5][8][10]".PHP_EOL.
                               "[3][6][9]");
                }else{
                    $player->sendMessage("Я вам не доверяю");
                }
            }
        }else{
            $player->sendMessage("Консольке привет!");
        }
        return true;
    }

    public function onSettings(\pocketmine\event\player\PlayerInteractEvent $event){
       $player = $event->getPlayer();
       if(@is_numeric($this->scopes[$player->getName()])){
           $scope = $this->scopes[$player->getName()];
          
           $block = $event->getBlock();
           
           if($block->getId() == BlockIds::ITEM_FRAME_BLOCK){
               if($scope != 11){
                    if($scope != 10){$player->sendMessage("Рамка {$scope} получена, пожалуйста нажмите на ".($scope+1)." Рамку");} else {$player->sendMessage("нажмите на кнопку запуска");}
                    $this->framesetting[$scope] = $block->x. ":". $block->y. ":". $block->z;
                    $this->scopes[$player->getName()] = $scope +1;
               }
           } elseif ($block->getId() == BlockIds::WOODEN_BUTTON || $block->getId() == 77) {
               $this->scopes[$player->getName()] = "getMode";
               $player->sendMessage("Кнопка получена, желайте ли вы включить, откидывание от рамок? Если нет напишите в чат 0, Чтобы включить откидывание по оси x напишите '1', по z - '2");
               $this->framesetting = [
                   'frames' => $this->framesetting,
                   'button' => [
                       'x' => $block->x,
                       'y' => $block->y,
                       'z' => $block->z
                   ]
               ];
             
       }else if($scope != 11) {
               $player->sendMessage("Пожалуйста, нажмите на {$scope} рамку, то на что вы нажали уж совсем не похоже");
           } else {
               $player->sendMessage("Это не похоже на кнопку");
           }
           $event->setCancelled();
       }
       
    }
    public function onSettingsMessage(\pocketmine\event\player\PlayerChatEvent $event){
        
        if(@$this->scopes[$event->getPlayer()->getName()] == "getMode"){
            if(is_numeric($event->getMessage()) ){
                file_put_contents($this->getDataFolder()."frames.json", json_encode(array_merge($this->framesetting, ['mode' => $event->getMessage()])));
                $event->getPlayer()->sendMessage(TF::YELLOW.'Все рамки записаны, перезагрузка плагина...');

                 $this->getServer()->getPluginManager()->disablePlugin($this);
                 $this->getServer()->getPluginManager()->loadPlugin($this->getFile());
                 $this->getServer()->getPluginManager()->enablePlugin( $this->getServer()->getPluginManager()->getPlugin("BestCasino"));
                 $event->setCancelled();
                 
            }}
             
        }
    public function start(){
        $this->gamevariables['started'] = true;
        $this->preStart();
        $this->getServer()->getScheduler()->scheduleDelayedTask(new FillingTask($this, 1), 0.5*20);
        if ($this->rotationTask == false){
            
            $this->rotationTask = TRUE;
        $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new RotationTask($this), 0.1*20, 0.3*20);
        }
         
    }
    public function preStart(){
        foreach ($this->frames as $key => $data){
             $frameTile = $this->getTileByFrameID($key);
            if($key != 10){
                $frameTile->setItem(Item::get(90)); // Ставим во все ячейки портал 
            }else{
                $frameTile->setItem(Item::get(0));
            }
        }
    }
    public function end(\pocketmine\Player $player){
        $frames = [
            2, //по каким рамкам проверять результат
            5,
            8
        ];
        $framesTile = [];
        foreach ($frames as $frame){
            $framesTile[] = $this->getTileByFrameID($frame);
        }
        $items = [];
        foreach ($framesTile as $frame){
            $items[] = $frame->getItem()->getId();
        }
        if(($items[0] == $items[1] && $items[1] == $items[2]) && ($items[0] == $items[2])){
            $this->getTileByFrameID(10)->setItem(Item::get($items[0]));
            $block = $this->getTileByFrameID(10)->getBlock();
            //$player->getLevel()->addParticle(new \pocketmine\level\particle\GenericParticle(new Vector3($block->x, $block->y, $block->z), 23)); 
            $player->getLevel()->addSound(new \pocketmine\level\sound\GenericSound($player, 1051));
            $player->sendPopup(TF::GREEN.TF::BOLD.'Вы выиграли! Вам вручен предмет');
            $player->getInventory()->addItem(Item::get($items[0]));
        } else {
            $player->getLevel()->addSound(new \pocketmine\level\sound\DoorCrashSound($player));
             $player->sendPopup(TF::RED.TF::BOLD.'Вы проиграли! '.TF::BLUE.'((((');
        }
        $this->getServer()->getScheduler()->scheduleDelayedTask(new ClearTask($this), 5*20);
        $this->gamevariables['player'] = NULL;
        $this->gamevariables['started'] = FALSE;
    }

    public function clearALL(){
        foreach ($this->frames as $key => $data){
            $this->frames[$key]['activated'] = FALSE;
        }
    }

    public function getTileByFrameID(int $id) : ItemFrame{
        $vector = explode(':', $this->frames[$id]['vector']);
        return $this->getServer()->getDefaultLevel()->getTile(new Vector3((int) $vector[0], (int) $vector[1], (int) $vector[2]));
    }
    private function getMoney(){
        return new \Coline\Money\MoneyController($this->_getConfig()['money_plugn']);
    }
    private function _getConfig(){
        
        return $this->getConfig()->getAll();
    }
}

class RotationTask extends PluginTask{
    public function __construct(BestCasinoMain $plugin) {
        $this->plugin = $plugin;
        parent::__construct($plugin);
    }
    public function onRun($currentTick) {
        foreach ($this->plugin->frames as $frame => $data){
            if($frame != 10){
               
            $frameTile = $this->plugin->getTileByFrameID($frame);
            $frameData = $this->plugin->frames[$frame];
            $frameRotation = $frameTile->getItemRotation();
            if($frameData['activated'] == FALSE){
                 if($frameRotation == 4){
                    $frameTile->setItemRotation(0);
                } else {
                    $frameTile->setItemRotation($frameRotation + 1);
                }
               // $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new RotationTask($this->plugin), 1*20);
            }
         }
        }
    }
    
}
class FillingTask extends PluginTask{
     public function __construct(BestCasinoMain $plugin, int $frameNumber = 1) {
        $this->plugin = $plugin;
        $this->frame = $frameNumber;
        parent::__construct($plugin);
    }
    public function onRun($currentTick) {
        $frame = $this->frame;
        if($frame <= 9){
            
        /* @var $frameTile ItemFrame */
            
          $player = $this->plugin->gamevariables['player'];
          /* @var $player Player */
          if($frame <= 3){
              $player->getLevel()->addSound(new \pocketmine\level\sound\PopSound($player));
          }else if($frame <= 6 && $frame > 3 ){
              $player->getLevel()->addSound(new \pocketmine\level\sound\PopSound($player));
          }else if($frame <= 9 && $frame > 6 ){
              $player->getLevel()->addSound(new \pocketmine\level\sound\PopSound($player));
          }
              
          $frameData = $this->plugin->frames[$frame];
          $frameTile = $this->plugin->getTileByFrameID($frame);
         if($frameData['activated'] == FALSE){
            $this->plugin->frames[$frame]['activated'] = true;
             $frameTile->setItem(Item::get($this->plugin->items[mt_rand(1, count($this->plugin->items))]));
             $frameTile->setItemRotation(0);
             if($frame == 3 || $frame == 6){
                $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new FillingTask($this->plugin, $frame + 1), 0.7*20);
            }else{
             $this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new FillingTask($this->plugin, $frame + 1), 0.5*20);
            }
            if($frame + 1 == 10){
                $this->plugin->end($this->plugin->gamevariables['player']);
            }
         }
        }
    }
}
class ClearTask extends PluginTask{
    public function __construct(BestCasinoMain $plugin) {
        $this->plugin = $plugin;
        parent::__construct($plugin);
    }
    public function onRun($currentTick) {
        if( $this->plugin->gamevariables['started'] == FALSE){
            $this->plugin->clearALL();
            $this->plugin->preStart();
        }
    }
}
