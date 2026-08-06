<?php

require_once __DIR__.'/Hero.php';

class AutomationSinglePlayer {

    public function AutomationSinglePlayer() {
        global $database, $session;
        //send NPC hero to adventure and sell items on auction
        if (!file_exists("GameEngine/Prevention/adventures.txt") or time() - filemtime("GameEngine/Prevention/adventures.txt") > 50) {
            $adv_time = 86400 / ADVENTURE_SPEED;
            if ($database->hasActiveAdventures( $adv_time, $session->uid )) {
                $this->sendAdventuresComplete();
            }
        }
    }

    private function createNpcHeroIfNotExists($uid) {
        global $database;
        $getHero = $database->getHero($uid);
        if ($getHero == false) {
            $database->addHero($uid);
        }
    }

    private function sendAdventuresComplete() {
        if (file_exists("GameEngine/Prevention/adventures.txt")) {
            unlink("GameEngine/Prevention/adventures.txt");
        }
        $traderUid = 2;
        $this->createNpcHeroIfNotExists($traderUid);

        global $database, $session;
        $time = time();
        $tribe = $database->getUserField($session->uid, 'tribe', 0);
        if ($tribe < 1 || $tribe > 3) {
            $tribe = 1;
        }

        $ownerID = $traderUid; //Natars uid =2

        $btype = rand(0, 15);
        $ntype = heroAdventureItemTypes($btype, $tribe, $time - COMMENCE);

        if ($btype >= 7) {
            $nntype = heroAdventureConsumableType($btype);
            if ($btype == 9) {
                $num = rand(6, 20);
            } elseif ($btype == 12 or $btype == 13 or $btype == 15) {
                $num = 1;
            } else {
                $num = rand(20, 50);
            }
            /* if($btype <= 11 or $btype >= 14) {
              if($database->checkHeroItem($ownerID, $btype)) {
              $id = $database->getHeroItemID($ownerID, $btype);
              $database->editHeroNum($id, $num, 1);
              } else {
              $database->addHeroItem($ownerID, $btype, $nntype, $num);
              }
              } else { */
            $database->addHeroItem($ownerID, $btype, $nntype, $num);
            /* } */
        } else {
            if (!empty($ntype)) {
                $num = 1;
                $nntype = $ntype[array_rand($ntype)];
                $database->addHeroItem($ownerID, $btype, $nntype, $num);
            }
        }
        $q = "SELECT * FROM " . TB_PREFIX . "heroitems WHERE uid = '$ownerID' and proc = 0";
        $dataarray = $database->query_return($q);
        foreach ($dataarray as $data) {
            $database->addAuction($ownerID, $data['id'], $data['btype'], $data['type'], $data['num']);
        }

        if (file_exists("GameEngine/Prevention/adventures.txt")) {
            unlink("GameEngine/Prevention/adventures.txt");
        }
    }

}

$automationSinglePlayer = new AutomationSinglePlayer;
?>
