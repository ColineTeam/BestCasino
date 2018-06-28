<?php

namespace Coline\Casino;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\block\BlockIds;
use pocketmine\scheduler\Task;
use pocketmine\tile\ItemFrame;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;

class BestCasinoMain extends PluginBase implements Listener {
	private $scopes = [];

	public $framesetting = [];
	public $gamevariables = ['player' => null, 'started' => false];
	public $vectors = [], $frames = [], $translation, $rotationTask = false, $buttons = [], $frames_data = [], $games = [];

	public function onLoad(){
		$this->saveDefaultConfig();
		if (@$this->_getConfig()['items'] == null) {
			$this->getConfig()->set('items', [41, 57, 42]);
			$this->getConfig()->save();
		}
		$frames = @json_decode(file_get_contents($this->getDataFolder() . "frames.json"), true);
		if (!is_null($frames)) {
			if (@$frames['frames'] == null) {
				foreach ($frames as $id => $frames_data) { //даже не спрашивайте что здесь
					$this->frames_data[$id] = $frames_data;
					$this->buttons[$frames_data['button']] = $id;
					foreach ($frames_data['frames'] as $num => $frame) {
						$this->frames[$id][$num] = ['vector' => $frame, "activated" => false];
					}
				}
			} else { //если старая версия
				$frames_save = [];
				$button = $frames['button'];
				$button = $button['x'] . ":" . $button['y'] . ":" . $button['z'];
				$frames['button'] = $button;
				$frames_save[] = $frames;
				file_put_contents($this->getDataFolder() . "frames.json", json_encode($frames_save));
				unset($frames_save);
				unset($button);
				$this->reloadPlugin();
			}
		}
	}

	public function onEnable(){
		if($this->isPhar()) (new \ColineServices\Updater($this, 195, $this->getFile()))->update();

		$this->initializeLanguage();
		if($this->getServer()->getPluginManager()->getPlugin('EconomyAPI') == null){
			$this->getLogger()->warning($this->translation->getTranslete('economy_not_find'));
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}


		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		foreach ($this->frames as $id => $frames_data) { //даже не спрашивайте что здесь
			$this->getScheduler()->scheduleDelayedTask(new ClearTask($this, $id), 5 * 20);
			foreach ($frames_data as $frame_id => $frame_data) {
				$xyz = explode(":", $frame_data['vector']);

				$this->vectors[$xyz[0] . ":" . $xyz[1] . ":" . $xyz[2]] = true;
				$this->vectors[$xyz[0] . ":" . $xyz[1] . ":" . ($xyz[2] + 1)] = true;
				$this->vectors[$xyz[0] . ":" . $xyz[1] . ":" . ($xyz[2] - 1)] = true;
				$this->vectors[$xyz[0] + 1 . ":" . $xyz[1] . ":" . ($xyz[2] + 1)] = true;
				$this->vectors[$xyz[0] - 1 . ":" . $xyz[1] . ":" . ($xyz[2] - 1)] = true;
				$this->vectors[$xyz[0] + 1 . ":" . $xyz[1] . ":" . $xyz[2]] = true;
				$this->vectors[$xyz[0] - 1 . ":" . $xyz[1] . ":" . $xyz[2]] = true;
				$this->vectors[$xyz[0] + 2 . ":" . $xyz[1] . ":" . $xyz[2]] = true;
				$this->vectors[$xyz[0] - 2 . ":" . $xyz[1] . ":" . $xyz[2]] = true;
				$this->vectors[$xyz[0] + 2 . ":" . $xyz[1] . ":" . $xyz[2]] = true;
				$this->vectors[$xyz[0] + 1 . ":" . $xyz[1] . ":" . ($xyz[2] - 1)] = true;
				$this->vectors[$xyz[0] - 1 . ":" . $xyz[1] . ":" . ($xyz[2] + 1)] = true;
			}

		}
		$this->items = $this->getConfig()->get('items');
	}

