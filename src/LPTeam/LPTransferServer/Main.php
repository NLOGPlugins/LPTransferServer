<?php

namespace LPTeam\LPTransferServer;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\Player;
use pocketmine\command\PluginCommand;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign as TileSign;
use pocketmine\utils\Config;
use pocketmine\scheduler\PluginTask;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\BlockBreakEvent;

class Main extends PluginBase implements Listener {
	
	/** @var array */
	private $list = [];
	
	/** @var Config */
	private $db;
	
	/** @var array */
	private $yml;
	
	public $tag = "§b§o[ TransferServer ] §7";

  public function onEnable() {
  	$this->registerCommand("서버", "op");
  	
  	@mkdir($this->getDataFolder());
  	$this->db = new Config($this->getDataFolder() . "database.yml", Config::YAML, []);
  	$this->yml = $this->db->getAll();
  	$this->getServer()->getScheduler()->scheduleRepeatingTask(new class($this) extends PluginTask {
  		public function onRun(int $currentTick) {
  			$this->owner->save();
  		}
  	}, 30);
  	$this->getLogger()->info("LPTransferServer 플러그인 활성화 | Made by LP-TEAM");
	$this->getServer()->getPluginManager()->registerEvents($this, $this);
  }
  
  public function registerCommand(string $name, string $permission, string $description = "", string $usage = "", bool $force = false, array $aliases= []) {
  	if ($force && $this->getServer()->getCommandMap()->getCommand($name) instanceof Command) {
  		$this->getServer()->getCommandMap()->getCommand($name)->unregister($this->getServer()->getCommandMap());
  	}
  	$command = new PluginCommand($name, $this);
  	$command->setLabel($name);
  	$command->setPermission($permission);
  	$command->setDescription($description);
  	$command->setUsage($usage);
  	$command->setAliases($aliases);
  	
  	$this->getServer()->getCommandMap()->register($this->getDescription()->getName(), $command);
  }
  
  public function asString(Vector3 $vector3) {
  	return $vector3->x . ":" . $vector3->y .  ":" . $vector3->z;
  }
  
  public function save() {
  	$this->db->setAll($this->yml);
  	$this->db->save();
  }
  
  /**
   * 커맨드의 label과 args에 공백을 정렬하는 소스입니다.
   * - Made by 엔로그 (nnnlog, NLOG)
   * 
   * @param string $label
   * @param array $args
   * @return array
   */
  public function lineup(string $label,array $args) {
  	$explode = explode(" ", $label);
  	$cmd = "";
  	if ($explode !== false) {
  		$label = $explode[0];
  		unset($explode[0]);
  		$i = 0;
  		foreach ($explode as $word) {
  			if ($word !== "") {
  				$cmd .= $word;
  				if($i === 0) {
  					$i++;
  					$cmd .= " ";
  				}
  			}
  		}
  	}
  	if ($cmd !== "") {
	  	$array = [];
	  	$args[0] = $cmd . " " . $args[0];
	  	foreach ($args as $k => $v) {
	  		$explode = explode(" ", $args[$k]);
	  		if ($explode !== false) {
	  			foreach ($explode as $v1) {
	  				if ($v1 === "") {
	  					continue;
	  				}
	  				$array[] = $v1;
	  			}
	  		}
	  	}
  	}elseif (!empty($args)) {
  		$array = [];
  		foreach ($args as $k => $v) {
  			$explode = explode(" ", $args[$k]);
  			if ($explode !== false) {
  				foreach ($explode as $v1) {
  					if ($v1 === "") {
  						continue;
  					}
  					$array[] = $v1;
  				}
  			}
  		}
  	}
  	
  	return ["label" => $label,"args" => $array];
  }
  
  public function transferServer($ip, $port,Player $player) {
  	$pk = new TransferPacket();
  	$pk->address = $ip;
  	$pk->port = $port;
  	
  	$player->dataPacket($pk);
  }
  
  public function onInteract (PlayerInteractEvent $ev) {
  	$name = $ev->getPlayer()->getName();
  	$sign = $ev->getBlock()->getLevel()->getTile($ev->getBlock());
  	if ($sign instanceof TileSign && isset($this->list[$name])) {
  		if (isset($this->yml[$this->asString($ev->getBlock()->asVector3())])) {
  			$ev->getPlayer()->sendMessage($this->tag . "서버이동 표지판은 수정하실 수 없습니다.");
  			return;
  		}
  		$ip = explode(":", $this->list[$name])[0];
  		$port = explode(":", $this->list[$name])[1];
  		$sign->setLine(0, "§l§d[ §f서버이동 §d]");
  		$sign->setLine(1, "§bIP : " . $ip);
  		$sign->setLine(2, "§cPort : " . $port);
  		$sign->setLine(3, "");
  		$sign->saveNBT();
  		
  		$this->yml[$this->asString($ev->getBlock()->asVector3())] = $ip . ":" . $port;
  		$this->save();
  		unset($this->list[$name]);
  		
  		$ev->getPlayer()->sendMessage($this->tag . "성공적으로 생성하였습니다.");
  		return;
  	}
  	if (isset($this->yml[$this->asString($ev->getBlock()->asVector3())])) {
  		$ip = explode(":", $this->yml[$this->asString($ev->getBlock()->asVector3())])[0];
  		$port = explode(":", $this->yml[$this->asString($ev->getBlock()->asVector3())])[1];
  		$ev->getPlayer()->sendMessage($this->tag . "서버를 이동하였습니다.");
  		$this->transferServer($ip, $port, $ev->getPlayer());
  		return;
  	}
  }
  
