<?php

declare(strict_types=1);

namespace MessageManager1;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener{

    public function onEnable() : void{
        $this->saveDefaultConfig();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->updateMotd();

        $seconds = (int)$this->getConfig()->getNested("motd.update-seconds", 30);

        $this->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() : void{
                $this->updateMotd();
            }),
            $seconds * 20
        );
    }

    private function updateMotd() : void{
        if(!$this->getConfig()->getNested("motd.enabled", true)){
            return;
        }

        $line1 = $this->replacePlaceholders(
            $this->getConfig()->getNested("motd.line1")
        );

        $line2 = $this->replacePlaceholders(
            $this->getConfig()->getNested("motd.line2")
        );

        $this->getServer()->getNetwork()->setName(
            TextFormat::colorize($line1 . "\n" . $line2)
        );
    }

    public function onJoin(PlayerJoinEvent $event) : void{
        $player = $event->getPlayer();

        $event->setJoinMessage(
            TextFormat::colorize(
                $this->replacePlaceholders(
                    $this->getConfig()->getNested("messages.join"),
                    $player
                )
            )
        );

        if(!$player->hasPlayedBefore()){

            $broadcast = TextFormat::colorize(
                $this->replacePlaceholders(
                    $this->getConfig()->getNested("messages.first-join-broadcast"),
                    $player
                )
            );

            $this->getServer()->broadcastMessage($broadcast);

            foreach($this->getConfig()->getNested("messages.first-join-message") as $line){
                $player->sendMessage(
                    TextFormat::colorize(
                        $this->replacePlaceholders($line, $player)
                    )
                );
            }
        }
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        $event->setQuitMessage(
            TextFormat::colorize(
                $this->replacePlaceholders(
                    $this->getConfig()->getNested("messages.quit"),
                    $event->getPlayer()
                )
            )
        );
    }

    public function onKick(PlayerKickEvent $event) : void{

        if(
            $this->getConfig()->getNested("settings.custom-whitelist-message", true)
            && str_contains(strtolower($event->getReason()), "whitelist")
        ){
            $event->setReason(
                TextFormat::colorize(
                    $this->getConfig()->getNested("messages.whitelist")
                )
            );
        }

        if(
            $this->getConfig()->getNested("settings.custom-full-message", true)
            && (
                str_contains(strtolower($event->getReason()), "full") ||
                str_contains(strtolower($event->getReason()), "server full")
            )
        ){
            $event->setReason(
                TextFormat::colorize(
                    $this->getConfig()->getNested("messages.server-full")
                )
            );
        }
    }

    private function replacePlaceholders(string $text, ?Player $player = null) : string{

        $dateEnabled = $this->getConfig()->getNested("date-time.enabled", true);

        $date = "";
        $time = "";

        if($dateEnabled){
            $date = date(
                $this->getConfig()->getNested(
                    "date-time.date-format",
                    "Y-m-d"
                )
            );

            $time = date(
                $this->getConfig()->getNested(
                    "date-time.time-format",
                    "H:i:s"
                )
            );
        }

        return str_replace(
            [
                "{player}",
                "{online}",
                "{max}",
                "{date}",
                "{time}"
            ],
            [
                $player?->getName() ?? "",
                (string)count($this->getServer()->getOnlinePlayers()),
                (string)$this->getServer()->getMaxPlayers(),
                $date,
                $time
            ],
            $text
        );
    }

    public function onCommand(
        CommandSender $sender,
        Command $command,
        string $label,
        array $args
    ) : bool{

        if($command->getName() === "mmreload"){

            $this->reloadConfig();

            $sender->sendMessage(
                TextFormat::GREEN . "MessageManager1 reloaded."
            );

            $this->updateMotd();

            return true;
        }

        return false;
    }
}