	public function Interact(\pocketmine\event\player\PlayerInteractEvent $event){
		$block = $event->getBlock();
		foreach ($this->frames as $id => $frames_data) { // не лапать рамки!
			foreach ($frames_data as $frame_id => $frame_data) {
				$vector = $frame_data['vector'];
				if ($vector == $block->x . ":" . $block->y . ":" . $block->z) {
					$event->setCancelled();
				}
			}
		}
	}

	public function onMove(\pocketmine\event\player\PlayerMoveEvent $e){
		$from = $e->getFrom();
		$to = $e->getTo();

		if (($from->getFloorX() != $to->getFloorX() || $from->getFloorZ() != $to->getFloorZ()) || $from->getFloorY() != $to->getFloorY()) {
			$player = $e->getPlayer();
			if (@$this->games[$player->getName()]['played'] != true) {
				if (@isset($this->vectors[$player->getFloorX().':'.$player->getFloorY().':'.$player->getFloorZ()])) {
                    $vector = $player->getDirectionVector();
					$player->setMotion(new Vector3(-($vector->x), -($vector->y), -($vector->z)));
				}
			}
		}
	}

	public function onStart(\pocketmine\event\player\PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();
		if ($block->getId() == BlockIds::STONE_BUTTON) {
			foreach ($this->buttons as $button => $id) {
				if ($block->getX() . ":" . $block->getY() . ":" . $block->getZ() == $button) {
					if (@is_null($this->frames[$id]['cleaning'])) {
						if (@$this->games[$player->getName()]['started'] == FALSE) {
							if ($this->getMoney()->getMoneyProviderByPlayer($player)->getMoney($this->_getConfig()['money']) >= $this->_getConfig()['money']) {
								$this->getMoney()->getMoneyProviderByPlayer($player)->reduceMoney($this->_getConfig()['money']);
								$this->games[$player->getName()]['played'] = true;
								$this->games[$player->getName()]['id'] = $id;
								$this->start($player);
								$this->clearALL($id);

							} else {
								$player->sendPopup($this->translation->getTranslete('no_money', [$this->_getConfig()['currency_name'], $this->_getConfig()['money'], $this->_getConfig()['currency_name']]));
							}
						} else {
							$player->sendMessage(TF::YELLOW . $this->translation->getTranslete('already_started'));
						}
					}
				}
			}
			if ($block->getId() == 199) {
				$event->setCancelled();
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		if (!($sender instanceof \pocketmine\command\ConsoleCommandSender)) {
			$player = $sender;
			if ($command->getName() == "settingcasino") {
				if ($player->isOp()) {
					$this->scopes[$player->getName()] = 1;
					$player->sendMessage($this->translation->getTranslete("settings_start") . PHP_EOL . "[1][4][7]" . PHP_EOL . "[2][5][8][10]" . PHP_EOL . "[3][6][9]");
				} else {
					$player->sendMessage("Я вам не доверяю");
				}
			}
		} else {
			$sender->sendMessage("Консольке привет!");
		}
		return true;
	}

	public function onSettings(\pocketmine\event\player\PlayerInteractEvent $event){
		$player = $event->getPlayer();
		if (@is_numeric($this->scopes[$player->getName()])) {
			$scope = $this->scopes[$player->getName()];

			$block = $event->getBlock();

			if ($block->getId() == BlockIds::ITEM_FRAME_BLOCK) {
				if ($scope != 11) {
					if ($scope != 10) {
						$player->sendMessage($this->translation->getTranslete('settings_step_1_start', [$scope, $scope + 1]));
					} else {
						$player->sendMessage($this->translation->getTranslete('settings_step_1_end'));
					}
					$this->framesetting[$scope] = $block->x . ":" . $block->y . ":" . $block->z;
					$this->scopes[$player->getName()] = $scope + 1;
				}
			} elseif ($block->getId() == BlockIds::WOODEN_BUTTON || $block->getId() == 77) {
				$this->framesetting = ['frames' => $this->framesetting, 'button' => $block->x . ":" . $block->y . ":" . $block->z];
				$file = @file_get_contents($this->getDataFolder() . "frames.json");
				if (!is_null($file)) {
					$write = json_decode($file, true);
				} else {
					$write = [];
				}

				$write[] = $this->framesetting;
				file_put_contents($this->getDataFolder() . "frames.json", json_encode($write));
				unset($write);
				unset($this->scopes[$player->getName()]);
				$player->sendMessage(TF::YELLOW . $this->translation->getTranslete('frames_saved'));

				$this->reloadPLugin();
				$event->setCancelled();

			} else if ($scope != 11) {
				$player->sendMessage($this->translation->getTranslete('not_frame', [$scope]));
			} else {
				$player->sendMessage($this->translation->getTranslete('not_button'));
			}
			$event->setCancelled();
		}

	}

	public function start($player){
		$this->games[$player->getName()]['started'] = true;
		$this->preStart($this->games[$player->getName()]['id']);
		$this->getScheduler()->scheduleDelayedTask(new FillingTask($this, 1, $player), 0.5 * 20);
		if (@$this->rotationTask[$this->games[$player->getName()]['id']] == false) {

			$this->rotationTask[$this->games[$player->getName()]['id']] = TRUE;
			$this->getScheduler()->scheduleDelayedRepeatingTask(new RotationTask($this, $this->games[$player->getName()]['id']), 0.1 * 20, 0.5 * 20);
		}

	}

	public function preStart($id){
		foreach ($this->frames[$id] as $key => $data) {
			if (is_numeric($key)) {
				$frameTile = $this->getTileByFrameID($id, $key);
				if ($key != 10) {
					$frameTile->setItem(Item::get(90)); // Ставим во все ячейки портал
				} else {
					$frameTile->setItem(Item::get(0));
				}
			}
		}
	}

	public function end(\pocketmine\Player $player){
		$frames = [2, //по каким рамкам проверять результат
			5, 8];
		$framesTile = [];
		$id = $this->games[$player->getName()]['id'];
		foreach ($frames as $frame) {
			$framesTile[] = $this->getTileByFrameID($id, $frame);
		}
		$items = [];
		foreach ($framesTile as $frame) {
			$items[] = $frame->getItem()->getId();
		}
		if (($items[0] == $items[1] && $items[1] == $items[2]) && ($items[0] == $items[2])) {
			$this->getTileByFrameID($id, 10)->setItem(Item::get($items[0]));
			$block = $this->getTileByFrameID($id, 10)->getBlock();
			//$player->getLevel()->addParticle(new \pocketmine\level\particle\GenericParticle(new Vector3($block->x, $block->y, $block->z), 23));
			$player->getLevel()->addSound(new \pocketmine\level\sound\GenericSound($player, 1051));
			$player->sendPopup(TF::GREEN . TF::BOLD . $this->translation->getTranslete('game_win'));
			$player->getInventory()->addItem(Item::get($items[0]));
		} else {
			$player->getLevel()->addSound(new \pocketmine\level\sound\EndermanTeleportSound($player));
			$player->sendPopup(TF::RED . TF::BOLD . $this->translation->getTranslete('game_lost') . ' ' . TF::BLUE . '((((');
		}
		$this->frames[$id]['cleaning'] = true;
		$this->getScheduler()->scheduleDelayedTask(new ClearTask($this, $id), 5 * 20);
		$this->rotationTask[$this->games[$player->getName()]['id']] = TRUE;
		unset($this->games[$player->getName()]);
	}

	public function clearALL($id){
		foreach ($this->frames[$id] as $key => $data) {
			if (is_numeric($key)) $this->frames[$id][$key]['activated'] = FALSE;
		}
	}

	public function getTileByFrameID(int $id, int $frame_id): ItemFrame{
		$vector = explode(':', $this->frames[$id][$frame_id]['vector']);
		return $this->getServer()->getDefaultLevel()->getTile(new Vector3((int)$vector[0], (int)$vector[1], (int)$vector[2]));
	}
	private function reloadPlugin(){
		$this->getServer()->getPluginManager()->disablePlugin($this);
		$this->getServer()->getPluginManager()->loadPlugin($this->getFile());
		$this->getServer()->getPluginManager()->enablePlugin($this->getServer()->getPluginManager()->getPlugin("BestCasino"));
	}

	private function initializeLanguage(){
		$lang = @$this->_getConfig()['lang'];
		if (is_null($lang)) {
			$serverLang = $this->getServer()->getProperty("settings.language");
			switch ($serverLang) {
				case "eng":
					$lang = "eng";
					break;
				default:
					$lang = "rus";
					break;
			}
			$this->getConfig()->set('lang', $lang);
			$this->getConfig()->save();
		}
		$file = $lang . '.json';
		$this->saveResource($file);
		$phrases = json_decode(file_get_contents($this->getDataFolder() . $file), true);
		$this->translation = new \ColineServices\TranslationContainer($phrases);
	}
	private function getMoney(){
		return new \Coline\Money\MoneyController($this->_getConfig()['money_plugn']);
	}

	private function _getConfig(){
		return $this->getConfig()->getAll();
	}
}

class RotationTask extends Task {
	public function __construct(BestCasinoMain $plugin, $id){
		$this->plugin = $plugin;
		$this->id = $id;
	}

	public function onRun($currentTick){
		foreach ($this->plugin->frames[$this->id] as $frame => $data) {
			if (is_numeric($frame)) {
				if ($frame != 10) {
					$frameTile = $this->plugin->getTileByFrameID($this->id, $frame);
					$frameData = $data;
					$frameRotation = $frameTile->getItemRotation();
					if ($frameData['activated'] == FALSE) {
						if ($frameRotation == 4) {
							$frameTile->setItemRotation(0);
						} else {
							$frameTile->setItemRotation($frameRotation + 1);
						}
						// $this->plugin->getScheduler()->scheduleDelayedTask(new RotationTask($this->plugin), 1*20);
					}
				}
			}
		}
	}

}

class FillingTask extends Task {
	public function __construct(BestCasinoMain $plugin, int $frameNumber = 1, Player $player){
		$this->plugin = $plugin;
		$this->frame = $frameNumber;
		$this->player = $player;

	}

	public function onRun($currentTick){
		$frame = $this->frame;
		if ($frame <= 9) {

			/* @var $frameTile ItemFrame */

			$player = $this->player;
			/* @var $player Player */
			if ($frame <= 3) {
				$player->getLevel()->addSound(new \pocketmine\level\sound\PopSound($player));
			} else if ($frame <= 6 && $frame > 3) {
				$player->getLevel()->addSound(new \pocketmine\level\sound\PopSound($player));
			} else if ($frame <= 9 && $frame > 6) {
				$player->getLevel()->addSound(new \pocketmine\level\sound\PopSound($player));
			}
			$id = $this->plugin->games[$player->getName()]['id'];
			$frameData = $this->plugin->frames[$id][$frame];
			$frameTile = $this->plugin->getTileByFrameID($id, $frame);
			if ($frameData['activated'] == FALSE) {
				$this->plugin->frames[$id][$frame]['activated'] = true;
				$frameTile->setItem(Item::get($this->plugin->items[mt_rand(0, count($this->plugin->items) - 1)]));
				$frameTile->setItemRotation(0);
				if ($frame == 3 || $frame == 6) {
					$this->plugin->getScheduler()->scheduleDelayedTask(new FillingTask($this->plugin, $frame + 1, $this->player), 0.7 * 20);
				} else {
					$this->plugin->getScheduler()->scheduleDelayedTask(new FillingTask($this->plugin, $frame + 1, $this->player), 0.5 * 20);
				}
				if ($frame + 1 == 10) {
					$this->plugin->end($this->player);
				}
			}
		}
	}
}

class ClearTask extends Task {
	public function __construct(BestCasinoMain $plugin, $id){
		$this->plugin = $plugin;
		$this->id = $id;
	}

	public function onRun($currentTick){
		if (true) {
			$this->plugin->clearALL($this->id);
			$this->plugin->preStart($this->id);
			unset($this->plugin->frames[$this->id]['cleaning']);
		}
	}
}