  public function onBreak (BlockBreakEvent $ev) {
  	$name = $ev->getPlayer()->getName();
  	$sign = $ev->getBlock()->getLevel()->getTile($ev->getBlock()->asVector3());
  	if ($sign instanceof TileSign && isset($this->yml[$this->asString($ev->getBlock()->asVector3())])) {
  		if ($ev->getPlayer()->isOp()) {
  			unset($this->yml[$this->asString($ev->getBlock()->asVector3())]);
  			$this->save();
  			$ev->getPlayer()->sendMessage($this->tag . "표지판을 제거하였습니다.");
  			return;
  		}else{
  			$ev->getPlayer()->sendMessage($this->tag . "권한이 없습니다.");
  			return;
  		}
  	}
  }
  
  public function onQuit(PlayerQuitEvent $ev) {
  	$name = $ev->getPlayer()->getName();
  	if (in_array($name, $this->list)) {
  		unset($this->list[$name]);
  	}
  }
  
  public function onCommand(CommandSender $sender,Command $command,string $label,array $args): bool {
  	if (!$sender instanceof Player) {
  		$sender->sendMessage("콘솔에서는 명령어를 입력하실 수 없습니다.");
  		return true;
  	}
  	
  	$lineup = $this->lineup($label, $args);
  	$label = $lineup["label"];
  	$args = $lineup["args"];
  	
  	$name = $sender->getName();
  	
  	switch ($args[0]) {
  		case "이동":
  			$ipport = explode(":", $args[1] ?? "null");
  			if (!$ipport || count($ipport) !== 2) {
  				$sender->sendMessage($this->tag . "/$label 이동 <ip:port> [플레이어 이름] - 서버를 이동합니다.");
  				break;
  			}
  			
  			$ip = $ipport[0];
  			$port = $ipport[1];
  			if ($args[2] ?? null !== null) {
  				$player = $this->getServer()->getPlayer($args[2]);
  				if ($player instanceof Player) {
  					if ($player->isOnline()) {
  						$this->transferServer($ip, $port, $player);
  						$sender->sendMessage($this->tag . "{$player->getName()}님을 다른 서버로 이동시켰습니다.");
  						break;
  					}else{
  						$sender->sendMessage($this->tag . "{$player->getName()}님은 현재 온라인 상태가 아닙니다.");
  						break;
  					}
  				}else{
  					$sender->sendMessage($this->tag . "존재하지 않는 플레이어입니다.");
  					break;
  				}
  			}
  			
  			$sender->sendMessage($this->tag . "서버를 이동하였습니다.");
			$this->transferServer($ip, $port, $sender);
  			break;
  		case "생성":
  			if (isset($this->list[$name])) {
  				$sender->sendMessage($this->tag . "이미 작업 중입니다.");
  				break;
  			}
  			$ipport = explode(":", $args[1] ?? "null");
  			if (!$ipport || count($ipport) !== 2) {
  				$sender->sendMessage($this->tag . "/$label 생성 <ip:port> - 서버 이동 표지판을 생성합니다.");
  				break;
  			}
  			
  			$ip = $ipport[0];
  			$port = $ipport[1];
  			$this->list[$name] = $ip . ":" . $port;
  			$sender->sendMessage($this->tag . "표지판을 터치하세요.");
  			break;
  		case "생성취소":
  			if (isset($this->list[$name])) {
  				$sender->sendMessage($this->tag . "작업을 하고 있지 않습니다.");
  				break;
  			}
  			unset($this->list[$name]);
  			$sender->sendMessage($this->tag . "작업을 취소하였습니다.");
  			break;
  		default:
  			$sender->sendMessage($this->tag . "/$label 이동 <ip:port> [플레이어 이름] - 서버를 이동합니다.");
  			$sender->sendMessage($this->tag . "/$label 생성 <ip:port> - 서버 이동 표지판을 생성합니다.");
  			$sender->sendMessage($this->tag . "/$label 생성취소 - 작업을 취소합니다.");
  			break;
  	}
  	
  	return true;
  }
 

}//클래스 괄호

?>
