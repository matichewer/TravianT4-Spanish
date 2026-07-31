<?php
//author: https://github.com/Lorex
        class mysqli_DB {
        	var $connection;

        	function __construct() {
				$this->connection = mysqli_connect(SQL_SERVER, SQL_USER, SQL_PASS);
				mysqli_set_charset($this->connection, 'utf8mb4');
				mysqli_select_db($this->connection, SQL_DB);
        	}

        	function register($username, $password, $email, $tribe, $locate, $act) {
        		$time = time();
				$stime = strtotime(START_DATE)-strtotime(date('m/d/Y'))+strtotime(START_TIME);
				if($stime > time()){
				$time = $stime;
				}
				$timep = $time + PROTECTION;
        		$q = "INSERT INTO " . TB_PREFIX . "users (username,password,access,email,timestamp,tribe,location,act,protect,fquest,cp) VALUES ('$username', '$password', " . USER . ", '$email', $time, $tribe, $locate, '$act', $timep, '0,0,0,0,0,0,0,0,0,0,0', 1)";
        		if(mysqli_query($this->connection,$q)) {
        			return mysqli_insert_id($this->connection);
        		} else {
        			return false;
        		}
        	}

        	function activate($username, $password, $email, $tribe, $locate, $act, $act2) {
        		$time = time();
        		$q = "INSERT INTO " . TB_PREFIX . "activate (username,password,access,email,tribe,timestamp,location,act,act2) VALUES ('$username', '$password', " . USER . ", '$email', $tribe, $time, $locate, '$act', '$act2')";
        		if(mysqli_query($this->connection,$q)) {
        			return mysqli_insert_id($this->connection);
        		} else {
        			return false;
        		}
        	}

        	function unreg($username) {
        		$q = "DELETE from " . TB_PREFIX . "activate where username = '$username'";
        		return mysqli_query($this->connection,$q);
        	}
        	function deleteReinf($id) {
        		$q = "DELETE from " . TB_PREFIX . "enforcement where id = '$id'";
        		mysqli_query($this->connection,$q);
        	}
        	function updateResource($vid, $what, $number) {

        		$q = "UPDATE " . TB_PREFIX . "vdata set " . $what . "=" . $number . " where wref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_query($this->connection,$q);
        	}

        	function checkExist($ref, $mode) {

        		if(!$mode) {
        			$q = "SELECT username FROM " . TB_PREFIX . "users where username = '$ref' LIMIT 1";
        		} else {
        			$q = "SELECT email FROM " . TB_PREFIX . "users where email = '$ref' LIMIT 1";
        		}
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function checkExist_activate($ref, $mode) {

        		if(!$mode) {
        			$q = "SELECT username FROM " . TB_PREFIX . "activate where username = '$ref' LIMIT 1";
        		} else {
        			$q = "SELECT email FROM " . TB_PREFIX . "activate where email = '$ref' LIMIT 1";
        		}
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}
        	function updateUserField($ref, $field, $value, $mode) {
        		if(!$mode) {
        			$q = "UPDATE " . TB_PREFIX . "users set $field = '$value' where username = '$ref'";
        		} elseif($mode==1) {
        			$q = "UPDATE " . TB_PREFIX . "users set $field = '$value' where id = '$ref'";
        		} elseif($mode==2) {
					$q = "UPDATE " . TB_PREFIX . "users set $field = $field + '$value' where id = '$ref'";
				} elseif($mode==3) {
					$q = "UPDATE " . TB_PREFIX . "users set $field = $field - '$value' where id = '$ref'";
				}
        		return mysqli_query($this->connection,$q);
        	}

        	function getSitee($uid) {
        		$q = "SELECT id from " . TB_PREFIX . "users where sit1 = $uid or sit2 = $uid";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

			function getSitee1($uid) {
        		$q = "SELECT * from " . TB_PREFIX . "users where sit1 = $uid";
        		$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
        		return $dbarray;
        	}

			function getSitee2($uid) {
        		$q = "SELECT * from " . TB_PREFIX . "users where sit2 = $uid";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray;
        	}

        	function removeMeSit($uid, $uid2) {
        		$q = "UPDATE " . TB_PREFIX . "users set sit1 = 0 where id = $uid and sit1 = $uid2";
        		mysqli_query($this->connection,$q);
        		$q2 = "UPDATE " . TB_PREFIX . "users set sit2 = 0 where id = $uid and sit2 = $uid2";
        		mysqli_query($this->connection,$q2);
        	}

        	function getUserField($ref, $field, $mode) {
        		if(!$mode) {
        			$q = "SELECT $field FROM " . TB_PREFIX . "users where id = '$ref'";
        		} else {
        			$q = "SELECT $field FROM " . TB_PREFIX . "users where username = '$ref'";
        		}
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray[$field];
        	}

			function getInvitedUser($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "users where invited = $uid order by regtime desc";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getStarvation(){
                    $q = "SELECT * FROM " . TB_PREFIX . "vdata where starv != 0";
                    $result = mysqli_query($this->connection,$q);
                    return $this->mysqli_fetch_all($result);
            }

        	function getActivateField($ref, $field, $mode) {
        		if(!$mode) {
        			$q = "SELECT $field FROM " . TB_PREFIX . "activate where id = '$ref'";
        		} else {
        			$q = "SELECT $field FROM " . TB_PREFIX . "activate where username = '$ref'";
        		}
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray[$field];
        	}

        	function login($username, $password) {
        		$q = "SELECT password,sessid FROM " . TB_PREFIX . "users where username = '$username'";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		if($dbarray['password'] == md5($password)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function checkActivate($act) {
        		$q = "SELECT * FROM " . TB_PREFIX . "activate where act = '$act'";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);

        		return $dbarray;
        	}

        	function sitterLogin($username, $password) {
        		$q = "SELECT sit1,sit2 FROM " . TB_PREFIX . "users where username = '$username' and access != " . BANNED;
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		if($dbarray['sit1'] != 0) {
        			$q2 = "SELECT password FROM " . TB_PREFIX . "users where id = " . $dbarray['sit1'] . " and access != " . BANNED;
        			$result2 = mysqli_query($this->connection,$q2);
        			$pw_sit1 = mysqli_fetch_array($result2);
        		}
				if($dbarray['sit2'] != 0) {
        			$q3 = "SELECT password FROM " . TB_PREFIX . "users where id = " . $dbarray['sit2'] . " and access != " . BANNED;
        			$result3 = mysqli_query($this->connection,$q3);
        			$pw_sit2 = mysqli_fetch_array($result3);
        		}
        		if($dbarray['sit1'] != 0 || $dbarray['sit2'] != 0) {
        			if($pw_sit1['password'] == md5($password) || $pw_sit2['password'] == md5($password)) {
        				return true;
        			} else {
        				return false;
        			}
        		} else {
        			return false;
        		}
        	}

        	function setDeleting($uid, $mode) {
        		$time = time() + 72 * 3600;
        		if(!$mode) {
        			$q = "INSERT into " . TB_PREFIX . "deleting values ($uid,$time)";
        		} else {
        			$q = "DELETE FROM " . TB_PREFIX . "deleting where uid = $uid";
        		}
        		mysqli_query($this->connection,$q);
        	}

        	function isDeleting($uid) {
        		$q = "SELECT timestamp from " . TB_PREFIX . "deleting where uid = $uid";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['timestamp'];
        	}

			function modifyGold($userid, $amt, $mode) {
				if(!$mode) {
					$q = "UPDATE " . TB_PREFIX . "users set gold = gold - $amt where id = $userid";
        		} else {
        			$q = "UPDATE " . TB_PREFIX . "users set gold = gold + $amt where id = $userid";
        		}
				return mysqli_query($this->connection,$q);
			}

			function redistributeResourcesWithGold($userid, $vid, $wood, $clay, $iron, $crop, $goldCost) {
				$userid = (int) $userid;
				$vid = (int) $vid;
				$wood = (int) $wood;
				$clay = (int) $clay;
				$iron = (int) $iron;
				$crop = (int) $crop;
				$goldCost = (int) $goldCost;
				if($userid <= 0 || $vid <= 0 || $goldCost <= 0
					|| $wood < 0 || $clay < 0 || $iron < 0 || $crop < 0) {
					return false;
				}
				// margen de 1 por el redondeo de las columnas float(12,2)
				$total = $wood + $clay + $iron + $crop - 1;
				$q = "UPDATE " . TB_PREFIX . "vdata v INNER JOIN " . TB_PREFIX . "users u ON u.id = $userid AND v.owner = u.id SET v.wood = $wood, v.clay = $clay, v.iron = $iron, v.crop = $crop, u.gold = u.gold - $goldCost WHERE v.wref = $vid AND u.gold >= $goldCost AND (v.wood + v.clay + v.iron + v.crop) >= $total";
				$result = mysqli_query($this->connection,$q);
				return $result && mysqli_affected_rows($this->connection) >= 1;
			}

        	/*****************************************
        	Function to retrieve user array via Username or ID
        	Mode 0: Search by Username
        	Mode 1: Search by ID
        	References: Alliance ID
        	*****************************************/

        	function getUserArray($ref, $mode) {
        		if(!$mode) {
        			$q = "SELECT * FROM " . TB_PREFIX . "users where username = '$ref'";
        		} else {
        			$q = "SELECT * FROM " . TB_PREFIX . "users where id = $ref";
        		}
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getUserWithEmail($email) {
        		$q = "SELECT * FROM " . TB_PREFIX . "users where email = '$email'";
				$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

        	function activeModify($username, $mode) {
        		$time = time();
        		if(!$mode) {
        			$q = "INSERT into " . TB_PREFIX . "active VALUES ('$username',$time)";
        		} else {
        			$q = "DELETE FROM " . TB_PREFIX . "active where username = '$username'";
        		}
        		return mysqli_query($this->connection,$q);
        	}

        	function addActiveUser($username, $time) {
        		$q = "REPLACE into " . TB_PREFIX . "active values ('$username',$time)";
        		if(mysqli_query($this->connection,$q)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function updateActiveUser($username, $time) {
				$q = "REPLACE into " . TB_PREFIX . "active (`username`, `timestamp`) values ('$username',$time)";
        		$q2 = "UPDATE " . TB_PREFIX . "users set timestamp = $time where username = '$username'";
        		$exec1 = mysqli_query($this->connection,$q);
        		$exec2 = mysqli_query($this->connection,$q2);
        		if($exec1 && $exec2) {
        			return true;
        		} else {
        			return false;
        		}
        	}

			function checkSitter($username){
				$q = "SELECT * FROM ".TB_PREFIX."online WHERE name = '".$username."'";
				$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				return $dbarray['sitter'];
			}

			public function canConquerOasis($vref,$wref) {
				$AttackerFields = $this->getResourceLevel($vref);
				for($i=19;$i<=38;$i++) {
					if($AttackerFields['f'.$i.'t'] == 37) { $HeroMansionLevel = $AttackerFields['f'.$i]; }
				}
				if($this->VillageOasisCount($vref) < floor(($HeroMansionLevel-5)/5)) {
					$OasisInfo = $this->getOasisInfo($wref);
                    $troopcount = $this->countOasisTroops($wref);
					if($OasisInfo['conqured'] == 0 || $OasisInfo['conqured'] != 0 && $OasisInfo['loyalty'] < 99 / min(3,(4-$this->VillageOasisCount($OasisInfo['conqured']))) && $troopcount == 0) {
						$CoordsVillage = $this->getCoor($vref);
						$CoordsOasis = $this->getCoor($wref);
						if(abs($CoordsOasis['x']-$CoordsVillage['x'])<=3 && abs($CoordsOasis['y']-$CoordsVillage['y'])<=3) {
							return True;
						} else {
							return False;
						}
					} else {
						return False;
					}
				} else {
					return False;
				}
			}

			public function conquerOasis($vref,$wref) {
				$vinfo = $this->getVillage($vref);
				$uid = $vinfo['owner'];
				$q = "UPDATE `".TB_PREFIX."odata` SET conqured=$vref,loyalty=100,lastupdated=".time().",lastupdated2=".time().",owner=$uid,name='Occupied Oasis' WHERE wref=$wref";
        		return mysqli_query($this->connection,$q);
			}

			public function modifyOasisLoyalty($wref) {
				if($this->isVillageOases($wref) != 0) {
					$OasisInfo = $this->getOasisInfo($wref);
					if($OasisInfo['conqured'] != 0) {
						$LoyaltyAmendment = floor(100 / min(3,(4-$this->VillageOasisCount($OasisInfo['conqured']))));
						$q = "UPDATE `".TB_PREFIX."odata` SET loyalty=loyalty-$LoyaltyAmendment WHERE wref=$wref";
						return mysqli_query($this->connection,$q);
					}
				}
			}

        	function checkactiveSession($username, $sessid) {
        		$user = $this->getUserArray($username, 0);
				$sessidarray = explode("+", $user['sessid']);
        		if(in_array($sessid, $sessidarray)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

		function addActiveSession($username, $sessid) {
			$username = mysqli_real_escape_string($this->connection, $username);
			$sessid = mysqli_real_escape_string($this->connection, $sessid);
			$q = "UPDATE " . TB_PREFIX . "users
				SET sessid = CASE
					WHEN FIND_IN_SET('$sessid', REPLACE(sessid, '+', ',')) > 0 THEN sessid
					ELSE SUBSTRING_INDEX(CONCAT_WS('+', NULLIF(sessid, ''), '$sessid'), '+', -20)
				END
				WHERE username = '$username'";
			return mysqli_query($this->connection, $q);
		}

		function removeActiveSession($username, $sessid) {
			$username = mysqli_real_escape_string($this->connection, $username);
			$sessid = mysqli_real_escape_string($this->connection, $sessid);
			$q = "UPDATE " . TB_PREFIX . "users
				SET sessid = TRIM(BOTH '+' FROM REPLACE(CONCAT('+', sessid, '+'), '+$sessid+', '+'))
				WHERE username = '$username'";
			return mysqli_query($this->connection, $q);
		}

        	function submitProfile($uid, $gender, $location, $birthday, $des1, $des2) {
        		$q = "UPDATE " . TB_PREFIX . "users set gender = $gender, location = '$location', birthday = '$birthday', desc1 = '$des1', desc2 = '$des2' where id = $uid";
        		return mysqli_query($this->connection,$q);
        	}

        	function gpack($uid, $gpack) {
        		$q = "UPDATE " . TB_PREFIX . "users set gpack = '$gpack' where id = $uid";
        		return mysqli_query($this->connection,$q);
        	}

        	function UpdateOnline($mode, $name = "", $sit = 0) {
        		global $session;
        		if($mode == "login") {
        			$q = "INSERT IGNORE INTO " . TB_PREFIX . "online (name, time, sitter) VALUES ('$name', ".time().", ".$sit.")";
        			return mysqli_query($this->connection,$q);
        		} else {
        			$q = "DELETE FROM " . TB_PREFIX . "online WHERE name ='" . addslashes($session->username) . "'";
        			return mysqli_query($this->connection,$q);
        		}
        	}

        	//Choice placement where sector
			function generateBase($sector){
				$sector = ($sector == 0) ? rand(1, 4) : $sector;
				// (-/-) SW
				if($sector == 1){
					$x_a = (WORLD_MAX - (WORLD_MAX*2));
					$x_b = 0;
					$y_a = (WORLD_MAX - (WORLD_MAX*2));
					$y_b = 0;
					$order = "ORDER BY y DESC,x DESC";
					$mmm = rand(-1, -20);
					$x_y = "AND x < -4 AND y < $mmm";
				}
				// (+/-) SE
				elseif($sector == 2){
					$x_a = (WORLD_MAX - (WORLD_MAX*2));
					$x_b = 0;
					$y_a = 0;
					$y_b = WORLD_MAX;
					$order = "ORDER BY y ASC,x DESC";
					$mmm = rand(1, 20);
					$x_y = "AND x < -4 AND y > $mmm";
				}
				// (+/+) NE
				elseif($sector == 3){
					$x_a = 0;
					$x_b = WORLD_MAX;
					$y_a = 0;
					$y_b = WORLD_MAX;
					$order = "ORDER BY y,x ASC";
					$mmm = rand(1, 20);
					$x_y = "AND x > 4 AND y > $mmm";
				}
				// (-/+) NW
				elseif($sector == 4){
					$x_a = 0;
					$x_b = WORLD_MAX;
					$y_a = (WORLD_MAX - (WORLD_MAX*2));
					$y_b = 0;
					$order = "ORDER BY y DESC, x ASC";
					$mmm = rand(-1, -20);
					$x_y = "AND x > 4 AND y < $mmm";
				}
				$q = "SELECT * FROM ".TB_PREFIX."wdata where fieldtype = 3 and occupied = 0 $x_y and (x BETWEEN $x_a AND $x_b) and (y BETWEEN $y_a AND $y_b) $order LIMIT 20";

				$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['id'];
			}

        	function setFieldTaken($id) {
        		$q = "UPDATE " . TB_PREFIX . "wdata set occupied = 1 where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

				function claimFieldForSettlement($id) {
					$id = (int) $id;
					$q = "UPDATE " . TB_PREFIX . "wdata SET occupied = 1 WHERE id = $id AND occupied = 0 AND oasistype = 0 AND fieldtype > 0";
					$result = mysqli_query($this->connection,$q);
					return $result && mysqli_affected_rows($this->connection) === 1;
				}

				function acquireSettlementLock($uid, $timeout = 5) {
					$uid = (int) $uid;
					$timeout = max(0, min(10, (int) $timeout));
					if($uid <= 0) {
						return false;
					}
					$lockName = mysqli_real_escape_string($this->connection, TB_PREFIX . "settlement_" . $uid);
					$result = mysqli_query($this->connection,"SELECT GET_LOCK('$lockName',$timeout)");
					$row = $result ? mysqli_fetch_row($result) : false;
					return $row && (int)$row[0] === 1;
				}

				function releaseSettlementLock($uid) {
					$uid = (int) $uid;
					if($uid <= 0) {
						return false;
					}
					$lockName = mysqli_real_escape_string($this->connection, TB_PREFIX . "settlement_" . $uid);
					$result = mysqli_query($this->connection,"SELECT RELEASE_LOCK('$lockName')");
					$row = $result ? mysqli_fetch_row($result) : false;
					return $row && (int)$row[0] === 1;
				}

				function assignExpansionSlot($from, $target, $owner) {
					$from = (int) $from;
					$target = (int) $target;
					$owner = (int) $owner;
					if($from <= 0 || $target <= 0 || $owner <= 0) {
						return false;
					}
					for($slot = 1; $slot <= 3; $slot++) {
						$q = "UPDATE " . TB_PREFIX . "vdata SET exp$slot = $target WHERE wref = $from AND owner = $owner AND exp$slot = 0";
						$result = mysqli_query($this->connection,$q);
						if($result && mysqli_affected_rows($this->connection) === 1) {
							return true;
						}
					}
					return false;
				}

				function getConquestEligibility($from, $target, $attackerOwner, $defenderOwner) {
					$from = (int)$from;
					$target = (int)$target;
					$attackerOwner = (int)$attackerOwner;
					$defenderOwner = (int)$defenderOwner;
					$result = array('status' => 'invalid');
					if($from <= 0 || $target <= 0 || $attackerOwner <= 0 || $defenderOwner <= 0 || $from === $target) {
						return $result;
					}

					$query = mysqli_query(
						$this->connection,
						"SELECT owner,exp1,exp2,exp3 FROM " . TB_PREFIX . "vdata WHERE wref = $from LIMIT 1"
					);
					$source = $query ? mysqli_fetch_assoc($query) : false;
					$query = mysqli_query(
						$this->connection,
						"SELECT owner,capital,loyalty FROM " . TB_PREFIX . "vdata WHERE wref = $target LIMIT 1"
					);
					$destination = $query ? mysqli_fetch_assoc($query) : false;
					if(!$source || !$destination) {
						$result['status'] = 'database_error';
						return $result;
					}
					if((int)$source['owner'] !== $attackerOwner) {
						$result['status'] = 'source_changed';
						return $result;
					}
					if((int)$destination['owner'] !== $defenderOwner) {
						$result['status'] = (int)$destination['owner'] === $attackerOwner ? 'same_owner' : 'target_changed';
						return $result;
					}
					if($attackerOwner === $defenderOwner) {
						$result['status'] = 'same_owner';
						return $result;
					}
					if((int)$destination['capital'] === 1) {
						$result['status'] = 'capital';
						return $result;
					}

					$query = mysqli_query(
						$this->connection,
						"SELECT COUNT(*) AS villages FROM " . TB_PREFIX . "vdata WHERE owner = $defenderOwner"
					);
					$count = $query ? mysqli_fetch_assoc($query) : false;
					if(!$count) {
						$result['status'] = 'database_error';
						return $result;
					}
					if((int)$count['villages'] <= 1) {
						$result['status'] = 'last_village';
						return $result;
					}

					$query = mysqli_query(
						$this->connection,
						"SELECT * FROM " . TB_PREFIX . "fdata WHERE vref = $target LIMIT 1"
					);
					$fields = $query ? mysqli_fetch_assoc($query) : false;
					if(!$fields) {
						$result['status'] = 'database_error';
						return $result;
					}
					for($field = 19; $field <= 38; $field++) {
						if((int)$fields['f'.$field.'t'] === 25 || (int)$fields['f'.$field.'t'] === 26) {
							$result['status'] = 'residence';
							return $result;
						}
					}

					$slot = 0;
					for($candidate = 1; $candidate <= 3; $candidate++) {
						if((int)$source['exp'.$candidate] === 0) {
							$slot = $candidate;
							break;
						}
					}
					if($slot === 0) {
						$result['status'] = 'no_slot';
						return $result;
					}

					return array(
						'status' => 'eligible',
						'slot' => $slot,
						'loyalty' => (int)$destination['loyalty']
					);
				}

				function applyConquestLoyalty($from, $target, $attackerOwner, $defenderOwner, $attackId, $loyaltyDamage) {
					$from = (int)$from;
					$target = (int)$target;
					$attackerOwner = (int)$attackerOwner;
					$defenderOwner = (int)$defenderOwner;
					$attackId = (int)$attackId;
					$loyaltyDamage = max(1, (int)$loyaltyDamage);
					if($from <= 0 || $target <= 0 || $attackerOwner <= 0 || $defenderOwner <= 0 || $attackId <= 0) {
						return array('status' => 'invalid');
					}

					$lockName = mysqli_real_escape_string($this->connection, TB_PREFIX . "conquest_" . $target);
					$lockQuery = mysqli_query($this->connection, "SELECT GET_LOCK('$lockName',5)");
					$lockRow = $lockQuery ? mysqli_fetch_row($lockQuery) : false;
					if(!$lockRow || (int)$lockRow[0] !== 1) {
						return array('status' => 'busy');
					}

					try {
						$eligibility = $this->getConquestEligibility($from, $target, $attackerOwner, $defenderOwner);
						if($eligibility['status'] !== 'eligible') {
							return $eligibility;
						}

						$oldLoyalty = (int)$eligibility['loyalty'];
						$newLoyalty = $oldLoyalty - $loyaltyDamage;
						if($newLoyalty > 0) {
							$query = "UPDATE " . TB_PREFIX . "vdata SET loyalty = $newLoyalty"
								. " WHERE wref = $target AND owner = $defenderOwner AND loyalty = $oldLoyalty";
							$updated = mysqli_query($this->connection, $query);
							if(!$updated || mysqli_affected_rows($this->connection) !== 1) {
								return array('status' => 'target_changed');
							}
							return array(
								'status' => 'loyalty_reduced',
								'old_loyalty' => $oldLoyalty,
								'new_loyalty' => $newLoyalty
							);
						}

						$slot = (int)$eligibility['slot'];
						$query = "UPDATE " . TB_PREFIX . "vdata AS source"
							. " INNER JOIN " . TB_PREFIX . "vdata AS destination ON destination.wref = $target"
							. " INNER JOIN " . TB_PREFIX . "fdata AS fields ON fields.vref = destination.wref"
							. " INNER JOIN " . TB_PREFIX . "attacks AS attack ON attack.id = $attackId"
							. " LEFT JOIN " . TB_PREFIX . "artefacts AS artefact ON artefact.vref = destination.wref"
							. " SET source.exp$slot = $target, destination.owner = $attackerOwner,"
							. " destination.loyalty = 33, fields.f40 = 0, fields.f40t = 0,"
							. " attack.t9 = attack.t9 - 1, artefact.owner = $attackerOwner"
							. " WHERE source.wref = $from AND source.owner = $attackerOwner"
							. " AND source.exp$slot = 0 AND destination.owner = $defenderOwner"
							. " AND destination.capital = 0 AND attack.t9 > 0";
						$updated = mysqli_query($this->connection, $query);
						if(!$updated) {
							return array('status' => 'database_error');
						}
						if(mysqli_affected_rows($this->connection) < 3) {
							return array('status' => 'no_chief');
						}

						$cleanup = mysqli_query(
							$this->connection,
							"UPDATE " . TB_PREFIX . "vdata SET"
							. " exp1 = IF(exp1 = $target,0,exp1),"
							. " exp2 = IF(exp2 = $target,0,exp2),"
							. " exp3 = IF(exp3 = $target,0,exp3)"
							. " WHERE wref != $from AND (exp1 = $target OR exp2 = $target OR exp3 = $target)"
						);
						$this->syncClimberPopulation($defenderOwner);
						$this->syncClimberPopulation($attackerOwner);
						return array(
							'status' => 'conquered',
							'old_loyalty' => $oldLoyalty,
							'new_loyalty' => 33,
							'cleanup' => (bool)$cleanup
						);
					} finally {
						mysqli_query($this->connection, "SELECT RELEASE_LOCK('$lockName')");
					}
				}

				function cleanupFailedSettlement($wid, $uid) {
					$wid = (int) $wid;
					$uid = (int) $uid;
					if($wid <= 0 || $uid <= 0) {
						return false;
					}
					$result = mysqli_query($this->connection,"SELECT owner FROM " . TB_PREFIX . "vdata WHERE wref = $wid");
					$row = $result ? mysqli_fetch_assoc($result) : false;
					if(!$row || (int)$row['owner'] !== $uid) {
						return false;
					}
					foreach(array('abdata','tdata','units','fdata') as $table) {
						mysqli_query($this->connection,"DELETE FROM " . TB_PREFIX . "$table WHERE vref = $wid");
					}
					mysqli_query($this->connection,"DELETE FROM " . TB_PREFIX . "vdata WHERE wref = $wid AND owner = $uid");
					$this->syncClimberPopulation($uid);
					$q = "UPDATE " . TB_PREFIX . "wdata w LEFT JOIN " . TB_PREFIX . "vdata v ON v.wref = w.id SET w.occupied = 0 WHERE w.id = $wid AND v.wref IS NULL";
					$result = mysqli_query($this->connection,$q);
					return $result && mysqli_affected_rows($this->connection) === 1;
				}

				function releaseUninitializedSettlementClaim($wid) {
					$wid = (int) $wid;
					if($wid <= 0) {
						return false;
					}
					$q = "UPDATE " . TB_PREFIX . "wdata w LEFT JOIN " . TB_PREFIX . "vdata v ON v.wref = w.id SET w.occupied = 0 WHERE w.id = $wid AND w.occupied = 1 AND v.wref IS NULL";
					$result = mysqli_query($this->connection,$q);
					return $result && mysqli_affected_rows($this->connection) === 1;
				}

			function addVillage($wid, $uid, $username, $capital) {
			$total = count($this->getVillagesID($uid));
			if($total >= 1) {
				$vname = "Aldea de " . $username . " " . ($total + 1);
			} else {
				$vname = "Aldea de " . $username;
			}

        		$time = time();
        		$q = "INSERT IGNORE into " . TB_PREFIX . "vdata (wref, owner, name, capital, pop, cp, celebration, wood, clay, iron, maxstore, crop, maxcrop, lastupdate, created) values
        ('$wid', '$uid', '$vname', '$capital', 2, 1, 0, 780, 780, 780, 800, 780, 800, '$time', '$time')";
			$result = mysqli_query($this->connection,$q);
			if($result) {
				$this->syncClimberPopulation((int)$uid);
			}
			return $result;
			}

        	function addResourceFields($vid, $type) {
        		switch($type) {
        			case 1:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,4,4,1,4,4,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 2:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,3,4,1,3,2,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 3:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,1,4,1,3,2,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 4:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,1,4,1,2,2,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 5:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,1,4,1,3,1,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 6:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,4,4,1,3,4,4,4,4,4,4,4,4,4,4,4,2,4,4,1,15)";
        				break;
        			case 7:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,1,4,4,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 8:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,3,4,4,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 9:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,3,4,4,1,1,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 10:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,3,4,1,2,2,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        			case 11:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,3,1,1,3,1,4,4,3,3,2,2,3,1,4,4,2,4,4,1,15)";
        				break;
        			case 12:
        				$q = "INSERT into " . TB_PREFIX . "fdata (vref,f1t,f2t,f3t,f4t,f5t,f6t,f7t,f8t,f9t,f10t,f11t,f12t,f13t,f14t,f15t,f16t,f17t,f18t,f26,f26t) values($vid,1,4,1,1,2,2,3,4,4,3,3,4,4,1,4,2,1,2,1,15)";
        				break;
        		}
        		return mysqli_query($this->connection,$q);
        	}
        	function isVillageOases($wref) {
        		$q = "SELECT id, oasistype FROM " . TB_PREFIX . "wdata where id = $wref";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['oasistype'];
        	}

			public function VillageOasisCount($vref) {
				$q = "SELECT count(*) FROM ".TB_PREFIX."odata WHERE conqured=$vref";
				$result = mysqli_query($this->connection,$q);
				$row = mysqli_fetch_row($result);
				return $row[0];
			}

        	function populateOasis() {
        		$q = "SELECT * FROM " . TB_PREFIX . "wdata where oasistype != 0";
        		$result = mysqli_query($this->connection,$q);
        		while($row = mysqli_fetch_array($result)) {
        			$wid = $row['id'];

        			$this->addUnits($wid);

        		}
        	}


        	/***************************
        	Function to retrieve type of village via ID
        	References: Village ID
        	***************************/
        	function getVillageType($wref) {
        		$q = "SELECT id, fieldtype FROM " . TB_PREFIX . "wdata where id = $wref";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['fieldtype'];
        	}

			function getVillageData($wref) {
        		$q = "SELECT * FROM " . TB_PREFIX . "wdata where id = $wref";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

			function getVillageType2($wref) {
        		$q = "SELECT * FROM " . TB_PREFIX . "wdata where id = $wref";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['oasistype'];
        	}

			function getVillageType3($wref) {
				$wref = (int)$wref;
				$q = "SELECT * FROM " . TB_PREFIX . "wdata where id = $wref";
				$result = mysqli_query($this->connection,$q);
				return $result ? mysqli_fetch_array($result) : false;
			}

			function getVilWref($x, $y) {
				$x = (int) $x;
				$y = (int) $y;
				$q = "SELECT * FROM " . TB_PREFIX . "wdata where x = $x AND y = $y";
        		$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				return $dbarray ? $dbarray['id'] : 0;
        	}

			function checkVilExist($wref) {
				$wref = (int) $wref;
				$q = "SELECT * FROM " . TB_PREFIX . "vdata where wref = $wref";
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

			function oasischecker($x, $y) {
        		$q = "SELECT * FROM " . TB_PREFIX . "wdata where x = $x AND y = $y";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	/*****************************************
        	Function to retrieve if is ocuped via ID
        	References: Village ID
        	*****************************************/
        	function getVillageState($wref) {
        		$q = "SELECT oasistype,occupied FROM " . TB_PREFIX . "wdata where id = $wref";
        		$result = mysqli_query($this->connection,$q);
        		//echo $result ;
        		$dbarray = mysqli_fetch_array($result);
        		if($dbarray['occupied'] != 0 || $dbarray['oasistype'] != 0) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function getProfileVillages($uid) {
        		$q = "SELECT * from " . TB_PREFIX . "vdata where owner = $uid order by pop desc";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function getProfileMedal($uid) {
        		$q = "SELECT id,categorie,plaats,week,img,points from " . TB_PREFIX . "medal where userid = $uid order by id desc";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);

        	}

        	function getProfileMedalAlly($uid) {
        		$q = "SELECT id,categorie,plaats,week,img,points from " . TB_PREFIX . "allimedal where allyid = $uid order by id desc";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);

        	}

        	function getVillageID($uid) {
        		$q = "SELECT wref FROM " . TB_PREFIX . "vdata WHERE owner = $uid";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['wref'];
        	}


        	function getVillagesID($uid) {
        		$q = "SELECT wref from " . TB_PREFIX . "vdata where owner = $uid order by capital DESC";
        		$result = mysqli_query($this->connection,$q);
        		$array = $this->mysqli_fetch_all($result);
        		$newarray = array();
        		for($i = 0; $i < count($array); $i++) {
        			array_push($newarray, $array[$i]['wref']);
        		}
        		return $newarray;
        	}

			function getPendingSettlementCountByOwner($uid, $excludeMoveId = 0, $target = 0) {
				$uid = (int) $uid;
				$excludeMoveId = (int) $excludeMoveId;
				$target = (int) $target;
				if($uid <= 0) {
					return 0;
				}
				$exclude = $excludeMoveId > 0 ? " AND m.moveid != $excludeMoveId" : "";
				$targetCondition = $target > 0 ? " AND m.`to` = $target" : "";
				$q = "SELECT COUNT(*) FROM " . TB_PREFIX . "movement m LEFT JOIN " . TB_PREFIX . "vdata v ON v.wref = m.`from` WHERE m.sort_type = 5 AND m.proc = 0$exclude$targetCondition AND ((CAST(m.data AS UNSIGNED) = $uid AND CAST(m.data AS UNSIGNED) > 0) OR (CAST(m.data AS UNSIGNED) = 0 AND v.owner = $uid))";
				$result = mysqli_query($this->connection,$q);
				$row = $result ? mysqli_fetch_row($result) : false;
				return $row ? (int)$row[0] : 0;
			}

			function getVillagesID2($uid) {
				$q = "SELECT wref from " . TB_PREFIX . "vdata where owner = $uid order by capital DESC,pop DESC";
				$result = mysqli_query($this->connection,$q);
				$array = $this->mysqli_fetch_all($result);
				return $array;
			}

        	function getVillage($vid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "vdata where wref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

        	function getOasisV($vid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "odata where wref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getAInfo($id) {
        		$q = "SELECT * FROM " . TB_PREFIX . "wdata where id = $id";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getMInfo($id) {
				$id = (int) $id;
				$q = "SELECT * FROM " . TB_PREFIX . "wdata left JOIN " . TB_PREFIX . "vdata ON " . TB_PREFIX . "vdata.wref = " . TB_PREFIX . "wdata.id where " . TB_PREFIX . "wdata.id = $id";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

        	function getOMInfo($id) {
        		$q = "SELECT * FROM " . TB_PREFIX . "wdata left JOIN " . TB_PREFIX . "odata ON " . TB_PREFIX . "odata.wref = " . TB_PREFIX . "wdata.id where " . TB_PREFIX . "wdata.id = $id";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

        	function getOasis($vid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "odata where conqured = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function getOasisInfo($wid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "odata where wref = $wid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

        	function getVillageField($ref, $field) {
        		$q = "SELECT $field FROM " . TB_PREFIX . "vdata where wref = $ref";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray[$field];

        	}

        	function getOasisField($ref, $field) {
        		$q = "SELECT $field FROM " . TB_PREFIX . "odata where wref = $ref";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray[$field];
        	}

        	function setVillageField($ref, $field, $value) {
        		$q = "UPDATE " . TB_PREFIX . "vdata set $field = '$value' where wref = $ref";
        		return mysqli_query($this->connection,$q);
        	}

        	function setVillageLevel($ref, $field, $value) {
        		$q = "UPDATE " . TB_PREFIX . "fdata set " . $field . " = '" . $value . "' where vref = " . $ref . "";
        		return mysqli_query($this->connection,$q);
        	}

        	function getResourceLevel($vid) {
        		$q = "SELECT * from " . TB_PREFIX . "fdata where vref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

			function getBreweryLevel($uid) {
				$uid = (int)$uid;
				if($uid <= 0 || $this->getBreweryCelebrationEnd($uid) <= time()) {
					return 0;
				}
				$q = "SELECT f.* FROM " . TB_PREFIX . "fdata AS f INNER JOIN " . TB_PREFIX . "vdata AS v ON v.wref = f.vref WHERE v.owner = $uid AND v.capital = 1 LIMIT 1";
				$result = mysqli_query($this->connection,$q);
				$fields = $result ? mysqli_fetch_assoc($result) : false;
				if(!$fields) {
					return 0;
				}
				for($field = 19; $field <= 38; $field++) {
					if((int)$fields['f'.$field.'t'] === 35) {
						return max(0, min(10, (int)$fields['f'.$field]));
					}
				}
				return 0;
			}

			function getBreweryCelebrationEnd($uid) {
				$uid = (int)$uid;
				if($uid <= 0) {
					return 0;
				}
				$result = mysqli_query($this->connection,"SELECT brewery FROM " . TB_PREFIX . "users WHERE id = $uid LIMIT 1");
				$row = $result ? mysqli_fetch_assoc($result) : false;
				return $row ? max(0, (int)$row['brewery']) : 0;
			}

			function startBreweryCelebration($uid,$wid,$endtime,$wood,$clay,$iron,$crop) {
				$uid = (int)$uid;
				$wid = (int)$wid;
				$endtime = (int)$endtime;
				$wood = max(0, (int)$wood);
				$clay = max(0, (int)$clay);
				$iron = max(0, (int)$iron);
				$crop = max(0, (int)$crop);
				if($uid <= 0 || $wid <= 0 || $endtime <= time() || !$this->acquireBreweryLock($uid)) {
					return false;
				}
				try {
					if($this->getBreweryCelebrationEnd($uid) > time()) {
						return false;
					}
					if(!$this->deductResourcesIfAvailable($wid,$wood,$clay,$iron,$crop)) {
						return false;
					}
					$q = "UPDATE " . TB_PREFIX . "users SET brewery = $endtime WHERE id = $uid AND brewery <= " . time();
					$result = mysqli_query($this->connection,$q);
					$started = $result && mysqli_affected_rows($this->connection) === 1;
					if(!$started) {
						$this->modifyResource($wid,$wood,$clay,$iron,$crop,1);
					}
					return $started;
				} finally {
					$this->releaseBreweryLock($uid);
				}
			}

			private function acquireBreweryLock($uid) {
				$lockName = mysqli_real_escape_string($this->connection, TB_PREFIX . "brewery_" . (int)$uid);
				$result = mysqli_query($this->connection,"SELECT GET_LOCK('$lockName',5)");
				$row = $result ? mysqli_fetch_row($result) : false;
				return $row && (int)$row[0] === 1;
			}

			private function releaseBreweryLock($uid) {
				$lockName = mysqli_real_escape_string($this->connection, TB_PREFIX . "brewery_" . (int)$uid);
				mysqli_query($this->connection,"SELECT RELEASE_LOCK('$lockName')");
			}

        	function getAdminLog() {
        		$q = "SELECT id,user,log,time from " . TB_PREFIX . "admin_log where id != 0 ORDER BY id DESC";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

			function delAdminLog($id) {
        		$q = "DELETE FROM " . TB_PREFIX . "admin_log where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

        	function getCoor($wref) {
				$wref = (int) $wref;
        		$q = "SELECT x,y FROM " . TB_PREFIX . "wdata where id = $wref";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

        	function CheckForum($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_cat where alliance = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function CountCat($id) {
        		$q = "SELECT count(id) FROM " . TB_PREFIX . "forum_topic where cat = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		return $row[0];
        	}

        	function LastTopic($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_topic where cat = '$id' order by post_date";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function CheckLastTopic($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_topic where cat = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function CheckLastPost($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_post where topic = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function LastPost($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_post where topic = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function CountTopic($id) {
        		$q = "SELECT count(id) FROM " . TB_PREFIX . "forum_post where owner = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);

        		$qs = "SELECT count(id) FROM " . TB_PREFIX . "forum_topic where owner = '$id'";
        		$results = mysqli_query($this->connection,$qs);
        		$rows = mysqli_fetch_row($results);
        		return $row[0] + $rows[0];
        	}

        	function CountPost($id) {
        		$q = "SELECT count(id) FROM " . TB_PREFIX . "forum_post where topic = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		return $row[0];
        	}

        	function ForumCat($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_cat where alliance = '$id' ORDER BY id";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function ForumCatEdit($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_cat where id = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

			function ForumCatAlliance($id) {
				$q = "SELECT alliance from " . TB_PREFIX . "forum_cat where id = $id";
				$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				return $dbarray['alliance'];
			}

        	function ForumCatName($id) {
        		$q = "SELECT forum_name from " . TB_PREFIX . "forum_cat where id = $id";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['forum_name'];
        	}

        	function CheckCatTopic($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_topic where cat = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function CheckResultEdit($alli) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_edit where alliance = '$alli'";
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	function CheckCloseTopic($id) {
        		$q = "SELECT close from " . TB_PREFIX . "forum_topic where id = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['close'];
        	}

        	function CheckEditRes($alli) {
        		$q = "SELECT result from " . TB_PREFIX . "forum_edit where alliance = '$alli'";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['result'];
        	}

        	function CreatResultEdit($alli, $result) {
        		$q = "INSERT into " . TB_PREFIX . "forum_edit values (0,'$alli','$result')";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

        	function UpdateResultEdit($alli, $result) {
        		$date = time();
        		$q = "UPDATE " . TB_PREFIX . "forum_edit set result = '$result' where alliance = '$alli'";
        		return mysqli_query($this->connection,$q);
        	}

        	function UpdateEditTopic($id, $title, $cat) {
        		$q = "UPDATE " . TB_PREFIX . "forum_topic set title = '$title', cat = '$cat' where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

        	function UpdateEditForum($id, $name, $des) {
        		$q = "UPDATE " . TB_PREFIX . "forum_cat set forum_name = '$name', forum_des = '$des' where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

        	function StickTopic($id, $mode) {
        		$q = "UPDATE " . TB_PREFIX . "forum_topic set stick = '$mode' where id = '$id'";
        		return mysqli_query($this->connection,$q);
        	}

        	function ForumCatTopic($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_topic where cat = '$id' AND stick = '' ORDER BY post_date desc";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function ForumCatTopicStick($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_topic where cat = '$id' AND stick = '1' ORDER BY post_date desc";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function ShowTopic($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_topic where id = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function ShowPost($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_post where topic = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function ShowPostEdit($id) {
        		$q = "SELECT * from " . TB_PREFIX . "forum_post where id = '$id'";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function CreatForum($owner, $alli, $name, $des, $area) {
        		$q = "INSERT into " . TB_PREFIX . "forum_cat values (0,'$owner','$alli','$name','$des','$area')";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

        	function CreatTopic($title, $post, $cat, $owner, $alli, $ends, $alliance, $player, $coor, $report) {
        		$date = time();
        		$q = "INSERT into " . TB_PREFIX . "forum_topic values (0,'$title','$post','$date','$date','$cat','$owner','$alli','$ends','','','$alliance','$player','$coor','$report')";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

        	function CreatPost($post, $tids, $owner, $alliance, $player, $coor, $report) {
        		$date = time();
        		$q = "INSERT into " . TB_PREFIX . "forum_post values (0,'$post','$tids','$owner','$date','$alliance','$player','$coor','$report')";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

        	function UpdatePostDate($id) {
        		$date = time();
        		$q = "UPDATE " . TB_PREFIX . "forum_topic set post_date = '$date' where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

        	function EditUpdateTopic($id, $post, $alliance, $player, $coor, $report) {
        		$q = "UPDATE " . TB_PREFIX . "forum_topic set post = '$post', alliance0 = '$alliance', player0 = '$player', coor0 = '$coor', report0 = '$report' where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

        	function EditUpdatePost($id, $post, $alliance, $player, $coor, $report) {
        		$q = "UPDATE " . TB_PREFIX . "forum_post set post = '$post', alliance0 = '$alliance', player0 = '$player', coor0 = '$coor', report0 = '$report' where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

        	function LockTopic($id, $mode) {
        		$q = "UPDATE " . TB_PREFIX . "forum_topic set close = '$mode' where id = '$id'";
        		return mysqli_query($this->connection,$q);
        	}

        	function DeleteCat($id) {
        		$qs = "DELETE from " . TB_PREFIX . "forum_cat where id = '$id'";
        		$q = "DELETE from " . TB_PREFIX . "forum_topic where cat = '$id'";
        		mysqli_query($this->connection,$qs);
        		return mysqli_query($this->connection,$q);
        	}

        	function DeleteTopic($id) {
        		$qs = "DELETE from " . TB_PREFIX . "forum_topic where id = '$id'";
        		//  $q = "DELETE from ".TB_PREFIX."forum_post where topic = '$id'";//
        		return mysqli_query($this->connection, $qs); //
        		// mysqli_query($this->connection,$q);
        	}

        	function DeletePost($id) {
        		$q = "DELETE from " . TB_PREFIX . "forum_post where id = '$id'";
        		return mysqli_query($this->connection,$q);
        	}

        	function getAllianceName($id) {
        		$q = "SELECT tag from " . TB_PREFIX . "alidata where id = $id";
        		$result = mysqli_query($this->connection,$q);
                if ($result)
                {
                    $dbarray = mysqli_fetch_array($result);
                    return $dbarray['tag'];
                }
                else return false;
        	}

        	function getAlliancePermission($ref, $field, $mode) {
        		if(!$mode) {
        			$q = "SELECT $field FROM " . TB_PREFIX . "ali_permission where uid = '$ref'";
        		} else {
        			$q = "SELECT $field FROM " . TB_PREFIX . "ali_permission where username = '$ref'";
        		}
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray[$field];
        	}

        	function getAlliance($id) {
        		$q = "SELECT * from " . TB_PREFIX . "alidata where id = $id";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

        	function setAlliName($aid, $name, $tag) {
        		$q = "UPDATE " . TB_PREFIX . "alidata set name = '$name', tag = '$tag' where id = $aid";
        		return mysqli_query($this->connection,$q);
        	}

        	function isAllianceOwner($id) {
        		$q = "SELECT * from " . TB_PREFIX . "alidata where leader = '$id'";
        		$result = mysqli_query($this->connection,$q);
                return mysqli_num_rows($result) > 0;
        	}

        	function aExist($ref, $type) {
                $escaped = $this->RemoveXSS($ref);
                $q = "SELECT 1 FROM " . TB_PREFIX . "alidata where $type = '$escaped'";
        		$result = mysqli_query($this->connection,$q);
                return mysqli_num_rows($result) > 0;
        	}

            function aExist2($ref, $type, $aid) {
                $escaped = $this->RemoveXSS($ref);
                $q = "SELECT 1 FROM " . TB_PREFIX . "alidata where $type = '$escaped' and id != $aid";
                $result = mysqli_query($this->connection,$q);
                return mysqli_num_rows($result) > 0;
            }

        	function modifyPoints($aid, $points, $amt) {
        		$q = "UPDATE " . TB_PREFIX . "users set $points = $points + $amt where id = $aid";
        		return mysqli_query($this->connection,$q);
        	}

        	function modifyPointsAlly($aid, $points, $amt) {
        		$q = "UPDATE " . TB_PREFIX . "alidata set $points = $points + $amt where id = $aid";
        		return mysqli_query($this->connection,$q);
        	}

        	/*****************************************
        	Function to create an alliance
        	References:
        	*****************************************/
        	function createAlliance($tag, $name, $uid, $max) {
        		$q = "INSERT into " . TB_PREFIX . "alidata values (0,'$name','$tag',$uid,0,0,0,'','',$max,'','','','','','','','')";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

			function syncAllianceClimberPopulation($aid) {
				$aid = (int)$aid;
				if($aid <= 0) {
					return false;
				}
				$q = "UPDATE " . TB_PREFIX . "alidata a
					LEFT JOIN (
						SELECT u.alliance AS aid, COALESCE(SUM(v.pop),0) AS totalpop
						FROM " . TB_PREFIX . "users u
						LEFT JOIN " . TB_PREFIX . "vdata v ON v.owner = u.id
						WHERE u.alliance = $aid
						GROUP BY u.alliance
					) totals ON totals.aid = a.id
					SET a.clp = a.clp + (
							CAST(COALESCE(totals.totalpop,0) AS SIGNED)
							- CAST(a.oldrank AS SIGNED)
						),
						a.oldrank = COALESCE(totals.totalpop,0)
					WHERE a.id = $aid";
				return mysqli_query($this->connection,$q);
			}

			function syncClimberPopulation($uid) {
				$uid = (int)$uid;
				if($uid <= 0) {
					return false;
				}
				$q = "SELECT alliance FROM " . TB_PREFIX . "users WHERE id = $uid";
				$result = mysqli_query($this->connection,$q);
				$row = $result ? mysqli_fetch_assoc($result) : false;
				if(!$row) {
					return false;
				}
				$q = "UPDATE " . TB_PREFIX . "users u
					LEFT JOIN (
						SELECT owner, COALESCE(SUM(pop),0) AS totalpop
						FROM " . TB_PREFIX . "vdata
						WHERE owner = $uid
						GROUP BY owner
					) totals ON totals.owner = u.id
					SET u.clp = u.clp + (
							CAST(COALESCE(totals.totalpop,0) AS SIGNED)
							- CAST(u.oldrank AS SIGNED)
						),
						u.oldrank = COALESCE(totals.totalpop,0)
					WHERE u.id = $uid";
				$updated = mysqli_query($this->connection,$q);
				$aid = (int)$row['alliance'];
				if($updated && $aid > 0) {
					$this->syncAllianceClimberPopulation($aid);
				}
				return $updated;
			}

			function syncAllClimberPopulations() {
				$accessLimit = INCLUDE_ADMIN ? 10 : 8;
				$result = mysqli_query(
					$this->connection,
					"SELECT id FROM " . TB_PREFIX . "users
					 WHERE id > 3 AND tribe <= 3 AND access < $accessLimit"
				);
				if(!$result) {
					return false;
				}
				while($row = mysqli_fetch_assoc($result)) {
					$this->syncClimberPopulation((int)$row['id']);
				}
				$allies = mysqli_query($this->connection,"SELECT id FROM " . TB_PREFIX . "alidata");
				if($allies) {
					while($ally = mysqli_fetch_assoc($allies)) {
						$this->syncAllianceClimberPopulation((int)$ally['id']);
					}
				}
				return true;
			}

			function procAllyPop($aid) {
				return $this->syncAllianceClimberPopulation($aid);
			}

        	/*****************************************
        	Function to insert an alliance new
        	References:
        	*****************************************/
        	function insertAlliNotice($aid, $notice) {
        		$time = time();
        		$q = "INSERT into " . TB_PREFIX . "ali_log values (0,'$aid','$notice',$time)";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

        	/*****************************************
        	Function to delete alliance if empty
        	References:
        	*****************************************/
        	function deleteAlliance($aid) {
        		$result = mysqli_query($this->connection,"SELECT * FROM " . TB_PREFIX . "users where alliance = $aid");
        		$num_rows = mysqli_num_rows($result);
        		if($num_rows == 0) {
        			$q = "DELETE FROM " . TB_PREFIX . "alidata WHERE id = $aid";
                    mysqli_query($this->connection,$q);
                    return mysqli_insert_id($this->connection);
        		}
        	}

        	/*****************************************
        	Function to read all alliance news
        	References:
        	*****************************************/
        	function readAlliNotice($aid) {
        		$q = "SELECT * from " . TB_PREFIX . "ali_log where aid = $aid ORDER BY date DESC";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	/*****************************************
        	Function to create alliance permissions
        	References: ID, notice, description
        	*****************************************/
        	function createAlliPermissions($uid, $aid, $rank, $opt1, $opt2, $opt3, $opt4, $opt5, $opt6, $opt7, $opt8) {

        		$q = "INSERT into " . TB_PREFIX . "ali_permission values(0,'$uid','$aid','$rank','$opt1','$opt2','$opt3','$opt4','$opt5','$opt6','$opt7','$opt8')";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

        	/*****************************************
        	Function to update alliance permissions
        	References:
        	*****************************************/
        	function deleteAlliPermissions($uid) {
        		$q = "DELETE from " . TB_PREFIX . "ali_permission where uid = '$uid'";
        		return mysqli_query($this->connection,$q);
        	}
        	/*****************************************
        	Function to update alliance permissions
        	References:
        	*****************************************/
        	function updateAlliPermissions($uid, $aid, $rank, $opt1, $opt2, $opt3, $opt4, $opt5, $opt6, $opt7, $opt8) {

        		$q = "UPDATE " . TB_PREFIX . "ali_permission SET rank = '$rank', opt1 = '$opt1', opt2 = '$opt2', opt3 = '$opt3', opt4 = '$opt4', opt5 = '$opt5', opt6 = '$opt6', opt7 = '$opt7', opt8 = $opt8 where uid = $uid && alliance =$aid";
        		return mysqli_query($this->connection,$q);
        	}

        	/*****************************************
        	Function to read alliance permissions
        	References: ID, notice, description
        	*****************************************/
        	function getAlliPermissions($uid, $aid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "ali_permission where uid = $uid && alliance = $aid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

        	/*****************************************
        	Function to update an alliance description and notice
        	References: ID, notice, description
        	*****************************************/
        	function submitAlliProfile($aid, $notice, $desc) {

        		$q = "UPDATE " . TB_PREFIX . "alidata SET `notice` = '$notice', `desc` = '$desc' where id = $aid";
        		return mysqli_query($this->connection,$q);
        	}

        	function diplomacyInviteAdd($alli1, $alli2, $type) {
        		$q = "INSERT INTO " . TB_PREFIX . "diplomacy (alli1,alli2,type,accepted) VALUES ($alli1,$alli2," . (int)intval($type) . ",0)";
        		return mysqli_query($this->connection,$q);
        	}

        	function diplomacyOwnOffers($session_alliance) {
        		$q = "SELECT * FROM " . TB_PREFIX . "diplomacy WHERE alli1 = $session_alliance AND accepted = 0";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

            function getAllianceID($name) {
        		$q = "SELECT id FROM " . TB_PREFIX . "alidata WHERE tag ='" . $this->RemoveXSS($name) . "'";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['id'];
        	}

        	function getDiplomacy($aid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "diplomacy WHERE id = $aid";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function diplomacyCancelOffer($id) {
        		$q = "DELETE FROM " . TB_PREFIX . "diplomacy WHERE id = $id";
        		return mysqli_query($this->connection,$q);
        	}

        	function diplomacyInviteAccept($id, $session_alliance) {
        		$q = "UPDATE " . TB_PREFIX . "diplomacy SET accepted = 1 WHERE id = $id AND alli2 = $session_alliance";
        		return mysqli_query($this->connection,$q);
        	}

        	function diplomacyInviteDenied($id, $session_alliance) {
        		$q = "DELETE FROM " . TB_PREFIX . "diplomacy WHERE id = $id AND alli2 = $session_alliance";
        		return mysqli_query($this->connection,$q);
        	}

        	function diplomacyInviteCheck($session_alliance) {
        		$q = "SELECT * FROM " . TB_PREFIX . "diplomacy WHERE alli2 = $session_alliance AND accepted = 0";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function diplomacyExistingRelationships($session_alliance) {
        		$q = "SELECT * FROM " . TB_PREFIX . "diplomacy WHERE alli2 = $session_alliance AND accepted = 1";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

            function diplomacyExistingRelationships2($session_alliance) {
        		$q = "SELECT * FROM " . TB_PREFIX . "diplomacy WHERE alli1 = $session_alliance AND accepted = 1";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function diplomacyCancelExistingRelationship($id, $session_alliance) {
        		$q = "DELETE FROM " . TB_PREFIX . "diplomacy WHERE id = $id AND alli2 = $session_alliance";
        		return mysqli_query($this->connection,$q);
        	}

        	function getUserAlliance($id) {
        		$q = "SELECT " . TB_PREFIX . "alidata.tag from " . TB_PREFIX . "users join " . TB_PREFIX . "alidata where " . TB_PREFIX . "users.alliance = " . TB_PREFIX . "alidata.id and " . TB_PREFIX . "users.id = $id";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		if($dbarray['tag'] == "") {
        			return "-";
        		} else {
        			return $dbarray['tag'];
        		}
        	}

        	function modifyResource($vid, $wood, $clay, $iron, $crop, $mode) {
        		if(!$mode) {
        			$q = "UPDATE " . TB_PREFIX . "vdata set wood = wood - $wood, clay = clay - $clay, iron = iron - $iron, crop = crop - $crop where wref = $vid";
        		} else {
        			$q = "UPDATE " . TB_PREFIX . "vdata set wood = wood + $wood, clay = clay + $clay, iron = iron + $iron, crop = crop + $crop where wref = $vid";
        		}
        		return mysqli_query($this->connection,$q);
        	}

            function deductResourcesIfAvailable($vid, $wood, $clay, $iron, $crop) {
                $vid = (int) $vid;
                $wood = (int) $wood;
                $clay = (int) $clay;
                $iron = (int) $iron;
                $crop = (int) $crop;
                if($wood < 0 || $clay < 0 || $iron < 0 || $crop < 0) {
                    return false;
                }
                $q = "UPDATE " . TB_PREFIX . "vdata SET wood = wood - $wood, clay = clay - $clay, iron = iron - $iron, crop = crop - $crop WHERE wref = $vid AND wood >= $wood AND clay >= $clay AND iron >= $iron AND crop >= $crop";
                $result = mysqli_query($this->connection,$q);
                return $result && mysqli_affected_rows($this->connection) === 1;
            }

        	function modifyOasisResource($vid, $wood, $clay, $iron, $crop, $mode) {
        		if(!$mode) {
        			$q = "UPDATE " . TB_PREFIX . "odata set wood = wood - $wood, clay = clay - $clay, iron = iron - $iron, crop = crop - $crop where wref = $vid";
        		} else {
        			$q = "UPDATE " . TB_PREFIX . "odata set wood = wood + $wood, clay = clay + $clay, iron = iron + $iron, crop = crop + $crop where wref = $vid";
        		}
        		return mysqli_query($this->connection,$q);
        	}

        	function getFieldLevel($vid, $field) {
        		$q = "SELECT f" . $field . " from " . TB_PREFIX . "fdata where vref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_result($result, 0);
        	}

        	function getFieldType($vid, $field) {
        		$q = "SELECT f" . $field . "t from " . TB_PREFIX . "fdata where vref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_result($result, 0);
        	}

        	function getVSumField($uid, $field) {
        		$q = "SELECT sum(" . $field . ") FROM " . TB_PREFIX . "vdata where owner = $uid";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		return $row[0];
        	}

        	function updateVillage($vid) {
        		$time = time();
        		$q = "UPDATE " . TB_PREFIX . "vdata set lastupdate = $time where wref = $vid";
        		return mysqli_query($this->connection,$q);
        	}

        	function updateOasis($vid) {
        		$time = time();
        		$q = "UPDATE " . TB_PREFIX . "odata set lastupdated = $time where wref = $vid";
        		return mysqli_query($this->connection,$q);
        	}

        	function updateOasis2($vid) {
        		$time = time();
        		$q = "UPDATE " . TB_PREFIX . "odata set lastupdated2 = $time where wref = $vid";
        		return mysqli_query($this->connection,$q);
        	}

        	function setVillageName($vid, $name) {
        		$q = "UPDATE " . TB_PREFIX . "vdata set name = '$name' where wref = $vid";
        		return mysqli_query($this->connection,$q);
        	}

			function modifyPop($vid, $pop, $mode) {
				$owner = (int)$this->getVillageField($vid, "owner");
				if(!$mode) {
					$q = "UPDATE " . TB_PREFIX . "vdata set pop = pop + $pop where wref = $vid";
				} else {
					$q = "UPDATE " . TB_PREFIX . "vdata set pop = pop - $pop where wref = $vid";
				}
				$result = mysqli_query($this->connection,$q);
				if($result && $owner > 0) {
					$this->syncClimberPopulation($owner);
				}
				return $result;
			}

        	function addCP($ref, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "vdata set cp = cp + '$cp' where wref = '$ref'";
        		return mysqli_query($this->connection,$q);
        	}

        	function addCel($ref, $cel, $type) {
        		$q = "UPDATE " . TB_PREFIX . "vdata set celebration = $cel, type= $type where wref = $ref";
        		return mysqli_query($this->connection,$q);
        	}
        	function getCel() {
        		$time = time();
        		$q = "SELECT * FROM " . TB_PREFIX . "vdata where celebration < $time AND celebration != 0";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function clearCel($ref) {
        		$q = "UPDATE " . TB_PREFIX . "vdata set celebration = 0, type = 0 where wref = $ref";
        		return mysqli_query($this->connection,$q);
        	}
        	function setCelCp($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "users set cp = cp + $cp where id = $user";
		return mysqli_query($this->connection,$q);
	}

	function clearExpansionSlot($id, $excludeVillage = 0) {
		$id = (int)$id;
		$excludeVillage = (int)$excludeVillage;
		$exclude = $excludeVillage > 0 ? " AND wref != " . $excludeVillage : "";
		for($i = 1; $i <= 3; $i++) {
			$q = "UPDATE " . TB_PREFIX . "vdata SET exp" . $i . "=0 WHERE exp" . $i . "=" . $id . $exclude;
			mysqli_query($this->connection,$q);
		}
	}

	function getInvitation($uid) {
		$q = "SELECT * FROM " . TB_PREFIX . "ali_invite where uid = $uid";
		$result = mysqli_query($this->connection,$q);
		return $this->mysqli_fetch_all($result);
	}

	function getInvitation2($uid, $aid) {
		$q = "SELECT * FROM " . TB_PREFIX . "ali_invite where uid = $uid AND alliance = $aid";
		$result = mysqli_query($this->connection,$q);
		return $this->mysqli_fetch_all($result);
	}

	function getAliInvitations($aid) {
		$q = "SELECT * FROM " . TB_PREFIX . "ali_invite where alliance = $aid && accept = 0";
		$result = mysqli_query($this->connection,$q);
		return $this->mysqli_fetch_all($result);
	}

	function sendInvitation($uid, $alli, $sender) {
		$time = time();
		$q = "INSERT INTO " . TB_PREFIX . "ali_invite values (0,$uid,$alli,$sender,$time,0)";
		return mysqli_query($this->connection,$q) or die(mysqli_error());
	}

	function removeInvitation($id) {
		$q = "DELETE FROM " . TB_PREFIX . "ali_invite where id = $id";
		return mysqli_query($this->connection,$q);
	}

			function delNotice($id, $uid) {
		$id = (int)$id;
		$uid = (int)$uid;
		$q = "DELETE FROM " . TB_PREFIX . "ndata WHERE id = $id AND uid = $uid";
		return mysqli_query($this->connection,$q);
	}

			function sendMessage($client, $owner, $topic, $message, $send, $alliance, $player, $coor, $report) {
				$time = time();
				$q = "INSERT INTO " . TB_PREFIX . "mdata values (0,$client,$owner,'$topic',\"$message\",0,0,$send,$time,0,0,$alliance,$player,$coor,$report)";
				return mysqli_query($this->connection,$q);
			}

			function exchangeGoldForSilver($userid, $gold) {
				$userid = (int)$userid;
				$gold = (int)$gold;
				if($userid <= 0 || $gold <= 0 || $gold > 42949672) {
					return false;
				}

				$silver = $gold * 100;
				$q = "UPDATE " . TB_PREFIX . "users SET gold = gold - $gold, silver = silver + $silver WHERE id = $userid AND gold >= $gold AND silver <= " . (4294967295 - $silver);
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

			function exchangeSilverForGold($userid, $silver) {
				$userid = (int)$userid;
				$silver = (int)$silver;
				if($userid <= 0 || $silver < 200 || $silver % 200 !== 0) {
					return false;
				}

				$gold = (int)($silver / 200);
				$q = "UPDATE " . TB_PREFIX . "users SET silver = silver - $silver, gold = gold + $gold WHERE id = $userid AND silver >= $silver AND gold <= " . (4294967295 - $gold);
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

			function claimQuestGold($userid, $currentQuest, $nextQuest, $gold) {
				$userid = (int)$userid;
				$currentQuest = (int)$currentQuest;
				$nextQuest = (int)$nextQuest;
				$gold = (int)$gold;
				if($userid <= 0 || $gold <= 0) {
					return false;
				}

				$q = "UPDATE " . TB_PREFIX . "users SET quest = $nextQuest, gold = gold + $gold WHERE id = $userid AND quest = $currentQuest AND gold <= " . (4294967295 - $gold);
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

			function advanceQuest($userid, $currentQuest, $nextQuest, $questChoose = null) {
				$userid = (int)$userid;
				$currentQuest = (int)$currentQuest;
				$nextQuest = (int)$nextQuest;
				if($userid <= 0 || $currentQuest < 0 || $nextQuest < 0) {
					return false;
				}

				$choiceUpdate = '';
				if($questChoose !== null) {
					$questChoose = (int)$questChoose;
					if($questChoose !== 1 && $questChoose !== 2) {
						return false;
					}
					$choiceUpdate = ", quest_choose = $questChoose";
				}

				$q = "UPDATE " . TB_PREFIX . "users SET quest = $nextQuest$choiceUpdate WHERE id = $userid AND quest = $currentQuest";
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

			function claimQuestPlus($userid, $currentQuest, $nextQuest, $seconds) {
				$userid = (int)$userid;
				$currentQuest = (int)$currentQuest;
				$nextQuest = (int)$nextQuest;
				$seconds = (int)$seconds;
				if($userid <= 0 || $seconds <= 0) {
					return false;
				}

				$q = "UPDATE " . TB_PREFIX . "users SET quest = $nextQuest, plus = GREATEST(plus, UNIX_TIMESTAMP()) + $seconds WHERE id = $userid AND quest = $currentQuest";
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

			function claimQuestResources($userid, $vref, $currentQuest, $nextQuest, $wood, $clay, $iron, $crop) {
				$userid = (int)$userid;
				$vref = (int)$vref;
				$currentQuest = (int)$currentQuest;
				$nextQuest = (int)$nextQuest;
				$wood = (int)$wood;
				$clay = (int)$clay;
				$iron = (int)$iron;
				$crop = (int)$crop;
				if($userid <= 0 || $vref <= 0 || min($wood, $clay, $iron, $crop) < 0) {
					return false;
				}

				$q = "UPDATE " . TB_PREFIX . "users AS u INNER JOIN " . TB_PREFIX . "vdata AS v ON v.wref = $vref AND v.owner = u.id SET u.quest = $nextQuest, v.wood = v.wood + $wood, v.clay = v.clay + $clay, v.iron = v.iron + $iron, v.crop = v.crop + $crop WHERE u.id = $userid AND u.quest = $currentQuest";
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_affected_rows($this->connection) === 2;
			}

			function claimFollowupQuestGold($userid, $currentFquest, $nextFquest, $gold) {
				$userid = (int)$userid;
				$gold = (int)$gold;
				if($userid <= 0 || $gold <= 0 || !preg_match('/^[012](,[012]){10}$/', $currentFquest) || !preg_match('/^[012](,[012]){10}$/', $nextFquest)) {
					return false;
				}

				$currentFquest = mysqli_real_escape_string($this->connection, $currentFquest);
				$nextFquest = mysqli_real_escape_string($this->connection, $nextFquest);
				$q = "UPDATE " . TB_PREFIX . "users SET fquest = '$nextFquest', gold = gold + $gold WHERE id = $userid AND quest = 24 AND fquest = '$currentFquest' AND gold <= " . (4294967295 - $gold);
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

			function claimFollowupQuestResources($userid, $vref, $currentFquest, $nextFquest, $wood, $clay, $iron, $crop) {
				$userid = (int)$userid;
				$vref = (int)$vref;
				$wood = (int)$wood;
				$clay = (int)$clay;
				$iron = (int)$iron;
				$crop = (int)$crop;
				if($userid <= 0 || $vref <= 0 || min($wood, $clay, $iron, $crop) < 0 || !preg_match('/^[012](,[012]){10}$/', $currentFquest) || !preg_match('/^[012](,[012]){10}$/', $nextFquest)) {
					return false;
				}

				$currentFquest = mysqli_real_escape_string($this->connection, $currentFquest);
				$nextFquest = mysqli_real_escape_string($this->connection, $nextFquest);
				$q = "UPDATE " . TB_PREFIX . "users AS u INNER JOIN " . TB_PREFIX . "vdata AS v ON v.wref = $vref AND v.owner = u.id SET u.fquest = '$nextFquest', v.wood = v.wood + $wood, v.clay = v.clay + $clay, v.iron = v.iron + $iron, v.crop = v.crop + $crop WHERE u.id = $userid AND u.quest = 24 AND u.fquest = '$currentFquest'";
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_affected_rows($this->connection) === 2;
			}

			function markFollowupQuestAchieved($userid, $questIndex) {
				$userid = (int)$userid;
				$questIndex = (int)$questIndex;
				if($userid <= 0 || $questIndex < 0 || $questIndex > 10) {
					return false;
				}

				for($attempt = 0; $attempt < 3; $attempt++) {
					$result = mysqli_query($this->connection, "SELECT fquest FROM " . TB_PREFIX . "users WHERE id = $userid LIMIT 1");
					$row = $result ? mysqli_fetch_assoc($result) : false;
					$currentFquest = $row ? (string)$row['fquest'] : '';
					if(!preg_match('/^[012](,[012]){10}$/', $currentFquest)) {
						return false;
					}

					$states = explode(',', $currentFquest);
					if((int)$states[$questIndex] !== 0) {
						return true;
					}
					$states[$questIndex] = 2;
					$nextFquest = implode(',', $states);
					$currentEscaped = mysqli_real_escape_string($this->connection, $currentFquest);
					$nextEscaped = mysqli_real_escape_string($this->connection, $nextFquest);
					$result = mysqli_query(
						$this->connection,
						"UPDATE " . TB_PREFIX . "users SET fquest = '$nextEscaped' WHERE id = $userid AND fquest = '$currentEscaped'"
					);
					if($result && mysqli_affected_rows($this->connection) === 1) {
						return true;
					}
				}
				return false;
			}

			function hasBuildingAtLevelForUser($userid, $buildingType, $minimumLevel) {
				$userid = (int)$userid;
				$buildingType = (int)$buildingType;
				$minimumLevel = (int)$minimumLevel;
				if($userid <= 0 || $buildingType <= 0 || $minimumLevel <= 0) {
					return false;
				}

				$conditions = array();
				for($field = 19; $field <= 40; $field++) {
					$conditions[] = "(f.f{$field}t = $buildingType AND f.f$field >= $minimumLevel)";
				}
				$q = "SELECT 1 FROM " . TB_PREFIX . "fdata AS f"
					. " INNER JOIN " . TB_PREFIX . "vdata AS v ON v.wref = f.vref"
					. " WHERE v.owner = $userid AND (" . implode(' OR ', $conditions) . ") LIMIT 1";
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_num_rows($result) === 1;
			}

			function hasOwnSettlersForUser($userid, $tribe, $required = 3) {
				$userid = (int)$userid;
				$tribe = (int)$tribe;
				$required = (int)$required;
				if($userid <= 0 || $tribe < 1 || $tribe > 5 || $required <= 0) {
					return false;
				}

				$column = 'u' . ($tribe * 10);
				$q = "SELECT 1 FROM " . TB_PREFIX . "units AS units"
					. " INNER JOIN " . TB_PREFIX . "vdata AS v ON v.wref = units.vref"
					. " WHERE v.owner = $userid AND units.$column >= $required LIMIT 1";
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_num_rows($result) === 1;
			}

			function hasSettlementAttemptForQuest($userid) {
				$userid = (int)$userid;
				if($userid <= 0) {
					return false;
				}
				$q = "SELECT 1 FROM " . TB_PREFIX . "movement"
					. " WHERE sort_type = 5 AND data = '$userid' LIMIT 1";
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_num_rows($result) === 1;
			}

			function hasFoundedVillageForQuest($userid) {
				$userid = (int)$userid;
				if($userid <= 0) {
					return false;
				}
				$q = "SELECT 1 FROM " . TB_PREFIX . "movement AS movement"
					. " INNER JOIN " . TB_PREFIX . "vdata AS source"
					. " ON source.wref = movement.`from` AND source.owner = $userid"
					. " INNER JOIN " . TB_PREFIX . "vdata AS target"
					. " ON target.wref = movement.`to` AND target.owner = $userid"
					. " WHERE movement.sort_type = 5 AND movement.proc = 1"
					. " AND movement.data = '$userid' AND target.created >= movement.endtime"
					. " AND (source.exp1 = target.wref OR source.exp2 = target.wref OR source.exp3 = target.wref)"
					. " LIMIT 1";
				$result = mysqli_query($this->connection, $q);
				return $result && mysqli_num_rows($result) === 1;
			}

        	function setArchived($id) {
        		$q = "UPDATE " . TB_PREFIX . "mdata set archived = 1 where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

        	function setNorm($id) {
        		$q = "UPDATE " . TB_PREFIX . "mdata set archived = 0 where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

			/***************************
			Function to get messages
			Mode 1: Get inbox
			Mode 2: Get sent
			Mode 3: Get message
			Mode 4: Set viewed
			Mode 5: Remove message
			Mode 6: Retrieve archive
			References: User ID/Message ID, Mode
			***************************/
			function getMessage($id, $mode) {
				global $session;
				switch($mode) {
					case 1:
						$q = "SELECT * FROM " . TB_PREFIX . "mdata WHERE target = $id and send = 0 and archived = 0 ORDER BY time DESC";
						break;
					case 2:
						$q = "SELECT * FROM " . TB_PREFIX . "mdata WHERE owner = $id ORDER BY time DESC";
						break;
					case 3:
						$q = "SELECT * FROM " . TB_PREFIX . "mdata where id = $id";
						break;
					case 4:
						$q = "UPDATE " . TB_PREFIX . "mdata set viewed = 1 where id = $id AND target = $session->uid";
						break;
					case 5:
						$q = "UPDATE " . TB_PREFIX . "mdata set deltarget = 1 ,viewed = 1 where id = $id";
						break;
					case 6:
						$q = "SELECT * FROM " . TB_PREFIX . "mdata where target = $id and send = 0 and archived = 1";
						break;
					case 7:
						$q = "UPDATE " . TB_PREFIX . "mdata set delowner = 1 where id = $id";
						break;
					case 8:
						$q = "UPDATE " . TB_PREFIX . "mdata set deltarget = 1, delowner = 1, viewed = 1 where id = $id";
						break;
					case 9:
						$q = "SELECT * FROM " . TB_PREFIX . "mdata WHERE target = $id and send = 0 and archived = 0 and deltarget = 0 ORDER BY time DESC";
						break;
					case 10:
						$q = "SELECT * FROM " . TB_PREFIX . "mdata WHERE owner = $id and delowner = 0 ORDER BY time DESC";
						break;
					case 11:
						$q = "SELECT * FROM " . TB_PREFIX . "mdata where target = $id and send = 0 and archived = 1 and deltarget = 0";
						break;
				}
				if($mode <= 3 || $mode == 6 || $mode > 8) {
					$result = mysqli_query($this->connection,$q);
					return $this->mysqli_fetch_all($result);
				} else {
					return mysqli_query($this->connection,$q);
				}
			}

			function getUnreadMessageCount($id) {
				$q = "SELECT COUNT(1) 'count' FROM " . TB_PREFIX . "mdata where target = $id and viewed = 0";
				$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_assoc($result)['count'];
			}

			function getDelSent($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "mdata WHERE owner = $uid and delowner = 1 ORDER BY time DESC";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getDelInbox($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "mdata WHERE target = $uid and deltarget = 1 ORDER BY time DESC";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getDelArchive($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "mdata WHERE target = $uid and archived = 1 and deltarget = 1 OR owner = $uid and archived = 1 and delowner = 1 ORDER BY time DESC";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function unarchiveNotice($id, $uid) {
				$id = (int)$id;
				$uid = (int)$uid;
				$q = "UPDATE " . TB_PREFIX . "ndata set archive = 0 where id = $id and uid = $uid";
				return mysqli_query($this->connection,$q);
			}

			function archiveNotice($id, $uid) {
				$id = (int)$id;
				$uid = (int)$uid;
				$q = "update " . TB_PREFIX . "ndata set archive = 1 where id = $id and uid = $uid";
				return mysqli_query($this->connection,$q);
			}

			function removeNotice($id, $uid) {
				$id = (int)$id;
				$uid = (int)$uid;
				$q = "UPDATE " . TB_PREFIX . "ndata set del = 1 ,viewed = 1 where id = $id and uid = $uid";
				return mysqli_query($this->connection,$q);
			}

		function noticeViewed($id, $uid = null) {
			$id = (int)$id;
			$uidCondition = ($uid === null) ? "" : " and uid = ".(int)$uid;
			$q = "UPDATE " . TB_PREFIX . "ndata set viewed = 1 where id = $id$uidCondition";
			return mysqli_query($this->connection,$q);
		}

		function noticeUnviewed($id, $uid = null) {
			$id = (int)$id;
			$uidCondition = ($uid === null) ? "" : " and uid = ".(int)$uid;
			$q = "UPDATE " . TB_PREFIX . "ndata set viewed = 0 where id = $id$uidCondition";
			return mysqli_query($this->connection,$q);
		}

        	function addNotice($uid, $toWref, $ally, $type, $topic, $data, $time = 0) {
        		if($time == 0) {
        			$time = time();
        		}
        		$q = "INSERT INTO " . TB_PREFIX . "ndata (id, uid, toWref, ally, topic, ntype, data, time, viewed) values (0,'$uid','$toWref','$ally','$topic',$type,'$data',$time,0)";
        		return mysqli_query($this->connection,$q) or die(mysqli_error());
        	}

			function getNotice($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "ndata where uid = $uid and del = 0 ORDER BY time DESC";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getNotice2($id, $field) {
				$id = (int)$id;
				$allowedFields = array(
					'id', 'uid', 'toWref', 'ally', 'topic', 'ntype',
					'data', 'time', 'viewed', 'archive', 'del'
				);
				if($id <= 0 || !in_array($field, $allowedFields, true)) {
					return false;
				}
				$q = "SELECT `".$field."` FROM " . TB_PREFIX . "ndata where `id` = $id";
				$result = mysqli_query($this->connection,$q);
				$dbarray = $result ? mysqli_fetch_assoc($result) : false;
				return $dbarray ? $dbarray[$field] : false;
			}

			function getNotice3($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "ndata where uid = $uid ORDER BY time DESC";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getNotice4($id) {
				$id = (int)$id;
				if($id <= 0) {
					return array();
				}
				$q = "SELECT * FROM " . TB_PREFIX . "ndata where id = $id ORDER BY time DESC";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getAuthorizedNotice($uid, $alliance, $id) {
				$uid = (int)$uid;
				$alliance = (int)$alliance;
				$id = (int)$id;
				if($uid <= 0 || $id <= 0) {
					return false;
				}

				$allianceCondition = "";
				if($alliance > 0) {
					$allianceCondition = " OR (ally = $alliance"
						." AND ntype IN (0,1,2,3,4,5,6,7,15,16,17,18,19,20,21,22,23,24))";
				}
				$q = "SELECT * FROM " . TB_PREFIX . "ndata"
					." WHERE id = $id AND (uid = $uid$allianceCondition) LIMIT 1";
				$result = mysqli_query($this->connection,$q);
				$row = $result ? mysqli_fetch_assoc($result) : false;
				return $row ? $row : false;
			}

			function getNoticeNeighbors($uid, $alliance, $id, $filter = 0) {
				$uid = (int)$uid;
				$alliance = (int)$alliance;
				$id = (int)$id;
				$filter = (int)$filter;
				$neighbors = array('previous' => 0, 'next' => 0);
				if($uid <= 0 || $id <= 0) {
					return $neighbors;
				}

				$accessCondition = "uid = $uid AND del = 0";
				if($alliance > 0 && $filter === 0) {
					$accessCondition = "($accessCondition) OR (ally = $alliance"
						." AND ntype IN (0,1,2,3,4,5,6,7,15,16,17,18,19,20,21,22,23,24))";
				}
				$filterConditions = array(
					0 => "archive = 0",
					1 => "archive = 0 AND ntype IN (1,2,3,4,5,6,7,25)",
					2 => "archive = 0 AND ntype IN (10,11,12,13)",
					3 => "archive = 0 AND ntype IN (9,15,16,17,18,19,20,21)",
					4 => "archive = 1",
					5 => "archive = 0 AND ntype = 8",
					6 => "archive = 0 AND ntype IN (0,22,23,24)"
				);
				$filterCondition = isset($filterConditions[$filter])
					? $filterConditions[$filter]
					: $filterConditions[0];
				$q = "SELECT id FROM " . TB_PREFIX . "ndata"
					." WHERE ($accessCondition) AND $filterCondition ORDER BY time DESC, id DESC";
				$result = mysqli_query($this->connection, $q);
				$ids = array();
				while($result && $row = mysqli_fetch_assoc($result)) {
					$ids[] = (int)$row['id'];
				}
				$position = array_search($id, $ids, true);
				if($position !== false) {
					$neighbors['previous'] = isset($ids[$position - 1]) ? $ids[$position - 1] : 0;
					$neighbors['next'] = isset($ids[$position + 1]) ? $ids[$position + 1] : 0;
				}
				return $neighbors;
			}

			function getNotice5($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "ndata where uid = $uid and viewed = 0 ORDER BY time DESC";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getUnreadNoticeCount($uid) {
				$q = "SELECT COUNT(1) 'count' FROM " . TB_PREFIX . "ndata where uid = $uid and viewed = 0";
				$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_assoc($result)['count'];
			}

			function createTradeRoute($uid,$wid,$from,$r1,$r2,$r3,$r4,$start,$deliveries,$merchant,$time) {
				$values = array_map('intval',array($uid,$wid,$from,$r1,$r2,$r3,$r4,$start,$deliveries,$merchant,$time));
				list($uid,$wid,$from,$r1,$r2,$r3,$r4,$start,$deliveries,$merchant,$time) = $values;
				if($uid <= 0 || $wid <= 0 || $from <= 0 || $wid === $from || min($r1,$r2,$r3,$r4) < 0
					|| $r1 + $r2 + $r3 + $r4 <= 0 || $start < 0 || $start > 23
					|| $deliveries < 1 || $deliveries > 3 || $merchant <= 0 || $time <= 0) {
					return false;
				}
				mysqli_query($this->connection,"START TRANSACTION");
				$charged = mysqli_query($this->connection,"UPDATE " . TB_PREFIX . "users SET gold = gold - 2 WHERE id = $uid AND gold >= 2");
				if(!$charged || mysqli_affected_rows($this->connection) !== 1) {
					mysqli_query($this->connection,"ROLLBACK");
					return false;
				}
				$q = "INSERT into " . TB_PREFIX . "route values (0,$uid,$wid,$from,$r1,$r2,$r3,$r4,$start,$deliveries,$merchant,$time)";
				if(!mysqli_query($this->connection,$q)) {
					mysqli_query($this->connection,"ROLLBACK");
					return false;
				}
				return mysqli_query($this->connection,"COMMIT");
			}

			function getTradeRoute($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "route where uid = $uid ORDER BY timestamp ASC";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getTradeRoute2($id) {
				$id = (int) $id;
				$q = "SELECT * FROM " . TB_PREFIX . "route where id = $id";
				$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				return $dbarray;
			}

			function getTradeRouteUid($id) {
				$id = (int) $id;
				$q = "SELECT * FROM " . TB_PREFIX . "route where id = $id";
				$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				return $dbarray ? $dbarray['uid'] : 0;
			}

			function updateTradeRouteOwned($id,$uid,$from,$r1,$r2,$r3,$r4,$start,$deliveries,$merchant,$time) {
				$values = array_map('intval',array($id,$uid,$from,$r1,$r2,$r3,$r4,$start,$deliveries,$merchant,$time));
				list($id,$uid,$from,$r1,$r2,$r3,$r4,$start,$deliveries,$merchant,$time) = $values;
				if($id <= 0 || $uid <= 0 || $from <= 0 || min($r1,$r2,$r3,$r4) < 0
					|| $r1 + $r2 + $r3 + $r4 <= 0 || $start < 0 || $start > 23
					|| $deliveries < 1 || $deliveries > 3 || $merchant <= 0 || $time <= 0) {
					return false;
				}
				$q = "UPDATE " . TB_PREFIX . "route SET wood = $r1, clay = $r2, iron = $r3, crop = $r4, start = $start, deliveries = $deliveries, merchant = $merchant, timestamp = $time WHERE id = $id AND uid = $uid AND `from` = $from";
				return mysqli_query($this->connection,$q);
			}

			function deleteTradeRouteOwned($id,$uid,$from) {
				$id = (int) $id;
				$uid = (int) $uid;
				$from = (int) $from;
				$q = "DELETE FROM " . TB_PREFIX . "route WHERE id = $id AND uid = $uid AND `from` = $from";
				return mysqli_query($this->connection,$q);
			}

			function editTradeRoute($id,$column,$value,$mode) {
			if(!$mode){
				$q = "UPDATE " . TB_PREFIX . "route set $column = $value where id = $id";
			}else{
				$q = "UPDATE " . TB_PREFIX . "route set $column = $column + $value where id = $id";
			}
				return mysqli_query($this->connection,$q);
			}

			function deleteTradeRoute($id) {
				$q = "DELETE FROM " . TB_PREFIX . "route where id = $id";
				return mysqli_query($this->connection,$q);
			}

			function getAttacks($ref) {
        		$q = "SELECT * FROM " . TB_PREFIX . "attacks where id = '$ref'";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

			function getAlliAttacks($aid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "ndata WHERE ally = $aid ORDER BY time DESC";
        	}

        	function addBuilding($wid, $field, $type, $loop, $time, $master, $level) {
                $x = "UPDATE " . TB_PREFIX . "fdata SET f" . $field . "t=" . $type . " WHERE vref=" . $wid;
                mysqli_query($this->connection,$x) or die(mysqli_error());
                $q = "INSERT into " . TB_PREFIX . "bdata values (0,$wid,$field,$type,$loop,$time,$master,$level)";
                return mysqli_query($this->connection,$q);
            }

        	function removeBuilding($d) {
                global $building;
                $jobLoopconID = -1;
                $SameBuildCount = 0;
                $jobs = $building->buildArray;
                for($i = 0; $i < sizeof($jobs); $i++) {
                    if($jobs[$i]['id'] == $d) {
                        $jobDeleted = $i;
                    }
                    if($jobs[$i]['loopcon'] == 1) {
                        $jobLoopconID = $i;
                    }
                    if($jobs[$i]['master'] == 1) {
                        $jobMaster = $i;
                    }
                }
                if(count($jobs) > 1 && ($jobs[0]['field'] == $jobs[1]['field'])) {
                    $SameBuildCount = 1;
                }
                if(count($jobs) > 2 && ($jobs[0]['field'] == $jobs[2]['field'])) {
                    $SameBuildCount = 2;
                }
                if(count($jobs) > 2 && ($jobs[1]['field'] == $jobs[2]['field'])) {
                    $SameBuildCount = 3;
                }
				if(count($jobs) > 2 && ($jobs[0]['field'] == ($jobs[1]['field'] == $jobs[2]['field']))) {
                    $SameBuildCount = 4;
                }
				if(count($jobs) > 3 && ($jobs[0]['field'] == ($jobs[1]['field'] == $jobs[3]['field']))) {
                    $SameBuildCount = 5;
                }
				if(count($jobs) > 3 && ($jobs[0]['field'] == ($jobs[2]['field'] == $jobs[3]['field']))) {
                    $SameBuildCount = 6;
                }
				if(count($jobs) > 3 && ($jobs[1]['field'] == ($jobs[2]['field'] == $jobs[3]['field']))) {
                    $SameBuildCount = 7;
                }
				if(count($jobs) > 3 && ($jobs[0]['field'] == $jobs[3]['field'])) {
                    $SameBuildCount = 8;
                }
                if(count($jobs) > 3 && ($jobs[1]['field'] == $jobs[3]['field'])) {
                    $SameBuildCount = 9;
                }
                if(count($jobs) > 3 && ($jobs[2]['field'] == $jobs[3]['field'])) {
                    $SameBuildCount = 10;
                }
                if($SameBuildCount > 0) {
					if($SameBuildCount > 3){
					if($SameBuildCount == 4 or $SameBuildCount == 5){
					if($jobDeleted == 0){
					$uprequire = $building->resourceRequired($jobs[1]['field'],$jobs[1]['type'],1);
					$time = $uprequire['time'];
					$timestamp = $time+time();
					$q = "UPDATE " . TB_PREFIX . "bdata SET loopcon=0,level=level-1,timestamp=".$timestamp." WHERE id=".$jobs[1]['id']."";
                        mysqli_query($this->connection,$q);
					}
					}else if($SameBuildCount == 6){
					if($jobDeleted == 0){
					$uprequire = $building->resourceRequired($jobs[2]['field'],$jobs[2]['type'],1);
					$time = $uprequire['time'];
					$timestamp = $time+time();
					$q = "UPDATE " . TB_PREFIX . "bdata SET loopcon=0,level=level-1,timestamp=".$timestamp." WHERE id=".$jobs[2]['id']."";
                        mysqli_query($this->connection,$q);
					}
					}else if($SameBuildCount == 7){
					if($jobDeleted == 1){
					$uprequire = $building->resourceRequired($jobs[2]['field'],$jobs[2]['type'],1);
					$time = $uprequire['time'];
					$timestamp = $time+time();
					$q = "UPDATE " . TB_PREFIX . "bdata SET loopcon=0,level=level-1,timestamp=".$timestamp." WHERE id=".$jobs[2]['id']."";
                        mysqli_query($this->connection,$q);
					}
					}
					if($SameBuildCount < 8){
					$uprequire1 = $building->resourceRequired($jobs[$jobMaster]['field'],$jobs[$jobMaster]['type'],2);
					$time1 = $uprequire1['time'];
					$timestamp1 = $time1;
					$q1 = "UPDATE " . TB_PREFIX . "bdata SET level=level-1,timestamp=".$timestamp1." WHERE id=".$jobs[$jobMaster]['id']."";
                        mysqli_query($this->connection,$q1);
					}else{
					$uprequire1 = $building->resourceRequired($jobs[$jobMaster]['field'],$jobs[$jobMaster]['type'],1);
					$time1 = $uprequire1['time'];
					$timestamp1 = $time1;
					$q1 = "UPDATE " . TB_PREFIX . "bdata SET level=level-1,timestamp=".$timestamp1." WHERE id=".$jobs[$jobMaster]['id']."";
                        mysqli_query($this->connection,$q1);
					}
					}else if($d == $jobs[floor($SameBuildCount / 3)]['id'] || $d == $jobs[floor($SameBuildCount / 2) + 1]['id']) {
                        $q = "UPDATE " . TB_PREFIX . "bdata SET loopcon=0,level=level-1,timestamp=" . $jobs[floor($SameBuildCount / 3)]['timestamp'] . " WHERE master = 0 AND id > ".$d." and (ID=" . $jobs[floor($SameBuildCount / 3)]['id'] . " OR ID=" . $jobs[floor($SameBuildCount / 2) + 1]['id'] . ")";
                        mysqli_query($this->connection,$q);
                    }
                } else {
                    if($jobs[$jobDeleted]['field'] >= 19) {
                        $x = "SELECT f" . $jobs[$jobDeleted]['field'] . " FROM " . TB_PREFIX . "fdata WHERE vref=" . $jobs[$jobDeleted]['wid'];
                        $result = mysqli_query($this->connection,$x) or die(mysqli_error());
                        $fieldlevel = mysqli_fetch_row($result);
                        if($fieldlevel[0] == 0) {
                            $x = "UPDATE " . TB_PREFIX . "fdata SET f" . $jobs[$jobDeleted]['field'] . "t=0 WHERE vref=" . $jobs[$jobDeleted]['wid'];
                            mysqli_query($this->connection,$x) or die(mysqli_error());
                        }
                    }
                    if(($jobLoopconID >= 0) && ($jobs[$jobDeleted]['loopcon'] != 1)) {
                        if(($jobs[$jobLoopconID]['field'] <= 18 && $jobs[$jobDeleted]['field'] <= 18) || ($jobs[$jobLoopconID]['field'] >= 19 && $jobs[$jobDeleted]['field'] >= 19) || sizeof($jobs) < 3) {
                            $uprequire = $building->resourceRequired($jobs[$jobLoopconID]['field'], $jobs[$jobLoopconID]['type']);
                            $x = "UPDATE " . TB_PREFIX . "bdata SET loopcon=0,timestamp=" . (time() + $uprequire['time']) . " WHERE wid=" . $jobs[$jobDeleted]['wid'] . " AND loopcon=1 AND master=0";
                            mysqli_query($this->connection,$x) or die(mysqli_error());
                        }
                    }
                }
                $q = "DELETE FROM " . TB_PREFIX . "bdata where id = $d";
                return mysqli_query($this->connection,$q);
            }

        	function addDemolition($wid, $field) {
        		global $building, $village;
				$q = "DELETE FROM ".TB_PREFIX."bdata WHERE field=$field AND wid=$wid";
				mysqli_query($this->connection,$q);
        		$uprequire = $building->resourceRequired($field,$village->resarray['f'.$field.'t']);
        		$q = "INSERT INTO ".TB_PREFIX."demolition VALUES (".$wid.",".$field.",".($this->getFieldLevel($wid,$field)-1).",".(time()+floor($uprequire['time']/2)).")";
				return mysqli_query($this->connection,$q);
        	}


        	function getDemolition($wid = 0) {
        		if($wid) {
        			$q = "SELECT * FROM " . TB_PREFIX . "demolition WHERE vref=" . $wid;
        		} else {
        			$q = "SELECT * FROM " . TB_PREFIX . "demolition WHERE timetofinish<=" . time();
        		}
        		$result = mysqli_query($this->connection,$q);
				if(!empty($result)) {
	        		return $this->mysqli_fetch_all($result);
				} else {
					return NULL;
				}
        	}

        	function finishDemolition($wid) {
        		$q = "UPDATE " . TB_PREFIX . "demolition SET timetofinish=" . time() . " WHERE vref=" . $wid;
        		return mysqli_query($this->connection,$q);
        	}

        	function delDemolition($wid) {
        		$q = "DELETE FROM " . TB_PREFIX . "demolition WHERE vref=" . $wid;
        		return mysqli_query($this->connection,$q);
        	}

        	function getJobs($wid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid order by master,timestamp ASC";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

            function FinishWoodcutter($wid) {
				$time = time()-1;
                $q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and type = 1 order by master,timestamp ASC";
                $result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				$q = "UPDATE ".TB_PREFIX."bdata SET timestamp = $time WHERE id = '".$dbarray['id']."'";
                $this->query($q);

				$tribe = $this->getUserField($this->getVillageField($wid, "owner"), "tribe", 0);
				if($tribe == 1){
				$q2 = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and loopcon = 1 and field <= 18 order by master,timestamp ASC";
				}else{
				$q2 = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and loopcon = 1 order by master,timestamp ASC";
				}
				$result2 = mysqli_query($this->connection,$q2);
				if(mysqli_num_rows($result2) > 0){
				$dbarray2 = mysqli_fetch_array($result2);
				$wc_time = $dbarray['timestamp'];
				$q2 = "UPDATE ".TB_PREFIX."bdata SET timestamp = timestamp - $wc_time WHERE id = '".$dbarray2['id']."'";
				$this->query($q2);
				}
            }

			function FinishRallyPoint($wid) {
				$time = time()-1;
                $q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and type = 16 order by master,timestamp ASC";
                $result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				$q = "UPDATE ".TB_PREFIX."bdata SET timestamp = $time WHERE id = '".$dbarray['id']."'";
                $this->query($q);

				$tribe = $this->getUserField($this->getVillageField($wid, "owner"), "tribe", 0);
				if($tribe == 1){
				$q2 = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and loopcon = 1 and field >= 19 order by master,timestamp ASC";
				}else{
				$q2 = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and loopcon = 1 order by master,timestamp ASC";
				}
				$result2 = mysqli_query($this->connection,$q2);
				if(mysqli_num_rows($result2) > 0){
				$dbarray2 = mysqli_fetch_array($result2);
				$wc_time = $dbarray['timestamp'];
				$q2 = "UPDATE ".TB_PREFIX."bdata SET timestamp = timestamp - $wc_time WHERE id = '".$dbarray2['id']."'";
				$this->query($q2);
				}
            }

            function getMasterJobs($wid) {
                $q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and master = 1 order by master,timestamp ASC";
                $result = mysqli_query($this->connection,$q);
                return $this->mysqli_fetch_all($result);
            }

            function getMasterJobsByField($wid,$field) {
                $q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and field = $field and master = 1 order by master,timestamp ASC";
                $result = mysqli_query($this->connection,$q);
                return $this->mysqli_fetch_all($result);
            }

            function getBuildingByField($wid,$field) {
                $q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and field = $field and master = 0";
                $result = mysqli_query($this->connection,$q);
                return $this->mysqli_fetch_all($result);
            }

            function getBuildingByType($wid,$type) {
                $q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and type = $type and master = 0";
                $result = mysqli_query($this->connection,$q);
                return $this->mysqli_fetch_all($result);
            }

            function getDorf1Building($wid) {
                $q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and field < 19 and master = 0";
                $result = mysqli_query($this->connection,$q);
                return $this->mysqli_fetch_all($result);
            }

            function getDorf2Building($wid) {
                $q = "SELECT * FROM " . TB_PREFIX . "bdata where wid = $wid and field > 18 and master = 0";
                $result = mysqli_query($this->connection,$q);
                return $this->mysqli_fetch_all($result);
            }

            function updateBuildingWithMaster($id, $time,$loop) {
                $q = "UPDATE " . TB_PREFIX . "bdata SET master = 0, timestamp = ".$time.",loopcon = ".$loop." WHERE id = ".$id."";
                return mysqli_query($this->connection,$q);
            }

			function getVillageByName($name) {
				$name = mysqli_real_escape_string($this->connection,(string)$name);
				$q = "SELECT wref FROM " . TB_PREFIX . "vdata where name = '$name' limit 1";
        		$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				return $dbarray ? $dbarray['wref'] : 0;
        	}

        	/***************************
        	Function to set accept flag on market
        	References: id
        	***************************/
        	function setMarketAcc($id) {
                $id = (int) $id;
        		$q = "UPDATE " . TB_PREFIX . "market set accept = 1 where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

            function claimMarketOffer($id, $buyerVid, $buyerAlliance) {
                $id = (int) $id;
                $buyerVid = (int) $buyerVid;
                $buyerAlliance = (int) $buyerAlliance;
                $q = "UPDATE " . TB_PREFIX . "market SET accept = 1 WHERE id = $id AND accept = 0 AND vref != $buyerVid AND (alliance = 0 OR alliance = $buyerAlliance)";
                $result = mysqli_query($this->connection,$q);
                return $result && mysqli_affected_rows($this->connection) === 1;
            }

            function claimOwnedMarketOffer($id, $vref) {
                $id = (int) $id;
                $vref = (int) $vref;
                $q = "UPDATE " . TB_PREFIX . "market SET accept = 1 WHERE id = $id AND vref = $vref AND accept = 0";
                $result = mysqli_query($this->connection,$q);
                return $result && mysqli_affected_rows($this->connection) === 1;
            }

            function releaseMarketOffer($id) {
                $id = (int) $id;
                $q = "UPDATE " . TB_PREFIX . "market SET accept = 0 WHERE id = $id AND accept = 1";
                return mysqli_query($this->connection,$q);
            }

        	/***************************
        	Function to send resource to other village
        	Mode 0: Send
        	Mode 1: Cancel
        	References: Wood/ID, Clay, Iron, Crop, Mode
        	***************************/
        	function sendResource($ref, $clay, $iron, $crop, $merchant, $mode) {
                $ref = (int) $ref;
                $clay = (int) $clay;
                $iron = (int) $iron;
                $crop = (int) $crop;
                $merchant = (int) $merchant;
        		if(!$mode) {
        			$q = "INSERT INTO " . TB_PREFIX . "send values (0,$ref,$clay,$iron,$crop,$merchant)";
        			mysqli_query($this->connection,$q);
        			return mysqli_insert_id($this->connection);
        		} else {
        			$q = "DELETE FROM " . TB_PREFIX . "send where id = $ref";
        			return mysqli_query($this->connection,$q);
        		}
        	}

        	/***************************
        	Function to get resources back if you delete offer
        	References: VillageRef (vref)
        	Made by: Dzoki
        	***************************/

        	function getResourcesBack($vref, $gtype, $gamt) {
                $vref = (int) $vref;
                $gtype = (int) $gtype;
                $gamt = (int) $gamt;
        		//Xtype (1) = wood, (2) = clay, (3) = iron, (4) = crop
        		if($gtype == 1) {
        			$q = "UPDATE " . TB_PREFIX . "vdata SET `wood` = `wood` + '$gamt' WHERE wref = $vref";
        			return mysqli_query($this->connection,$q);
        		} else
        			if($gtype == 2) {
        				$q = "UPDATE " . TB_PREFIX . "vdata SET `clay` = `clay` + '$gamt' WHERE wref = $vref";
        				return mysqli_query($this->connection,$q);
        			} else
        				if($gtype == 3) {
        					$q = "UPDATE " . TB_PREFIX . "vdata SET `iron` = `iron` + '$gamt' WHERE wref = $vref";
        					return mysqli_query($this->connection,$q);
        				} else
        					if($gtype == 4) {
        						$q = "UPDATE " . TB_PREFIX . "vdata SET `crop` = `crop` + '$gamt' WHERE wref = $vref";
        						return mysqli_query($this->connection,$q);
        					}
        	}

        	/***************************
        	Function to get info about offered resources
        	References: VillageRef (vref)
        	Made by: Dzoki
        	***************************/

        	function getMarketField($vref, $field) {
                $vref = (int) $vref;
                if(!in_array($field, array('gtype', 'gamt', 'wtype', 'wamt', 'merchant'), true)) {
                    return false;
                }
        		$q = "SELECT $field FROM " . TB_PREFIX . "market where vref = '$vref'";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray[$field];
        	}

        	function removeAcceptedOffer($id) {
                $id = (int) $id;
        		$q = "DELETE FROM " . TB_PREFIX . "market where id = $id";
                return mysqli_query($this->connection,$q);
        	}

        	/***************************
        	Function to add market offer
        	Mode 0: Add
        	Mode 1: Cancel
        	References: Village, Give, Amt, Want, Amt, Time, Alliance, Mode
        	***************************/
        	function addMarket($vid, $gtype, $gamt, $wtype, $wamt, $time, $alliance, $merchant, $mode) {
                $vid = (int) $vid;
                $gtype = (int) $gtype;
                $gamt = (int) $gamt;
                $wtype = (int) $wtype;
                $wamt = (int) $wamt;
                $time = (int) $time;
                $alliance = (int) $alliance;
                $merchant = (int) $merchant;
        		if(!$mode) {
        			$q = "INSERT INTO " . TB_PREFIX . "market values (0,$vid,$gtype,$gamt,$wtype,$wamt,0,$time,$alliance,$merchant)";
        			mysqli_query($this->connection,$q);
        			return mysqli_insert_id($this->connection);
        		} else {
        			$q = "DELETE FROM " . TB_PREFIX . "market where id = $gtype and vref = $vid";
        			return mysqli_query($this->connection,$q);
        		}
        	}

        	/***************************
        	Function to get market offer
        	References: Village, Mode
        	***************************/
        	function getMarket($vid, $mode) {
                $vid = (int) $vid;
        		$alliance = $this->getUserField($this->getVillageField($vid, "owner"), "alliance", 0);
                $alliance = (int) $alliance;
        		if(!$mode) {
        			$q = "SELECT * FROM " . TB_PREFIX . "market where vref = $vid and accept = 0 ORDER BY id DESC";
        		} else {
                    $q = "SELECT * FROM " . TB_PREFIX . "market WHERE vref != $vid AND accept = 0 AND (alliance = 0 OR alliance = $alliance) ORDER BY id DESC";
        		}
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	/***************************
        	Function to get market offer
        	References: ID
        	***************************/
        	function getMarketInfo($id) {
                $id = (int) $id;
        		$q = "SELECT * FROM " . TB_PREFIX . "market where id = $id";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

        	function setMovementProc($moveid) {
        		$q = "UPDATE " . TB_PREFIX . "movement set proc = 1 where moveid = $moveid";
        		return mysqli_query($this->connection,$q);
        	}

			function claimMovementProc($moveid) {
				$moveid = (int) $moveid;
				$q = "UPDATE " . TB_PREFIX . "movement SET proc = 1 WHERE moveid = $moveid AND proc = 0";
				$result = mysqli_query($this->connection,$q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

        	/***************************
        	Function to retrieve used merchant
        	References: Village
        	***************************/
        	function totalMerchantUsed($vid) {
        		$time = time();
        		$q = "SELECT sum(" . TB_PREFIX . "send.merchant) from " . TB_PREFIX . "send, " . TB_PREFIX . "movement where " . TB_PREFIX . "movement.from = $vid and " . TB_PREFIX . "send.id = " . TB_PREFIX . "movement.ref and " . TB_PREFIX . "movement.proc = 0 and sort_type = 0";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		$q2 = "SELECT sum(ref) from " . TB_PREFIX . "movement where sort_type = 2 and " . TB_PREFIX . "movement.to = $vid and proc = 0";
        		$result2 = mysqli_query($this->connection,$q2);
        		$row2 = mysqli_fetch_row($result2);
        		$q3 = "SELECT sum(merchant) from " . TB_PREFIX . "market where vref = $vid and accept = 0";
        		$result3 = mysqli_query($this->connection,$q3);
        		$row3 = mysqli_fetch_row($result3);
        		return $row[0] + $row2[0] + $row3[0];
        	}

			function getOrdinaryTroopReturnsInWindow($village, $windowStart, $windowEnd) {
				$village = (int)$village;
				$windowStart = (int)$windowStart;
				$windowEnd = (int)$windowEnd;
				if($village <= 0 || $windowEnd < $windowStart) {
					return array();
				}

				$q = "SELECT moveid, `from`, `to`, endtime, proc"
					." FROM " . TB_PREFIX . "movement"
					." WHERE `to` = $village"
					." AND sort_type = 4"
					." AND `from` <> 0"
					." AND endtime >= $windowStart"
					." AND endtime <= $windowEnd"
					." ORDER BY endtime ASC";
				$result = mysqli_query($this->connection,$q);
				return $result ? $this->mysqli_fetch_all($result) : array();
			}

        	/***************************
        	Function to retrieve movement of village
        	Type 0: Send Resource
        	Type 1: Send Merchant
        	Type 2: Return Resource
        	Type 3: Attack
        	Type 4: Return
        	Type 5: Settler
        	Type 6: Bounty
			Type 7: Reinf.
			Type 9: Adventure
        	Mode 0: Send/Out
        	Mode 1: Recieve/In
        	References: Type, Village, Mode
        	***************************/
        	function getMovement($type, $village, $mode) {
        		$time = time();
        		if(!$mode) {
        			$where = "from";
        		} else {
        			$where = "to";
        		}
        		switch($type) {
        			case 0:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement, " . TB_PREFIX . "send where " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "send.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 0";
        				break;
        			case 2:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement where " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 2";
        				break;
        			case 3:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement, " . TB_PREFIX . "attacks where " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 3 ORDER BY endtime ASC";
        				break;
        			case 4:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement, " . TB_PREFIX . "attacks where " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 4 ORDER BY endtime ASC";
        				break;
        			case 5:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement where " . TB_PREFIX . "movement." . $where . " = $village and sort_type = 5 and proc = 0";
        				break;
        			case 6:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement," . TB_PREFIX . "odata, " . TB_PREFIX . "attacks where " . TB_PREFIX . "odata.conqured = $village and " . TB_PREFIX . "movement.to = " . TB_PREFIX . "odata.wref and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 3 ORDER BY endtime ASC";
        				break;
					case 9:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement where " . TB_PREFIX . "movement." . $where . " = $village and sort_type = 9 and proc = 0";
        				break;
        			case 34:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement, " . TB_PREFIX . "attacks where " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 3 or " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX .
        					"movement.sort_type = 4 ORDER BY endtime ASC";
        				break;
        		}
        		$result = mysqli_query($this->connection,$q);
        		$array = $this->mysqli_fetch_all($result);
        		return $array;
        	}

			/***************************
        	Function to retrieve movement of village
        	Type 3: Attack
        	Type 4: Return
        	Type 5: Settler
        	Type 6: Bounty
			Type 7: Reinf.
			Type 9: Adventure
        	Mode 0: Send/Out
        	Mode 1: Recieve/In
        	References: Type, Village, Mode
        	***************************/
			function getMovement2($type, $village, $mode) {
        		$time = time();
        		if(!$mode) {
        			$where = "from";
        		} else {
        			$where = "to";
        		}
        		switch($type) {
					case 3:
					$hiddenScout = $mode ? " and " . TB_PREFIX . "attacks.attack_type != 1" : "";
					$q = "SELECT * FROM " . TB_PREFIX . "movement, " . TB_PREFIX . "attacks where " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 3 and " . TB_PREFIX . "attacks.attack_type != 2" . $hiddenScout . " ORDER BY endtime DESC";
						break;
        			case 34:
					$q = "SELECT * FROM " . TB_PREFIX . "movement, " . TB_PREFIX . "attacks where " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 3 and " . TB_PREFIX . "attacks.attack_type = 2 or " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX .
        					"movement.sort_type = 4 ORDER BY endtime DESC";
        				break;
        			case 5:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement where " . TB_PREFIX . "movement." . $where . " = $village and sort_type = 5 and proc = 0";
        				break;
        			case 7:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement, " . TB_PREFIX . "attacks where " . TB_PREFIX . "movement." . $where . " = $village and " . TB_PREFIX . "movement.ref = " . TB_PREFIX . "attacks.id and " . TB_PREFIX . "movement.proc = 0 and " . TB_PREFIX . "movement.sort_type = 3 and " . TB_PREFIX . "attacks.attack_type = 2 ORDER BY endtime DESC";
        				break;
        			case 9:
        				$q = "SELECT * FROM " . TB_PREFIX . "movement where " . TB_PREFIX . "movement." . $where . " = $village and sort_type = 9 and proc = 0";
        				break;
        			default:
        				return array();

        		}
        		$result = mysqli_query($this->connection,$q);
        		$array = $this->mysqli_fetch_all($result);
        		return $array;
        	}

        	function addA2b($ckey, $timestamp, $to, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11, $type) {
        		$q = "INSERT INTO " . TB_PREFIX . "a2b (ckey,time_check,to_vid,u1,u2,u3,u4,u5,u6,u7,u8,u9,u10,u11,type) VALUES ('$ckey', '$timestamp', '$to', '$t1', '$t2', '$t3', '$t4', '$t5', '$t6', '$t7', '$t8', '$t9', '$t10', '$t11', '$type')";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

        	function getA2b($ckey, $check) {
        		$q = "SELECT * from " . TB_PREFIX . "a2b where ckey = '" . $ckey . "' AND time_check = '" . $check . "'";
        		$result = mysqli_query($this->connection,$q);
        		if($result) {
        			return mysqli_fetch_assoc($result);
        		} else {
        			return false;
        		}
        	}

			function addMovement($type, $from, $to, $ref, $data, $endtime, $send = 1, $wood = 0, $clay = 0, $iron = 0, $crop = 0, $ref2 = 0) {
				$q = "INSERT INTO " . TB_PREFIX . "movement values (0,$type,$from,$to,$ref,$ref2,'$data',$endtime,0,$send,$wood,$clay,$iron,$crop)";
				return mysqli_query($this->connection,$q);
			}

			function removeMarketMovementBySend($ref) {
				$ref = (int) $ref;
				$q = "DELETE FROM " . TB_PREFIX . "movement WHERE sort_type = 0 AND ref = $ref AND proc = 0";
				return mysqli_query($this->connection,$q);
			}

			function getOutgoingMovement($moveid, $from, $owner) {
				$moveid = (int) $moveid;
				$from = (int) $from;
				$owner = (int) $owner;
				$q = "SELECT m.*, a.attack_type FROM " . TB_PREFIX . "movement m INNER JOIN " . TB_PREFIX . "attacks a ON a.id = m.ref INNER JOIN " . TB_PREFIX . "vdata v ON v.wref = m.`from` WHERE m.moveid = $moveid AND m.`from` = $from AND v.owner = $owner AND m.sort_type = 3 AND m.proc = 0 LIMIT 1";
				$result = mysqli_query($this->connection,$q);
				return $result ? mysqli_fetch_assoc($result) : false;
			}

			function cancelOutgoingMovement($moveid, $from, $sentAt, $now, $returnEndtime) {
				$moveid = (int) $moveid;
				$from = (int) $from;
				$sentAt = (int) $sentAt;
				$now = (int) $now;
				$returnEndtime = (int) $returnEndtime;

				$q = "UPDATE " . TB_PREFIX . "movement SET proc = 1 WHERE moveid = $moveid AND `from` = $from AND sort_type = 3 AND proc = 0 AND data = '$sentAt' AND endtime > $now";
				mysqli_query($this->connection,$q);
				if(mysqli_affected_rows($this->connection) !== 1) {
					return false;
				}

				$q = "INSERT INTO " . TB_PREFIX . "movement (sort_type, `from`, `to`, ref, ref2, data, endtime, proc, send, wood, clay, iron, crop) SELECT 4, `to`, `from`, ref, 0, '0,0,0,0,0', $returnEndtime, 0, 1, 0, 0, 0, 0 FROM " . TB_PREFIX . "movement WHERE moveid = $moveid LIMIT 1";
				$result = mysqli_query($this->connection,$q);
				if(!$result || mysqli_affected_rows($this->connection) !== 1) {
					mysqli_query($this->connection,"UPDATE " . TB_PREFIX . "movement SET proc = 0 WHERE moveid = $moveid AND `from` = $from AND data = '$sentAt'");
					return false;
				}

				return true;
			}

        	function addAttack($vid, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10, $t11, $type, $ctar1, $ctar2, $spy) {
        		$q = "INSERT INTO " . TB_PREFIX . "attacks values (0,$vid,$t1,$t2,$t3,$t4,$t5,$t6,$t7,$t8,$t9,$t10,$t11,$type,$ctar1,$ctar2,$spy)";
        		mysqli_query($this->connection,$q);
        		return mysqli_insert_id($this->connection);
        	}

        	function modifyAttack($aid, $unit, $amt) {
        		$unit = 't' . $unit;
        		$q = "UPDATE " . TB_PREFIX . "attacks set $unit = $unit - $amt where id = $aid";
        		return mysqli_query($this->connection,$q);
        	}

			function modifyAttack2($aid, $unit, $amt) {
        		$unit = 't' . $unit;
        		$q = "UPDATE " . TB_PREFIX . "attacks set $unit = $unit + $amt where id = $aid";
        		return mysqli_query($this->connection,$q);
        	}

        	function getRanking() {
        		$q = "SELECT id,username,alliance,ap,apall,dp,dpall,access FROM " . TB_PREFIX . "users WHERE tribe<=3 AND access<" . (INCLUDE_ADMIN ? "10" : "8");
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

			function getBuildList($type) {
				$type = (int) $type;
				$wid = isset($_SESSION['wid']) ? (int) $_SESSION['wid'] : 0;
				$q = "SELECT * FROM " . TB_PREFIX . "bdata WHERE wid = $wid AND type = $type";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

        	function getVRanking() {
        		$q = "SELECT v.wref,v.name,v.owner,v.pop FROM " . TB_PREFIX . "vdata AS v," . TB_PREFIX . "users AS u WHERE v.owner=u.id AND u.tribe<=3 AND v.wref != '' AND u.access<" . (INCLUDE_ADMIN ? "10" : "8");
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function getARanking($limit="") {
        		$q = "SELECT id,name,tag,oldrank,Aap,Adp FROM " . TB_PREFIX . "alidata where id != '' $limit";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

			function getARanking2() {
        		$q = "SELECT * FROM " . TB_PREFIX . "alidata where id != ''";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_num_rows($result);
        	}

			function getARanking3($limit="") {
        		$q = "SELECT * FROM " . TB_PREFIX . "alidata where id != '' $limit";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

        	function getHeroRanking() {
        		$q = "SELECT * FROM " . TB_PREFIX . "hero";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

		function getAllMember($aid, $rankOnly = false) {
				$rankFilter = $rankOnly ? " AND tribe <= 3 AND access < " . (INCLUDE_ADMIN ? "10" : "8") : "";
				$q = "SELECT * FROM " . TB_PREFIX . "users WHERE alliance = $aid" . $rankFilter . " ORDER BY (SELECT sum(pop) FROM " . TB_PREFIX . "vdata WHERE owner =  " . TB_PREFIX . "users.id) DESC";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function addUnits($vid) {
        		$q = "INSERT into " . TB_PREFIX . "units (vref) values ($vid)";
        		return mysqli_query($this->connection,$q);
        	}

        	function getUnit($vid) {
				$q = "SELECT * FROM " . TB_PREFIX . "units where vref = ".$vid."";
        		$result = mysqli_query($this->connection,$q);
        		if (!empty($result)) {
					return mysqli_fetch_assoc($result);
				} else {
					return NULL;
				}
        	}
			function getHUnit($vid) {
				$q = "SELECT hero FROM " . TB_PREFIX . "units where vref = ".$vid."";
        		$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
        		if ($dbarray['hero']!=0) {
					return true;
				} else {
					return false;
				}
        	}

        	function getHero($uid=0) {
				if (!$uid) {
					$q = "SELECT * FROM ".TB_PREFIX."hero";
				} else {
	        		$q = "SELECT * FROM ".TB_PREFIX."hero WHERE dead=0 AND uid=$uid LIMIT 1";
				}
        		$result = mysqli_query($this->connection,$q);
        		if (!empty($result)) {
					return $this->mysqli_fetch_all($result);
				} else {
					return NULL;
				}
        	}

			function modifyHero($column,$value,$heroid,$mode=0) {
				if(!$mode){
					$q = "UPDATE ".TB_PREFIX."hero SET $column = $value WHERE heroid = $heroid";
				} elseif($mode==1){
					$q = "UPDATE ".TB_PREFIX."hero SET $column = $column + $value WHERE heroid = $heroid";
				} elseif($mode==2){
					$q = "UPDATE ".TB_PREFIX."hero SET $column = $column - $value WHERE heroid = $heroid";
				}
				return mysqli_query($this->connection,$q);
			}

				function modifyHero2($column,$value,$uid,$mode) {
					if(!$mode){
						$q = "UPDATE ".TB_PREFIX."hero SET $column = $value WHERE uid = $uid";
				} elseif($mode==1){
					$q = "UPDATE ".TB_PREFIX."hero SET $column = $column + $value WHERE uid = $uid";
				} elseif($mode==2){
					$q = "UPDATE ".TB_PREFIX."hero SET $column = $column - $value WHERE uid = $uid";
				}
					return mysqli_query($this->connection,$q);
				}

				function allocateHeroAttributePoint($uid,$attribute,$limit=100) {
					$attributes = array('power','offBonus','defBonus','product');
					if(!in_array($attribute,$attributes,true)){
						return false;
					}

					$uid = (int)$uid;
					$limit = max(1,(int)$limit);
					$q = "UPDATE ".TB_PREFIX."hero SET $attribute = $attribute + 1, points = points - 1"
						." WHERE uid = $uid AND points > 0 AND $attribute < $limit";
					$result = mysqli_query($this->connection,$q);
					return $result && mysqli_affected_rows($this->connection)===1;
				}

				function advanceHeroLevel($heroid,$currentLevel,$targetLevel) {
					$heroid = (int)$heroid;
					$currentLevel = max(0,(int)$currentLevel);
					$targetLevel = max($currentLevel,(int)$targetLevel);
					if($heroid<1 || $targetLevel===$currentLevel){
						return false;
					}

					$awardedPoints = 4*($targetLevel-$currentLevel);
					$q = "UPDATE ".TB_PREFIX."hero SET level = $targetLevel, points = points + $awardedPoints"
						." WHERE heroid = $heroid AND level = $currentLevel";
					$result = mysqli_query($this->connection,$q);
					return $result && mysqli_affected_rows($this->connection)===1;
				}

				function resetHeroAttributes($uid) {
					$uid = (int)$uid;
					$q = "UPDATE ".TB_PREFIX."hero SET"
						." points = points + power + offBonus + defBonus + product,"
						." power = 0, offBonus = 0, defBonus = 0, product = 0,"
						." r0 = 1, r1 = 0, r2 = 0, r3 = 0, r4 = 0"
						." WHERE uid = $uid";
					return mysqli_query($this->connection,$q);
				}

				function consumeBookOfWisdom($uid,$itemid) {
					$uid = (int)$uid;
					$itemid = (int)$itemid;
					if($uid<1 || $itemid<1){
						return false;
					}

					$q = "UPDATE ".TB_PREFIX."hero AS hero"
						." INNER JOIN ".TB_PREFIX."heroitems AS item ON item.uid = hero.uid"
						." SET hero.points = hero.points + hero.power + hero.offBonus + hero.defBonus + hero.product,"
						." hero.power = 0, hero.offBonus = 0, hero.defBonus = 0, hero.product = 0,"
						." hero.r0 = 1, hero.r1 = 0, hero.r2 = 0, hero.r3 = 0, hero.r4 = 0,"
						." item.proc = 1"
						." WHERE hero.uid = $uid AND hero.dead = 0"
						." AND item.id = $itemid AND item.uid = $uid"
						." AND item.btype = 13 AND item.num = 1 AND item.proc = 0";
					$result = mysqli_query($this->connection,$q);
					return $result && mysqli_affected_rows($this->connection)>0;
				}

				function setHeroResourceMode($uid,$mode) {
					$uid = (int)$uid;
					$mode = max(0,min(4,(int)$mode));
					$values = array(0,0,0,0,0);
					$values[$mode] = 1;
					$q = "UPDATE ".TB_PREFIX."hero SET"
						." r0 = ".$values[0].", r1 = ".$values[1].", r2 = ".$values[2]
						.", r3 = ".$values[3].", r4 = ".$values[4]." WHERE uid = $uid";
					return mysqli_query($this->connection,$q);
				}

				function addTech($vid) {
        		$q = "INSERT into " . TB_PREFIX . "tdata (vref) values ($vid)";
        		return mysqli_query($this->connection,$q);
        	}

        	function addABTech($vid) {
        		$q = "INSERT into " . TB_PREFIX . "abdata (vref) values ($vid)";
        		return mysqli_query($this->connection,$q);
        	}

        	function getABTech($vid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "abdata where vref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

        	function addResearch($vid, $tech, $time) {
        		$q = "INSERT into " . TB_PREFIX . "research values (0,$vid,'$tech',$time)";
        		return mysqli_query($this->connection,$q);
        	}

        	function getResearching($vid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "research where vref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function checkIfResearched($vref, $unit) {
        		$q = "SELECT $unit FROM " . TB_PREFIX . "tdata WHERE vref = $vref";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray[$unit];
        	}

        	function getTech($vid) {
        		$q = "SELECT * from " . TB_PREFIX . "tdata where vref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

        	function getTraining($vid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "training where vref = $vid ORDER BY id";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function countTraining($vid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "training WHERE vref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		return $row[0];
        	}

			function trainUnit($vid, $unit, $amt, $pop, $each, $time, $mode) {
				global $village, $building, $session, $technology;

		if(!$mode) {
			$barracks = array(1,2,3,11,12,13,14,21,22,31,32,33,34,41,42,43,44);
			$greatbarracks = array(61,62,63,71,72,73,74,81,82,91,92,93,94,101,102,103,104);
			$stables = array(4,5,6,15,16,23,24,25,26,35,36,45,46);
			$greatstables = array(64,65,66,75,76,83,84,85,86,95,96,105,106);
			$workshop = array(7,8,17,18,27,28,37,38,47,48);
			$greatworkshop = array(67,68,77,78,87,88,97,98,107,108);
			$residence = array(9,10,19,20,29,30,39,40,49,50);
			$trapper = array(99);

			if(in_array($unit, $barracks)) {
				$queued = $technology->getTrainingList(1);
			} elseif(in_array($unit, $stables)) {
				$queued = $technology->getTrainingList(2);
			} elseif(in_array($unit, $workshop)) {
				$queued = $technology->getTrainingList(3);
			} elseif(in_array($unit, $residence)) {
				$queued = $technology->getTrainingList(4);
			} elseif(in_array($unit, $greatstables)) {
				$queued = $technology->getTrainingList(6);
			} elseif(in_array($unit, $greatbarracks)) {
				$queued = $technology->getTrainingList(5);
			} elseif(in_array($unit, $greatworkshop)) {
				$queued = $technology->getTrainingList(7);
			} elseif(in_array($unit, $trapper)) {
				$queued = $technology->getTrainingList(8);
			}
			$now = time();

			if($each == 0){ $each = 1; }
			$time2 = $now+$each;
			if(count($queued) > 0) {
			$time += $queued[count($queued) - 1]['timestamp'] - $now;
			$time2 += $queued[count($queued) - 1]['timestamp'] - $now;
			}
			if(!empty($queued) && $queued[count($queued) - 1]['unit'] == $unit){
			$time = $amt*$queued[count($queued) - 1]['eachtime'];
					$q = "UPDATE " . TB_PREFIX . "training SET amt = amt + $amt, timestamp = timestamp + $time WHERE id = ".$queued[count($queued) - 1]['id']."";
			}else{
					$q = "INSERT INTO " . TB_PREFIX . "training values (0,$vid,$unit,$amt,$pop,$time,$each,$time2)";
			}
				} else {
					$q = "DELETE FROM " . TB_PREFIX . "training where id = $vid";
				}
				return mysqli_query($this->connection,$q);
			}

			function getHeroTrain($vid) {
        		$q = "SELECT * from " . TB_PREFIX . "training where vref = $vid and unit = 0";
        		$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
					if(empty($result)) {
						return false;
					} else {
						return $dbarray;
					}
        	}

			function trainHero($vid, $each, $mode) {
        		if(!$mode) {
        			$time = time();
        			$q = "INSERT INTO " . TB_PREFIX . "training values (0,$vid,0,1,6,$time,$each,$each)";
        		} else {
        			$q = "DELETE FROM " . TB_PREFIX . "training where id = $vid";
        		}
        		return mysqli_query($this->connection,$q);
        	}

			function updateTraining($id, $trained, $each) {
				$q = "UPDATE " . TB_PREFIX . "training set amt = amt - $trained, timestamp2 = timestamp2 + $each where id = $id";
				return mysqli_query($this->connection,$q);
			}

        	function modifyUnit($vref, $unit, $amt, $mode) {
        		if($unit == 230) {
        			$unit = 30;
        		}
        		if($unit == 231) {
        			$unit = 31;
        		}
        		if($unit == 120) {
        			$unit = 20;
        		}
        		if($unit == 121) {
        			$unit = 21;
        		}
				if($unit == 'hero'){
					$unit = 'hero';
				}else{
        			$unit = 'u' . $unit;
				}
        		if(!$mode) {
        			$q = "UPDATE " . TB_PREFIX . "units set $unit = $unit - $amt where vref = $vref";
        		} else {
        			$q = "UPDATE " . TB_PREFIX . "units set $unit = $unit + $amt where vref = $vref";
        		}
        		return mysqli_query($this->connection,$q);
        	}

			function deductUnitIfAvailable($vref, $unit, $amt) {
				$vref = (int) $vref;
				$amt = (int) $amt;
				if($amt <= 0) {
					return false;
				}
				if($unit === 'hero') {
					$column = 'hero';
				} else {
					$unit = (int) $unit;
					if($unit === 230) $unit = 30;
					if($unit === 231) $unit = 31;
					if($unit === 120) $unit = 20;
					if($unit === 121) $unit = 21;
					if($unit < 1 || $unit > 50) {
						return false;
					}
					$column = 'u' . $unit;
				}
				$q = "UPDATE " . TB_PREFIX . "units SET $column = $column - $amt WHERE vref = $vref AND $column >= $amt";
				$result = mysqli_query($this->connection,$q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

			function refundFoundingAssets($vref, $owner, $settlerUnit) {
				$vref = (int)$vref;
				$owner = (int)$owner;
				$settlerUnit = (int)$settlerUnit;
				if($vref <= 0 || $owner <= 0 || $settlerUnit < 1 || $settlerUnit > 50) {
					return false;
				}
				$column = 'u'.$settlerUnit;
				$q = "UPDATE " . TB_PREFIX . "units u INNER JOIN " . TB_PREFIX . "vdata v ON v.wref = u.vref SET u.$column = u.$column + 3, v.wood = v.wood + 750, v.clay = v.clay + 750, v.iron = v.iron + 750, v.crop = v.crop + 750 WHERE u.vref = $vref AND v.owner = $owner";
				$result = mysqli_query($this->connection,$q);
				return $result && mysqli_affected_rows($this->connection) === 1;
			}

        	function getEnforce($vid, $from) {
        		$q = "SELECT * from " . TB_PREFIX . "enforcement where `from` = $from and vref = $vid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

			function checkEnforce($vid, $from) {
				$q = "SELECT * from " . TB_PREFIX . "enforcement where `from` = $from and vref = $vid";
        		$result = mysqli_query($this->connection,$q);
				if(!empty($result)) {
					return mysqli_insert_id($this->connection);
				}else{
					return true;
				}
			}


        	function addEnforce($data) {
        		$q = "INSERT into " . TB_PREFIX . "enforcement (vref,`from`) values (" . $data['to'] . "," . $data['from'] . ")";
        		mysqli_query($this->connection,$q);
        		$id = mysqli_insert_id($this->connection);
				if($data['from'] != 0){
        		$owntribe = $this->getUserField($this->getVillageField($data['from'], "owner"), "tribe", 0);
				}else{
				$owntribe = 4;
				}
        		$start = ($owntribe - 1) * 10 + 1;
        		$end = ($owntribe * 10);
        		//add unit
        		$j = '1';
        		for($i = $start; $i <= $end; $i++) {
        			$this->modifyEnforce($id, $i, $data['t' . $j . ''], 1);
        			$j++;
        		}
        		return mysqli_insert_id($this->connection);
        	}

			function addHeroEnforce($data) {
				$q = "INSERT into " . TB_PREFIX . "enforcement (`vref`,`from`,`hero`) values (" . $data['to'] . "," . $data['from'] . ",1)";
        		mysqli_query($this->connection,$q);
        	}


        	function modifyEnforce($id, $unit, $amt, $mode) {
				if($unit == 'hero'){
					$unit = 'hero';
				}else{
					$unit = 'u' . $unit;
				}
        		if(!$mode) {
        			$q = "UPDATE " . TB_PREFIX . "enforcement set $unit = $unit - $amt where id = $id";
        		} else {
        			$q = "UPDATE " . TB_PREFIX . "enforcement set $unit = $unit + $amt where id = $id";
        		}
        		mysqli_query($this->connection,$q);
        	}

        	function getEnforceArray($id, $mode) {
        		if(!$mode) {
        			$q = "SELECT * from " . TB_PREFIX . "enforcement where id = $id";
        		} else {
        			$q = "SELECT * from " . TB_PREFIX . "enforcement where `from` = $id";
        		}
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_assoc($result);
        	}

        	function getEnforceVillage($id, $mode) {
        		if(!$mode) {
        			$q = "SELECT * from " . TB_PREFIX . "enforcement where `vref` = '$id'";
        		} else {
        			$q = "SELECT * from " . TB_PREFIX . "enforcement where `from` = '$id'";
        		}
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function getVillageMovement($id) {
        		$vinfo = $this->getVillage($id);
        		$vtribe = $this->getUserField($vinfo['owner'], "tribe", 0);
        		$movingunits = array();
        		$outgoingarray = $this->getMovement(3, $id, 0);
        		if(!empty($outgoingarray)) {
			foreach($outgoingarray as $out) {
				for($i = 1; $i <= 10; $i++) {
					$key = 'u' . (($vtribe - 1) * 10 + $i);
					$movingunits[$key] = (int)(isset($movingunits[$key]) ? $movingunits[$key] : 0)
						+ (int)$out['t' . $i];
				}
				$movingunits['hero'] = (int)(isset($movingunits['hero']) ? $movingunits['hero'] : 0)
					+ (int)$out['t11'];
			}
        		}
        		$returningarray = $this->getMovement(4, $id, 1);
        		if(!empty($returningarray)) {
        			foreach($returningarray as $ret) {
				if($ret['attack_type'] != 1) {
					for($i = 1; $i <= 10; $i++) {
						$key = 'u' . (($vtribe - 1) * 10 + $i);
						$movingunits[$key] = (int)(isset($movingunits[$key]) ? $movingunits[$key] : 0)
							+ (int)$ret['t' . $i];
					}
					$movingunits['hero'] = (int)(isset($movingunits['hero']) ? $movingunits['hero'] : 0)
						+ (int)$ret['t11'];
				}
        			}
        		}
        		$settlerarray = $this->getMovement(5, $id, 0);
        		if(!empty($settlerarray)) {
        			$movingunits['u' . ($vtribe * 10)] += 3 * count($settlerarray);
        		}
        		return $movingunits;
        	}

        	################# -START- ##################
        	##   WORLD WONDER STATISTICS FUNCTIONS!   ##
        	############################################

        	/***************************
        	Function to get all World Wonders
        	Made by: Dzoki
        	***************************/

        	function getWW() {
        		$q = "SELECT * FROM " . TB_PREFIX . "fdata WHERE f99t = 40";
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return true;
        		} else {
        			return false;
        		}
        	}

        	/***************************
        	Function to get world wonder level!
        	Made by: Dzoki
        	***************************/

        	function getWWLevel($vref) {
        		$q = "SELECT f99 FROM " . TB_PREFIX . "fdata WHERE vref = $vref";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['f99'];
        	}

        	/***************************
        	Function to get world wonder owner ID!
        	Made by: Dzoki
        	***************************/

        	function getWWOwnerID($vref) {
        		$q = "SELECT owner FROM " . TB_PREFIX . "vdata WHERE wref = $vref";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['owner'];
        	}

        	/***************************
        	Function to get user alliance name!
        	Made by: Dzoki
        	***************************/

        	function getUserAllianceID($id) {
        		$q = "SELECT alliance FROM " . TB_PREFIX . "users where id = $id";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['alliance'];
        	}

        	/***************************
        	Function to get WW name
        	Made by: Dzoki
        	***************************/

        	function getWWName($vref) {
        		$q = "SELECT wwname FROM " . TB_PREFIX . "fdata WHERE vref = $vref";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['wwname'];
        	}

        	/***************************
        	Function to change WW name
        	Made by: Dzoki
        	***************************/

        	function submitWWname($vref, $name) {
        		$q = "UPDATE " . TB_PREFIX . "fdata SET `wwname` = '$name' WHERE " . TB_PREFIX . "fdata.`vref` = $vref";
        		return mysqli_query($this->connection,$q);
        	}

        	//medal functions
        	function addclimberpop($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "users set Rc = Rc + '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
        	function addclimberrankpop($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "users set clp = clp + '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
        	function removeclimberrankpop($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "users set clp = clp - '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
        	function updateoldrank($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "users set oldrank = '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
			function setclimberrankpop($user, $cp) {
				$q = "UPDATE " . TB_PREFIX . "users set clp = '$cp' where id = $user";
				return mysqli_query($this->connection,$q);
			}
        	function removeclimberpop($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "users set Rc = Rc - '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
        	// ALLIANCE MEDAL FUNCTIONS
        	function addclimberpopAlly($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "alidata set Rc = Rc + '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
        	function addclimberrankpopAlly($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "alidata set clp = clp + '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
        	function removeclimberrankpopAlly($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "alidata set clp = clp - '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
        	function updateoldrankAlly($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "alidata set oldrank = '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}
        	function removeclimberpopAlly($user, $cp) {
        		$q = "UPDATE " . TB_PREFIX . "alidata set Rc = Rc - '$cp' where id = $user";
        		return mysqli_query($this->connection,$q);
        	}

        	function modifyCommence($id) {
        		$time = time();
        		$q = "UPDATE " . TB_PREFIX . "training set commence = $time WHERE id=$id";

        		return mysqli_query($this->connection,$q);
        	}


        	function getTrainingList() {
        		$q = "SELECT * FROM " . TB_PREFIX . "training where vref != ''";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function getNeedDelete() {
        		$time = time();
        		$q = "SELECT uid FROM " . TB_PREFIX . "deleting where timestamp < $time";
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	function countUser() {
                        $q = "SELECT count(id) FROM " . TB_PREFIX . "users WHERE tribe BETWEEN 1 AND 3 AND access = " . USER;
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		return $row[0];
        	}

        	function countAlli() {
        		$q = "SELECT count(id) FROM " . TB_PREFIX . "alidata where id != 0";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		return $row[0];
        	}

        	/***************************
        	Function to process MYSQLi->fetch_all (Only exist in MYSQL)
        	References: Result
        	***************************/
        	function mysqli_fetch_all($result) {
        		$all = array();
        		if($result) {
        			while($row = mysqli_fetch_assoc($result)) {
        				$all[] = $row;
        			}
        		}
        		return $all;
        	}

        	function query_return($q) {
        		$result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
        	}

        	/***************************
        	Function to do free query
        	References: Query
        	***************************/
        	function query($query) {
        		return mysqli_query($this->connection,$query);
        	}

        	function RemoveXSS($val) {
        		return htmlspecialchars($val, ENT_QUOTES);
        	}

        	//MARKET FIXES
        	function getWoodAvailable($wref) {
        		$q = "SELECT wood FROM " . TB_PREFIX . "vdata WHERE wref = $wref";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['wood'];
        	}

        	function getClayAvailable($wref) {
        		$q = "SELECT clay FROM " . TB_PREFIX . "vdata WHERE wref = $wref";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['clay'];
        	}

        	function getIronAvailable($wref) {
        		$q = "SELECT iron FROM " . TB_PREFIX . "vdata WHERE wref = $wref";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['iron'];
        	}

        	function getCropAvailable($wref) {
        		$q = "SELECT crop FROM " . TB_PREFIX . "vdata WHERE wref = $wref";
        		$result = mysqli_query($this->connection,$q) or die(mysqli_error());
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['crop'];
        	}

        	function Getowner($vid) {
        		$s = "SELECT owner FROM " . TB_PREFIX . "vdata where wref = $vid";
        		$result1 = mysqli_query($this->connection, $s);
        		$row1 = mysqli_fetch_row($result1);
        		return $row1[0];
        	}

        	public function debug($time, $uid, $debug_info) {
        		$q = "INSERT INTO " . TB_PREFIX . "debug_info (time,uid,debug_info) VALUES ($time,$uid,$debug_info)";
        		if(mysqli_query($this->connection,$q)) {
        			return mysqli_insert_id($this->connection);
        		} else {
        			return false;
        		}
        	}
        	function populateOasisdata() {
        		$q2 = "SELECT * FROM " . TB_PREFIX . "wdata where oasistype != 0";
        		$result2 = mysqli_query($this->connection, $q2);
        		while($row = mysqli_fetch_array($result2)) {
        			$wid = $row['id'];
switch($row['oasistype']) {
case 1:
$tt =  "1000,1000,1000,1000,1000,1000";
break;
case 2:
$tt =  "2000,1000,1000,2000,1000,2000";
break;
case 3:
$tt =  "2000,1000,1000,2000,2000,2000";
break;
case 4:
$tt =  "1000,1000,1000,1000,1000,1000";
break;
case 5:
$tt =  "1000,2000,1000,2000,1000,2000";
break;
case 6:
$tt =  "1000,2000,1000,2000,1000,2000";
break;
case 7:
$tt =  "1000,1000,1000,1000,1000,1000";
break;
case 8:
$tt =  "1000,1000,2000,2000,1000,2000";
break;
case 9:
$tt =  "1000,1000,2000,2000,2000,2000";
break;
case 10:
$tt =  "1000,1000,1000,1000,1000,1000";
break;
case 11:
$tt =  "1000,1000,1000,2000,2000,2000";
break;
case 12:
$tt =  "1000,1000,1000,2000,2000,2000";
break;
}
        			$basearray = $this->getOMInfo($wid);
        			//We switch type of oasis and instert record with apropriate infomation.
        			$q = "INSERT into " . TB_PREFIX . "odata VALUES ('" . $basearray['id'] . "'," . $basearray['oasistype'] . ",0,".$tt."," . time() .",".time(). ",100,3,'Oasis sin ocupar')";
        			$result = mysqli_query($this->connection,$q);
        		}
        	}

        	public function getAvailableExpansionTraining() {
        		global $building, $session, $technology, $village;
        		$q = "SELECT (IF(exp1=0,1,0)+IF(exp2=0,1,0)+IF(exp3=0,1,0)) FROM " . TB_PREFIX . "vdata WHERE wref = $village->wid";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
		$emptySlots = $row ? (int)$row[0] : 0;
		$occupiedSlots = 3 - $emptySlots;
		$residence = (int)$building->getTypeLevel(25);
		$palace = (int)$building->getTypeLevel(26);
		$unlockedSlots = $residence >= 20 ? 2 : ($residence >= 10 ? 1 : 0);
		if($palace >= 20) {
			$unlockedSlots = max($unlockedSlots,3);
		} elseif($palace >= 15) {
			$unlockedSlots = max($unlockedSlots,2);
		} elseif($palace >= 10) {
			$unlockedSlots = max($unlockedSlots,1);
        		}
		$maxslots = max(0,$unlockedSlots - $occupiedSlots);

        		$q = "SELECT (u10+u20+u30) FROM " . TB_PREFIX . "units WHERE vref = $village->wid";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		$settlers = $row[0];
        		$q = "SELECT (u9+u19+u29) FROM " . TB_PREFIX . "units WHERE vref = $village->wid";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
        		$chiefs = $row[0];

        		$settlers += 3 * count($this->getMovement(5, $village->wid, 0));
        		$current_movement = $this->getMovement(3, $village->wid, 0);
        		if(!empty($current_movement)) {
        			foreach($current_movement as $build) {
        				$settlers += $build['t10'];
        				$chiefs += $build['t9'];
        			}
        		}
        		$current_movement = $this->getMovement(3, $village->wid, 1);
        		if(!empty($current_movement)) {
        			foreach($current_movement as $build) {
        				$settlers += $build['t10'];
        				$chiefs += $build['t9'];
        			}
        		}
        		$current_movement = $this->getMovement(4, $village->wid, 0);
        		if(!empty($current_movement)) {
        			foreach($current_movement as $build) {
        				$settlers += $build['t10'];
        				$chiefs += $build['t9'];
        			}
        		}
        		$current_movement = $this->getMovement(4, $village->wid, 1);
        		if(!empty($current_movement)) {
        			foreach($current_movement as $build) {
        				$settlers += $build['t10'];
        				$chiefs += $build['t9'];
        			}
        		}
		$q = "SELECT COALESCE(SUM(u10+u20+u30),0) FROM " . TB_PREFIX . "enforcement WHERE `from` = $village->wid";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
		$settlers += $row ? (int)$row[0] : 0;
		$q = "SELECT COALESCE(SUM(u9+u19+u29),0) FROM " . TB_PREFIX . "enforcement WHERE `from` = $village->wid";
        		$result = mysqli_query($this->connection,$q);
        		$row = mysqli_fetch_row($result);
		$chiefs += $row ? (int)$row[0] : 0;
		$q = "SELECT COALESCE(SUM(t10),0),COALESCE(SUM(t9),0) FROM " . TB_PREFIX . "prisoners WHERE `from` = $village->wid";
		$result = mysqli_query($this->connection,$q);
		$row = mysqli_fetch_row($result);
		if($row) {
			$settlers += (int)$row[0];
			$chiefs += (int)$row[1];
        		}
        		$trainlist = $technology->getTrainingList(4);
        		if(!empty($trainlist)) {
        			foreach($trainlist as $train) {
        				if($train['unit'] % 10 == 0) {
        					$settlers += $train['amt'];
        				}
        				if($train['unit'] % 10 == 9) {
        					$chiefs += $train['amt'];
        				}
        			}
        		}
		$settlerslots = max(0,$maxslots * 3 - $settlers - $chiefs * 3);
		$chiefslots = max(0,$maxslots - $chiefs - floor(($settlers + 2) / 3));

        		if(!$technology->getTech(($session->tribe - 1) * 10 + 9)) {
        			$chiefslots = 0;
        		}
		$slots = array("chiefs" => max(0,(int)$chiefslots), "settlers" => max(0,(int)$settlerslots));
        		return $slots;
        	}

	function addArtefact($vref, $owner, $type, $size, $name, $desc, $effect, $img) {
		$q = "INSERT INTO `" . TB_PREFIX . "artefacts` (`vref`, `owner`, `type`, `size`, `conquered`, `name`, `desc`, `effect`, `img`) VALUES ('$vref', '$owner', '$type', '$size', '" . time() . "', '$name', '$desc', '$effect', '$img')";
		return mysqli_query($this->connection,$q);
	}

			function getOwnArtefactInfo($vref) {
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE vref = $vref";
				$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_array($result);
			}

			function getOwnArtefactInfo2($vref) {
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE vref = $vref";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getOwnArtefactInfo3($uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE owner = $uid";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getOwnArtefactInfoByType($vref, $type) {
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE vref = $vref AND type = $type order by size";
				$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_array($result);
			}

			function getOwnArtefactInfoByType2($vref, $type) {
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE vref = $vref AND type = $type";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getOwnUniqueArtefactInfo($id, $type, $size) {
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE owner = $id AND type = $type AND size=$size";
				$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_array($result);
			}

			function getOwnUniqueArtefactInfo2($id, $type, $size, $mode) {
			if(!$mode){
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE owner = $id AND active = 1 AND type = $type AND size=$size";
			}else{
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE vref = $id AND active = 1 AND type = $type AND size=$size";
			}
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function getFoolArtefactInfo($type,$vid,$uid) {
				$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE vref = $vid AND type = 8 AND kind = $type OR owner = $uid AND size > 1 AND active = 1 AND type = 8 AND kind = $type";
				$result = mysqli_query($this->connection,$q);
				return $this->mysqli_fetch_all($result);
			}

			function claimArtefact($vref, $ovref, $id) {
				$time = time();
				$q = "UPDATE " . TB_PREFIX . "artefacts SET vref = $vref, owner = $id WHERE vref = $ovref";
				return mysqli_query($this->connection,$q);
			}

        	function getArtefactDetails($id) {
        		$q = "SELECT * FROM " . TB_PREFIX . "artefacts WHERE id = " . $id . "";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function HeroFace($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "heroface WHERE uid = ".$uid."";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function addHeroFace($uid, $bread, $ear, $eye, $eyebrow, $face, $hair, $mouth, $nose, $color) {

				$q = "INSERT INTO `" . TB_PREFIX . "heroface` (`beard`, `ear`, `eye`, `eyebrow`, `face`, `hair`, `mouth`, `nose`, `color`, `foot`, `helmet`, `horse`, `leftHand`, `rightHand`) VALUES ('$bread', '$ear', '$eye', '$eyebrow', '$face', '$hair', '$mouth', '$nose', '$color', '0', '0', '0', 'leftHand', 'rightHand')";
        		return mysqli_query($this->connection,$q);
        	}

			function modifyHeroFace($uid,$column,$value) {
                $q = "UPDATE ".TB_PREFIX."heroface SET $column = $value WHERE uid = $uid";
                return mysqli_query($this->connection,$q);
            }

			function modifyHeroXp($column,$value,$uid) {
                $q = "UPDATE ".TB_PREFIX."hero SET $column = $column + $value WHERE uid = $uid";
                return mysqli_query($this->connection,$q);
            }

			function populateOasisUnitsLow() {
        		$q2 = "SELECT * FROM " . TB_PREFIX . "wdata where oasistype != 0";
        		$result2 = mysqli_query($this->connection, $q2);
        		while($row = mysqli_fetch_array($result2)) {
        			$wid = $row['id'];
        			$basearray = $this->getMInfo($wid);
        			//each Troop is a Set for oasis type like mountains have rats spiders and snakes fields tigers elphants clay wolves so on stonger one more not so less
        			switch($basearray['oasistype']) {
        				case 1:
        				case 2:
							// Oasis Random populate
							$UP35 = rand(5, 30);
							$UP36 = rand(5, 30);
							$UP37 = rand(0, 30);
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u35 = u35 +  '" . $UP35 . "', u36 = u36 + '" . $UP36 . "', u37 = u37 + '" . $UP37 . "' WHERE vref = '" . $wid . "'";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 3:
							// Oasis Random populate
							$UP35 = rand(5, 30);
							$UP36 = rand(5, 30);
							$UP37 = rand(1, 30);
							$UP39 = rand(0, 10);
							$fil = rand(0,20);
							if($fil == 1){
								$UP40 = rand(0, 31);
							}else{
								$UP40 = 0;
							}
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u35 = u35 +  '" . $UP35 . "', u36 = u36 + '" . $UP36 . "', u37 = u37 + '" . $UP37 . "', u39 = u39 + '" . $UP39 . "', u40 = u40 + '" . $UP40 . "' WHERE vref = '" . $wid . "'";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 4:
        				case 5:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP32 = rand(5, 30);
							$UP35 = rand(0, 25);
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u32 = u32 + '" . $UP32 . "', u35 = u35 + '" . $UP35 . "' WHERE vref = '" . $wid . "'";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 6:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP32 = rand(5, 30);
							$UP35 = rand(1, 25);
							$UP38 = rand(0, 15);
							$fil = rand(0,20);
							if($fil == 1){
								$UP40 = rand(0, 31);
							}else{
								$UP40 = 0;
							}
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u32 = u32 + '" . $UP32 . "', u35 = u35 + '" . $UP35 . "', u38 = u38 + '" . $UP38 . "', u40 = u40 + '" . $UP40 . "' WHERE vref = '" . $wid . "'";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 7:
        				case 8:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP32 = rand(5, 30);
							$UP34 = rand(0, 25);
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u32 = u32 + '" . $UP32 . "', u34 = u34 + '" . $UP34 . "' WHERE vref = '" . $wid . "'";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 9:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP32 = rand(5, 30);
							$UP34 = rand(1, 25);
							$UP37 = rand(0, 15);
							$fil = rand(0,20);
							if($fil == 1){
								$UP40 = rand(0, 31);
							}else{
								$UP40 = 0;
							}
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u32 = u32 + '" . $UP32 . "', u34 = u34 + '" . $UP34 . "', u37 = u37 + '" . $UP37 . "', u40 = u40 + '" . $UP40 . "' WHERE vref = '" . $wid . "'";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 10:
        				case 11:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP33 = rand(5, 30);
							$UP37 = rand(1, 25);
							$UP39 = rand(0, 25);
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u33 = u33 + '" . $UP33 . "', u37 = u37 + '" . $UP37 . "', u39 = u39 + '" . $UP39 . "' WHERE vref = '" . $wid . "'";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 12:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP33 = rand(5, 30);
							$UP38 = rand(1, 25);
							$UP39 = rand(0, 25);
							$fil = rand(0,20);
							if($fil == 1){
								$UP40 = rand(0, 31);
							}else{
								$UP40 = 0;
							}
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u33 = u33 + '" . $UP33 . "', u38 = u38 + '" . $UP38 . "', u39 = u39 + '" . $UP39 . "', u40 = u40 + '" . $UP40 . "' WHERE vref = '" . $wid . "'";
        					$result = mysqli_query($this->connection,$q);
        					break;
        			}
        		}
        	}

			function populateOasisUnitsLow2($wid) {
        			$basearray = $this->getMInfo($wid);
					$max = rand(80, 120);
        			//each Troop is a Set for oasis type like mountains have rats spiders and snakes fields tigers elphants clay wolves so on stonger one more not so less
        			switch($basearray['oasistype']) {
        				case 1:
        				case 2:
							// Oasis Random populate
							$UP35 = rand(5, 30);
							$UP36 = rand(5, 30);
							$UP37 = rand(0, 30);
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u35 = u35 +  '" . $UP35 . "', u36 = u36 + '" . $UP36 . "', u37 = u37 + '" . $UP37 . "' WHERE vref = '" . $wid . "' AND u35 <= ".$max." AND u36 <= ".$max." AND u37 <= ".$max."";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 3:
							// Oasis Random populate
							$UP35 = rand(5, 30);
							$UP36 = rand(5, 30);
							$UP37 = rand(1, 30);
							$UP39 = rand(0, 10);
							$fil = rand(0,10);
							if($fil == 1){
								$UP40 = rand(0, 31);
							}else{
								$UP40 = 0;
							}
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u35 = u35 +  '" . $UP35 . "', u36 = u36 + '" . $UP36 . "', u37 = u37 + '" . $UP37 . "', u39 = u39 + '" . $UP39 . "', u40 = u40 + '" . $UP40 . "' WHERE vref = '" . $wid . "' AND u35 <= ".$max." AND u36 <= ".$max." AND u37 <= ".$max."  AND u39 <= ".$max." AND u40 <= ".$max."";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 4:
        				case 5:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP32 = rand(5, 30);
							$UP35 = rand(0, 25);
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u32 = u32 + '" . $UP32 . "', u35 = u35 + '" . $UP35 . "' WHERE vref = '" . $wid . "' AND u31 <= ".$max." AND u32 <= ".$max." AND u35 <= ".$max."";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 6:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP32 = rand(5, 30);
							$UP35 = rand(1, 25);
							$UP38 = rand(0, 15);
							$fil = rand(0,10);
							if($fil == 1){
								$UP40 = rand(0, 31);
							}else{
								$UP40 = 0;
							}
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u32 = u32 + '" . $UP32 . "', u35 = u35 + '" . $UP35 . "', u38 = u38 + '" . $UP38 . "', u40 = u40 + '" . $UP40 . "' WHERE vref = '" . $wid . "' AND u31 <= ".$max." AND u32 <= ".$max." AND u35 <= ".$max." AND u38 <= ".$max." AND u40 <= ".$max."";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 7:
        				case 8:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP32 = rand(5, 30);
							$UP34 = rand(0, 25);
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u32 = u32 + '" . $UP32 . "', u34 = u34 + '" . $UP34 . "' WHERE vref = '" . $wid . "' AND u31 <= ".$max." AND u32 <= ".$max." AND u34 <= ".$max."";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 9:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP32 = rand(5, 30);
							$UP34 = rand(1, 25);
							$UP37 = rand(0, 15);
							$fil = rand(0,10);
							if($fil == 1){
								$UP40 = rand(0, 31);
							}else{
								$UP40 = 0;
							}
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u32 = u32 + '" . $UP32 . "', u34 = u34 + '" . $UP34 . "', u37 = u37 + '" . $UP37 . "', u40 = u40 + '" . $UP40 . "' WHERE vref = '" . $wid . "' AND u31 <= ".$max." AND u32 <= ".$max." AND u34 <= ".$max." AND u37 <= ".$max." AND u40 <= ".$max."";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 10:
        				case 11:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP33 = rand(5, 30);
							$UP37 = rand(1, 25);
							$UP39 = rand(0, 25);
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u33 = u33 + '" . $UP33 . "', u37 = u37 + '" . $UP37 . "', u39 = u39 + '" . $UP39 . "' WHERE vref = '" . $wid . "' AND u31 <= ".$max." AND u33 <= ".$max." AND u37 <= ".$max." AND u39 <= ".$max."";
        					$result = mysqli_query($this->connection,$q);
        					break;
        				case 12:
							// Oasis Random populate
							$UP31 = rand(5, 40);
							$UP33 = rand(5, 30);
							$UP38 = rand(1, 25);
							$UP39 = rand(0, 25);
							$fil = rand(0,10);
							if($fil == 1){
								$UP40 = rand(0, 31);
							}else{
								$UP40 = 0;
							}
							//+25% lumber per hour
        					$q = "UPDATE " . TB_PREFIX . "units SET u31 = u31 +  '" . $UP31 . "', u33 = u33 + '" . $UP33 . "', u38 = u38 + '" . $UP38 . "', u39 = u39 + '" . $UP39 . "', u40 = u40 + '" . $UP40 . "' WHERE vref = '" . $wid . "' AND u31 <= ".$max." AND u33 <= ".$max." AND u38 <= ".$max." AND u39 <= ".$max." AND u40 <= ".$max."";
        					$result = mysqli_query($this->connection,$q);
        					break;
        			}
        	}

			public function hasBeginnerProtection($vid) {
				$q = "SELECT u.protect FROM ".TB_PREFIX."users u,".TB_PREFIX."vdata v WHERE u.id=v.owner AND v.wref=".$vid;
				$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				if(!empty($dbarray)) {
					if(time()<$dbarray[0]) {
						return true;
					} else {
						return false;
					}
				} else {
					return false;
				}
			}

			function addCLP($uid, $clp) {
        		$q = "UPDATE " . TB_PREFIX . "users set clp = clp + $clp where id = $uid";
        		return mysqli_query($this->connection,$q);
        	}

			function sendwlcMessage($client, $owner, $topic, $message, $send) {
        		$time = time();
        		$q = "INSERT INTO " . TB_PREFIX . "mdata values (0,$client,$owner,'$topic',\"$message\",1,0,$send,$time)";
        		return mysqli_query($this->connection,$q);
        	}

			function getLinks($id){
                $q = 'SELECT * FROM ' . TB_PREFIX . 'links WHERE `userid` = '.$id.' ORDER BY `pos` ASC';
                $result = mysqli_query($this->connection,$q);
        		return $this->mysqli_fetch_all($result);
            }

            function removeLinks($id,$uid){
                $q = "DELETE FROM " . TB_PREFIX . "links WHERE `id` = ".$id." and `userid` = ".$uid."";
                return mysqli_query($this->connection,$q);
            }

			function getFarmlist($uid){
                $q = 'SELECT * FROM ' . TB_PREFIX . 'farmlist WHERE owner = ' . $uid . ' ORDER BY name ASC';
				$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);

				if($dbarray['id']!=0) {
						return true;
					} else {
						return false;
					}

            }

			function getRaidList($id) {
        		$q = "SELECT * FROM " . TB_PREFIX . "raidlist WHERE id = ".$id."";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getAllAuction() {
        		$q = "SELECT * FROM " . TB_PREFIX . "auction WHERE finish = 0";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getVilFarmlist($wref){
                $q = 'SELECT * FROM ' . TB_PREFIX . 'farmlist WHERE wref = ' . $wref . ' ORDER BY wref ASC';
				$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);

				if($dbarray['id']!=0) {
						return true;
					} else {
						return false;
					}

            }

			function delFarmList($id, $owner) {
        		$q = "DELETE FROM " . TB_PREFIX . "farmlist where id = $id and owner = $owner";
        		return mysqli_query($this->connection,$q);
        	}


			function delSlotFarm($id) {
        		$q = "DELETE FROM " . TB_PREFIX . "raidlist where id = $id";
        		return mysqli_query($this->connection,$q);
        	}


			function createFarmList($wref, $owner, $name) {
        		$q = "INSERT INTO " . TB_PREFIX . "farmlist (`wref`, `owner`, `name`) VALUES ('$wref', '$owner', '$name')";
        		return mysqli_query($this->connection,$q);
        	}

			function addSlotFarm($lid, $towref, $x, $y, $distance, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10) {
        		$q = "INSERT INTO " . TB_PREFIX . "raidlist (`lid`, `towref`, `x`, `y`, `distance`, `t1`, `t2`, `t3`, `t4`, `t5`, `t6`, `t7`, `t8`, `t9`, `t10`) VALUES ('$lid', '$towref', '$x', '$y', '$distance', '$t1', '$t2', '$t3', '$t4', '$t5', '$t6', '$t7', '$t8', '$t9', '$t10')";
        		return mysqli_query($this->connection,$q);
        	}

			function editSlotFarm($eid, $lid, $wref, $x, $y, $dist, $t1, $t2, $t3, $t4, $t5, $t6, $t7, $t8, $t9, $t10) {

				$q = "UPDATE " . TB_PREFIX . "raidlist set lid = '$lid', towref = '$wref', x = '$x', y = '$y', t1 = '$t1', t2 = '$t2', t3 = '$t3', t4 = '$t4', t5 = '$t5', t6 = '$t6', t7 = '$t7', t8 = '$t8', t9 = '$t9', t10 = '$t10' WHERE id = $eid";
        		return mysqli_query($this->connection,$q);

        	}

			function getBerichte($uid) {
        		$q = "SELECT id FROM " . TB_PREFIX . "ndata where uid = $uid";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['id'];
        	}

			function removeOases($wref) {
                $q = "UPDATE ".TB_PREFIX."odata SET conqured = 0, owner = 3, name = 'Oasis no conquistado' WHERE wref = $wref";
                return mysqli_query($this->connection,$q);
            }

			function getArrayMemberVillage($uid){
			$q = 'SELECT a.wref, a.name, b.x, b.y from '.TB_PREFIX.'vdata AS a left join '.TB_PREFIX.'wdata AS b ON b.id = a.wref where owner = '.$uid.' order by capital DESC,pop DESC';
			$result = mysqli_query($this->connection,$q);
			$array = $this->mysqli_fetch_all($result);
			return $array;
			}

			function getNoticeData($nid) {
				$nid = (int)$nid;
				if($nid <= 0) {
					return false;
				}
				$q = "SELECT * FROM " . TB_PREFIX . "ndata where id = $nid";
				$result = mysqli_query($this->connection,$q);
				$dbarray = $result ? mysqli_fetch_assoc($result) : false;
				return $dbarray ? $dbarray['data'] : false;
			}

			function setSilver($uid, $silver, $mode) {
				if(!$mode){
        			$q = "UPDATE " . TB_PREFIX . "users set silver = silver - $silver where id = $uid";
				}else{
					$q = "UPDATE " . TB_PREFIX . "users set silver = silver + $silver where id = $uid";
				}
        		return mysqli_query($this->connection,$q);
        	}

			function acquireAuctionLock($timeout = 3) {
				$timeout = max(0, min(10, (int) $timeout));
				$lockName = "travian_auction_" . sha1(SQL_DB . ":" . TB_PREFIX);
				$result = mysqli_query($this->connection, "SELECT GET_LOCK('$lockName', $timeout) AS acquired");
				$row = $result ? mysqli_fetch_assoc($result) : false;
				return $row && (int) $row['acquired'] === 1;
			}

			function releaseAuctionLock() {
				$lockName = "travian_auction_" . sha1(SQL_DB . ":" . TB_PREFIX);
				return mysqli_query($this->connection, "SELECT RELEASE_LOCK('$lockName')");
			}

			function setNewSilver($id, $newsilver) {
				$q = "UPDATE " . TB_PREFIX . "auction set newsilver = $newsilver where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

			function getAuctionSilver($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "auction where uid = $uid and finish = 0";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getAuctionData($id) {
        		$q = "SELECT * FROM " . TB_PREFIX . "auction where id = $id";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function delAuction($id, $owner) {
				$id = (int) $id;
				$owner = (int) $owner;
				if($id <= 0 || $owner <= 0 || !$this->acquireAuctionLock()) {
					return false;
				}

				try {
					$now = time();
					$q = "SELECT * FROM " . TB_PREFIX . "auction WHERE id = $id AND owner = $owner AND finish = 0 AND bids = 0 AND time > $now LIMIT 1";
					$result = mysqli_query($this->connection, $q);
					$aucData = $result ? mysqli_fetch_assoc($result) : false;
					if(!$aucData) {
						return false;
					}

					$q = "UPDATE " . TB_PREFIX . "auction SET finish = 2 WHERE id = $id AND owner = $owner AND finish = 0 AND bids = 0 AND time > $now";
					$claimed = mysqli_query($this->connection, $q);
					if(!$claimed || mysqli_affected_rows($this->connection) !== 1) {
						return false;
					}

					$btype = (int) $aucData['btype'];
					if($btype >= 7 && $btype != 12 && $btype != 13) {
						if($this->checkHeroItem($owner, $btype)) {
							$restored = $this->editHeroNum($this->getHeroItemID($owner, $btype), (int) $aucData['num'], 1);
						} else {
							$restored = $this->addHeroItem($owner, $btype, (int) $aucData['type'], (int) $aucData['num']);
						}
					} else {
						$restored = $this->addHeroItem($owner, $btype, (int) $aucData['type'], (int) $aucData['num']);
					}

					if(!$restored) {
						mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "auction SET finish = 0 WHERE id = $id AND finish = 2");
						return false;
					}

					return mysqli_query($this->connection, "DELETE FROM " . TB_PREFIX . "auction WHERE id = $id AND owner = $owner AND finish = 2");
				} finally {
					$this->releaseAuctionLock();
				}
			}

			function getAuctionUser($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "auction where owner = $uid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

				function addAuction($owner, $itemid, $btype, $type, $amount) {
					$owner = (int)$owner;
					$itemid = (int)$itemid;
					$amount = (int)$amount;
					if($owner<1 || $itemid<1 || $amount<1 || !$this->acquireAuctionLock()){
						return false;
					}

					try {
						$q = "SELECT * FROM ".TB_PREFIX."heroitems"
							." WHERE id = $itemid AND uid = $owner AND proc = 0 LIMIT 1";
						$result = mysqli_query($this->connection,$q);
						$itemData = $result ? mysqli_fetch_assoc($result) : false;
						if(!$itemData || $amount>(int)$itemData['num']){
							return false;
						}

						$btype = (int)$itemData['btype'];
						$type = (int)$itemData['type'];
						$itemAmount = (int)$itemData['num'];
						$stackable = in_array($btype,array(7,8,9,10,11,13,14),true);
						if(!$stackable && $amount!==$itemAmount){
							return false;
						}

						$removedWholeItem = ($amount===$itemAmount);
						if($removedWholeItem){
							$q = "DELETE FROM ".TB_PREFIX."heroitems"
								." WHERE id = $itemid AND uid = $owner AND proc = 0 AND num = $itemAmount";
						}else{
							$q = "UPDATE ".TB_PREFIX."heroitems SET num = num - $amount"
								." WHERE id = $itemid AND uid = $owner AND proc = 0 AND num = $itemAmount";
						}
						$claimed = mysqli_query($this->connection,$q);
						if(!$claimed || mysqli_affected_rows($this->connection)!==1){
							return false;
						}

						$time = time()+AUCTIONTIME;
						$silver = $stackable ? $amount : 100;
						$q = "INSERT INTO ".TB_PREFIX."auction"
							." (`owner`, `itemid`, `btype`, `type`, `num`, `uid`, `bids`, `silver`, `newsilver`, `time`, `finish`)"
							." VALUES ($owner, $itemid, $btype, $type, $amount, 0, 0, $silver, $silver, $time, 0)";
						$created = mysqli_query($this->connection,$q);
						if($created){
							return true;
						}

						if($removedWholeItem){
							$this->addHeroItem($owner,$btype,$type,$amount);
						}else{
							mysqli_query(
								$this->connection,
								"UPDATE ".TB_PREFIX."heroitems SET num = num + $amount"
									." WHERE id = $itemid AND uid = $owner AND proc = 0"
							);
						}
						return false;
					} finally {
						$this->releaseAuctionLock();
					}
				}

			function placeAuctionBid($id, $bidder, $maxBid) {
				$id = (int) $id;
				$bidder = (int) $bidder;
				$maxBid = (int) $maxBid;
				if($id <= 0 || $bidder <= 0 || $maxBid <= 0 || $maxBid > 2147483647) {
					return array('status' => 'invalid');
				}
				if(!$this->acquireAuctionLock()) {
					return array('status' => 'busy');
				}

				try {
					$result = mysqli_query($this->connection, "SELECT * FROM " . TB_PREFIX . "auction WHERE id = $id LIMIT 1");
					$auction = $result ? mysqli_fetch_assoc($result) : false;
					if(!$auction) {
						return array('status' => 'missing');
					}
					if((int) $auction['finish'] !== 0 || (int) $auction['time'] <= time()) {
						return array('status' => 'closed');
					}
					if((int) $auction['owner'] === $bidder) {
						return array('status' => 'own');
					}

					$currentPrice = (int) $auction['silver'];
					$currentWinner = (int) $auction['uid'];
					$currentMaximum = max((int) $auction['newsilver'], $currentPrice);
					if($maxBid < $currentPrice || ($currentWinner !== $bidder && $maxBid === $currentPrice)) {
						return array('status' => 'too_low', 'minimum' => $currentPrice + 1);
					}

					$userResult = mysqli_query($this->connection, "SELECT silver FROM " . TB_PREFIX . "users WHERE id = $bidder LIMIT 1");
					$user = $userResult ? mysqli_fetch_assoc($userResult) : false;
					if(!$user) {
						return array('status' => 'invalid');
					}

					if($currentWinner === $bidder) {
						$available = (int) $user['silver'] + $currentMaximum;
						if($maxBid > $available) {
							return array('status' => 'insufficient');
						}
						$difference = $maxBid - $currentMaximum;
						if($difference > 0) {
							$moneyChanged = mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "users SET silver = silver - $difference WHERE id = $bidder AND silver >= $difference");
						} elseif($difference < 0) {
							$refund = abs($difference);
							$moneyChanged = mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "users SET silver = silver + $refund WHERE id = $bidder");
						} else {
							$moneyChanged = true;
						}
						if(!$moneyChanged || ($difference > 0 && mysqli_affected_rows($this->connection) !== 1)) {
							return array('status' => 'insufficient');
						}

						$updated = mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "auction SET newsilver = $maxBid WHERE id = $id AND finish = 0");
						if(!$updated) {
							if($difference > 0) {
								mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "users SET silver = silver + $difference WHERE id = $bidder");
							} elseif($difference < 0) {
								$refund = abs($difference);
								mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "users SET silver = silver - $refund WHERE id = $bidder");
							}
							return array('status' => 'error');
						}
						$this->recordAuctionBid($id, $bidder, $maxBid, $currentPrice, $currentPrice);
						return array('status' => 'winning', 'price' => $currentPrice);
					}

					if($maxBid > (int) $user['silver']) {
						return array('status' => 'insufficient');
					}

					if($currentWinner === 0 || $maxBid > $currentMaximum) {
						$charged = mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "users SET silver = silver - $maxBid WHERE id = $bidder AND silver >= $maxBid");
						if(!$charged || mysqli_affected_rows($this->connection) !== 1) {
							return array('status' => 'insufficient');
						}
						if($currentWinner > 0) {
							mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "users SET silver = silver + $currentMaximum WHERE id = $currentWinner");
						}

						$newPrice = min($maxBid, $currentMaximum + 1);
						$updated = mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "auction SET uid = $bidder, silver = $newPrice, newsilver = $maxBid, bids = bids + 1 WHERE id = $id AND finish = 0");
						if(!$updated) {
							mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "users SET silver = silver + $maxBid WHERE id = $bidder");
							if($currentWinner > 0) {
								mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "users SET silver = silver - $currentMaximum WHERE id = $currentWinner");
							}
							return array('status' => 'error');
						}
						$this->recordAuctionBid($id, $bidder, $maxBid, $currentPrice, $newPrice);
						return array('status' => 'winning', 'price' => $newPrice);
					}

					$newPrice = min($currentMaximum, max($currentPrice, $maxBid + 1));
					$updated = mysqli_query($this->connection, "UPDATE " . TB_PREFIX . "auction SET silver = $newPrice, bids = bids + 1 WHERE id = $id AND finish = 0");
					if(!$updated) {
						return array('status' => 'error');
					}
					$this->recordAuctionBid($id, $bidder, $maxBid, $currentPrice, $newPrice);
					return array('status' => 'outbid', 'price' => $newPrice);
				} finally {
					$this->releaseAuctionLock();
				}
			}

			private function recordAuctionBid($auctionId, $uid, $maxBid, $priceBefore, $priceAfter) {
				$auctionId = (int) $auctionId;
				$uid = (int) $uid;
				$maxBid = (int) $maxBid;
				$priceBefore = (int) $priceBefore;
				$priceAfter = (int) $priceAfter;
				$time = time();
				$q = "INSERT INTO " . TB_PREFIX . "auction_bids (auction_id, uid, max_bid, price_before, price_after, time) VALUES ($auctionId, $uid, $maxBid, $priceBefore, $priceAfter, $time)";
				return mysqli_query($this->connection, $q);
			}

			function addBid($id, $uid, $newsilver) {
        		$q = "UPDATE " . TB_PREFIX . "auction set uid = $uid, silver = newsilver + 1, newsilver = $newsilver, bids = bids + 1 where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

			function removeBidNotice($id) {
        		$q = "DELETE FROM " . TB_PREFIX . "auction where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

			function addHeroItem($uid, $btype, $type, $num) {
        		$q = "INSERT INTO " . TB_PREFIX . "heroitems (`uid`, `btype`, `type`, `num`, `proc`) VALUES ('$uid', '$btype', '$type', '$num', 0)";
        		return mysqli_query($this->connection,$q);
        	}

			function checkHeroItem($uid, $btype){
                $q = "SELECT * FROM ".TB_PREFIX."heroitems WHERE uid = '$uid' and btype = '$btype' and proc = 0";
				$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
				if($dbarray['btype']==$btype) {
					return $dbarray['id'];
				} else {
					return false;
				}
            }

			function checkAttack($wref, $toWref){
                $q = "SELECT * FROM ".TB_PREFIX."movement WHERE `from` = '$wref' AND `to` = '$toWref' AND `proc` = '0' AND `sort_type` = '3'";
				$result = mysqli_query($this->connection,$q);
				if(mysqli_num_rows($result)) {
					return mysqli_fetch_array($result);
				} else {
					return false;
				}
            }

			function getHeroItemID($uid, $btype) {
        		$q = "SELECT * FROM " . TB_PREFIX . "heroitems where uid = ".$uid." AND btype = ".$btype."";
				$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['id'];
        	}

			function getHeroItemID2($uid, $btype, $type) {
        		$q = "SELECT * FROM " . TB_PREFIX . "heroitems where uid = ".$uid." AND btype = ".$btype." AND type = ".$type."";
				$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['id'];
        	}

			function getEquippedHeroItem($uid, $btype) {
        		$q = "SELECT * FROM " . TB_PREFIX . "heroitems where uid = ".$uid." AND btype = ".$btype." AND proc = 1";
				$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getItemData($id) {
				$id = (int)$id;
        		$q = "SELECT * FROM " . TB_PREFIX . "heroitems WHERE id = $id";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function editHeroNum($id, $num, $mode) {
				$id = (int)$id;
				$num = max(0,(int)$num);
				if($mode==0){
        			$q = "UPDATE " . TB_PREFIX . "heroitems set num = num - $num where id = $id and proc = 0";
				}elseif($mode==1){
					$q = "UPDATE " . TB_PREFIX . "heroitems set num = num + $num where id = $id and proc = 0";
				}else{
					$q = "UPDATE " . TB_PREFIX . "heroitems set num = $num where id = $id and proc = 0";
				}
        		return mysqli_query($this->connection,$q);
        	}

			function editHeroNum2($id, $num, $mode) {
				if($mode==0){
        			$q = "UPDATE " . TB_PREFIX . "heroitems set num = num - $num where id = $id";
				}elseif($mode==1){
					$q = "UPDATE " . TB_PREFIX . "heroitems set num = num + $num where id = $id";
				}else{
					$q = "UPDATE " . TB_PREFIX . "heroitems set num = $num where id = $id";
				}
        		return mysqli_query($this->connection,$q);
        	}

			function editHeroType($id, $type, $mode) {
				if($mode==0){
        			$q = "UPDATE " . TB_PREFIX . "heroitems set type = type - $type where id = $id";
				}elseif($mode==1){
					$q = "UPDATE " . TB_PREFIX . "heroitems set type = type + $type where id = $id";
				}else{
					$q = "UPDATE " . TB_PREFIX . "heroitems set type = $type where id = $id";
				}
        		return mysqli_query($this->connection,$q);
        	}

			function editProcItem($id, $mode) {
				$id = (int)$id;
				if($mode==0){
        			$q = "UPDATE " . TB_PREFIX . "heroitems set proc = 0 where id = $id";
				}else{
					$q = "UPDATE " . TB_PREFIX . "heroitems set proc = 1 where id = $id";
				}
        		return mysqli_query($this->connection,$q);
        	}

			function editBid($id, $silver) {
        		$q = "UPDATE " . TB_PREFIX . "auction set silver = $silver where id = $id";
        		return mysqli_query($this->connection,$q);
        	}

			function checkBid($id, $newsilver){
                $q = "SELECT * FROM " . TB_PREFIX . "auction WHERE id = '$id'";
				$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);

				if($dbarray['newsilver']>=$newsilver) {
						return false;
					} else {
						return true;
					}
            }

			function getBidData($id) {
        		$q = "SELECT * FROM " . TB_PREFIX . "auction WHERE id = $id";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function setHeroInventory($uid, $field, $value) {
        		$q = "UPDATE " . TB_PREFIX . "heroinventory set $field = '$value' where uid = $uid";
        		return mysqli_query($this->connection,$q);
        	}

			function getHeroInventory($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "heroinventory WHERE uid = $uid";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getHeroData($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "hero WHERE uid = $uid";
        		$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_array($result);
        	}

			function getHeroData2($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "hero WHERE dead = 0 and uid = $uid";
        		$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_array($result);
        	}

			function getHeroData3($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "hero WHERE dead = 0 and hide = 0 and uid = $uid";
        		$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_array($result);
        	}

			function getFLData($id) {
        		$q = "SELECT * FROM " . TB_PREFIX . "farmlist where id = $id";
        		$result = mysqli_query($this->connection,$q);
				return mysqli_fetch_array($result);
        	}

			function getHeroField($uid, $field) {
        		$q = "SELECT ".$field." FROM " . TB_PREFIX . "hero WHERE uid = $uid";
        		$result = mysqli_query($this->connection,$q);
				$dbarray = mysqli_fetch_array($result);
        		return $dbarray[$field];
        	}

			function getVFH($uid) {
        		$q = "SELECT wref FROM " . TB_PREFIX . "vdata WHERE owner = $uid and capital = 1";
        		$result = mysqli_query($this->connection,$q);
        		$dbarray = mysqli_fetch_array($result);
        		return $dbarray['wref'];
        	}

			function addAdventure($wref, $uid){
				$time = time()+(3600*120);
				$ddd = rand(0,3);
				if($ddd == 1){ $dif = 1; }else{ $dif = 0; }
				$sql = mysqli_query($this->connection,"SELECT * FROM ".TB_PREFIX."wdata ORDER BY id DESC LIMIT 1");
				$lastw = 641601;
				if(($wref-10000)<=10){
					$w1 = rand(10,($wref+10000));
				}
				elseif(($wref+10000) >= $lastw){
					$w1 = rand(($wref-10000),($lastw-1000));
				}
				else{
					$w1 = rand(($wref-10000),($wref+10000));
				}

				$q = "INSERT into " . TB_PREFIX . "adventure (`wref`, `uid`, `dif`, `time`, `end`) values ('$w1', '$uid', '$dif', '$time', 0)";
        		return mysqli_query($this->connection,$q) or die(mysqli_error());
			}

			function addHero($uid){
				$time = time();
				$hash = md5($time);
				$q = "INSERT into " . TB_PREFIX . "hero (`uid`, `wref`, `level`, `speed`, `points`, `experience`, `dead`, `health`, `power`, `offBonus`, `defBonus`, `product`, `r0`, `autoregen`, `lastupdate`, `lastadv`, `hash`) values
				('$uid', 0, 0, '7', 0, '2', 0, '100', '0', 0, 0, '4', '1', '10', '$time', '$time', '$hash')";
        		return mysqli_query($this->connection,$q) or die(mysqli_error());
			}

			// Add new password => mode:0
			// Add new email => mode: 1
			function addNewProc($uid, $npw, $nemail, $act, $mode) {
        		$time = time();
        		if(!$mode){
					$q = "INSERT into " . TB_PREFIX . "newproc (uid, npw, act, time, proc) values ('$uid', '$npw', '$act', '$time', 0)";
				}else{
					$q = "INSERT into " . TB_PREFIX . "newproc (uid, nemail, act, time, proc) values ('$uid', '$nemail', '$act', '$time', 0)";
				}

        		return mysqli_query($this->connection,$q) or die(mysqli_error());
        	}

			function checkProcExist($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "newproc where uid = '$uid' and proc = 0";
        		$result = mysqli_query($this->connection,$q);
        		if(mysqli_num_rows($result)) {
        			return false;
        		} else {
        			return true;
        		}
        	}

			function removeProc($uid) {
        		$q = "DELETE FROM " . TB_PREFIX . "newproc where uid = $uid";
        		return mysqli_query($this->connection,$q);
        	}

			function checkBan($uid){
				$uid = (int) $uid;
				$q = "SELECT 1 FROM " . TB_PREFIX . "banlist WHERE uid = $uid AND active = 1 LIMIT 1";
				$result = mysqli_query($this->connection,$q);
				if(mysqli_num_rows($result)) {
					return true;
				}else{
					return false;
				}
			}

			function getNewProc($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "newproc WHERE uid = $uid";
        		$result = mysqli_query($this->connection,$q);
				if(mysqli_num_rows($result)) {
        			return mysqli_fetch_array($result);
        		} else {
        			return false;
        		}
        	}

			function getAdventure($uid, $wref) {
				$uid = (int) $uid;
				$wref = (int) $wref;
				$q = "SELECT * FROM " . TB_PREFIX . "adventure WHERE uid = $uid AND wref = $wref ORDER BY id DESC LIMIT 1";
        		$result = mysqli_query($this->connection,$q);
				if(mysqli_num_rows($result)) {
        			return mysqli_fetch_array($result);
        		} else {
        			return false;
        		}
        	}

			function getAdventureCount($uid) {
				$uid = (int) $uid;
				$q = "SELECT COUNT(1) AS count FROM " . TB_PREFIX . "adventure WHERE uid = $uid AND end = 0";
				$result = mysqli_query($this->connection, $q);
				$row = mysqli_fetch_assoc($result);
				return (int) $row['count'];
			}

			function editTableField($table, $field, $value, $refField, $ref) {
        		$q = "UPDATE " . TB_PREFIX . "".$table." set $field = '$value' where ".$refField." = '$ref'";
        		return mysqli_query($this->connection,$q);
        	}

			function HeroItemsNum($uid) {
        		$q = "SELECT * FROM " . TB_PREFIX . "heroitems where uid = '$uid'";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_num_rows($result);
        	}

			function addHeroinventory($uid){
				$q = "INSERT into " . TB_PREFIX . "heroinventory (`uid`) values ('$uid')";
        		return mysqli_query($this->connection,$q) or die(mysqli_error());
			}

			function config() {
        		$q = "SELECT * FROM " . TB_PREFIX . "config";
        		$result = mysqli_query($this->connection,$q);
        		return mysqli_fetch_array($result);
        	}

			function getAllianceDipProfile($aid, $type){
				$q = "SELECT * FROM ".TB_PREFIX."diplomacy WHERE alli1 = '$aid' AND type = '$type' AND accepted = '1'";
				$result = mysqli_query($this->connection,$q);
				if(mysqli_num_rows($result) == 0){
					$q2 = "SELECT * FROM ".TB_PREFIX."diplomacy WHERE alli2 = '$aid' AND type = '$type' AND accepted = '1'";
					$result2 = mysqli_query($this->connection,$q2);
					while($row = mysqli_fetch_array($result2)){
						$alliance = $this->getAlliance($row['alli1']);
						$text = "";
						$text .= "<a href=allianz.php?aid=".$alliance['id'].">".$alliance['tag']."</a><br> ";
					}
				}else{
					while($row = mysqli_fetch_array($result)){
						$alliance = $this->getAlliance($row['alli2']);
						$text = "";
						$text .= "<a href=allianz.php?aid=".$alliance['id'].">".$alliance['tag']."</a><br> ";
					}
				}
				if(strlen($text) == 0){
					$text = "-<br>";
				}
				return $text;
			}

			public function canClaimArtifact ($vref,$type) {
				$DefenderFields = $this->getResourceLevel($vref);
                for($i=19;$i<=38;$i++) {
                    if($AttackerFields['f'.$i.'t'] == 27) {
                        $defcanclaim = FALSE;
                        $defTresuaryLevel = $AttackerFields['f'.$i];
                    } else {
                        $defcanclaim = TRUE;
                    }
                }
                $AttackerFields = $this->getResourceLevel($vref);
                for($i=19;$i<=38;$i++) {
                	if($AttackerFields['f'.$i.'t'] == 27) {
                		$attTresuaryLevel = $AttackerFields['f'.$i];
                		if ($attTresuaryLevel >= 10){
                			$villageartifact = TRUE;
                		}else{
                			$villageartifact = FALSE;
                		}
                		if ($attTresuaryLevel == 20){
                			$accountartifact = TRUE;
                		}else{
                			$accountartifact = FALSE;
                		}
                	}
                }
                if ($type == 1) {
					if ($defcanclaim == TRUE && $villageartifact == TRUE){
						return TRUE;
					}
                }else if($type == 2) {
                	if ($defcanclaim == TRUE && $accountartifact == TRUE){
                		return TRUE;
                	}
                }else if($type == 3) {
                	if ($defcanclaim == TRUE && $accountartifact == TRUE){
                		return TRUE;
                	}
                }else { return FALSE; }
            }

			function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){
				if(!isset($pct)){
					return false;
				}
				$pct /= 100;
				// Get image width and height
				$w = imagesx( $src_im );
				$h = imagesy( $src_im );
				// Turn alpha blending off
				imagealphablending( $src_im, false );
				// Find the most opaque pixel in the image (the one with the smallest alpha value)
				$minalpha = 127;
				for( $x = 0; $x < $w; $x++ )
				for( $y = 0; $y < $h; $y++ ){
					$alpha = ( imagecolorat( $src_im, $x, $y ) >> 24 ) & 0xFF;
					if( $alpha < $minalpha ){
						$minalpha = $alpha;
					}
				}
				//loop through image pixels and modify alpha for each
				for( $x = 0; $x < $w; $x++ ){
					for( $y = 0; $y < $h; $y++ ){
						//get current alpha value (represents the TANSPARENCY!)
						$colorxy = imagecolorat( $src_im, $x, $y );
						$alpha = ( $colorxy >> 24 ) & 0xFF;
						//calculate new alpha
						if( $minalpha !== 127 ){
							$alpha = 127 + 127 * $pct * ( $alpha - 127 ) / ( 127 - $minalpha );
						} else {
							$alpha += 127 * $pct;
						}
						//get the color index with new alpha
						$alphacolorxy = imagecolorallocatealpha( $src_im, ( $colorxy >> 16 ) & 0xFF, ( $colorxy >> 8 ) & 0xFF, $colorxy & 0xFF, $alpha );
						//set pixel with the new color + opacity
						if( !imagesetpixel( $src_im, $x, $y, $alphacolorxy ) ){
							return false;
						}
					}
				}
				// The image copy
				imagecopy($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h);
			}

            function getCropProdstarv($wref) {
			global $bid4,$bid8,$bid9,$sesion,$technology;

				$basecrop = $grainmill = $bakery = 0;
                $owner = $this->getVillageField($wref, 'owner');
                $bonus = $this->getUserField($owner, b4, 0);

                $buildarray = $this->getResourceLevel($wref);
				$cropholder = array();
				for($i=1;$i<=38;$i++) {
                    if($buildarray['f'.$i.'t'] == 4) {
                        array_push($cropholder,'f'.$i);
                    }
                    if($buildarray['f'.$i.'t'] == 8) {
                        $grainmill = $buildarray['f'.$i];
                    }
                    if($buildarray['f'.$i.'t'] == 9) {
                        $bakery = $buildarray['f'.$i];
                    }
				}
                $q = "SELECT type FROM `" . TB_PREFIX . "odata` WHERE conqured = $wref";
                $oasis = $this->query_return($q);
				foreach($oasis as $oa){
                    switch($oa['type']) {
                        case 1:
                        case 2:
                        $wood += 1;
                        break;
                        case 3:
                        $wood += 1;
                        $cropo += 1;
                        break;
                        case 4:
                        case 5:
                        $clay += 1;
                        break;
                        case 6:
                        $clay += 1;
                        $cropo += 1;
                        break;
                        case 7:
                        case 8:
                        $iron += 1;
                        break;
                        case 9:
                        $iron += 1;
                        $cropo += 1;
                        break;
                        case 10:
                        case 11:
                        $cropo += 1;
                        break;
                        case 12:
                        $cropo += 2;
                        break;
                    }
				}
				for($i=0;$i<=count($cropholder)-1;$i++) { $basecrop+= $bid4[$buildarray[$cropholder[$i]]]['prod']; }
				$crop = $basecrop + $basecrop * 0.25 * $cropo;
				if($grainmill >= 1 || $bakery >= 1) {
                    $crop += $basecrop /100 * ($bid8[$grainmill]['attri'] + $bid9[$bakery]['attri']);
				}
				if($bonus > time()) {
                    $crop *= 1.25;
				}
				$crop *= SPEED;
				return $crop;
            }

		function getFieldDistance($wid) {
		$q = "SELECT * FROM " . TB_PREFIX . "vdata where owner > 4 and wref != $wid";
		$array = $this->query_return($q);
		$coor = $this->getCoor($wid);
		$x1 = intval($coor['x']);
		$y1 = intval($coor['y']);
		$prevdist = 0;
		$q2 = "SELECT * FROM " . TB_PREFIX . "vdata where owner = 4";
		$array2 = mysqli_fetch_array(mysqli_query($this->connection,$q2));
		$vill = $array2['wref'];
		if(mysqli_num_rows(mysqli_query($this->connection,$q)) > 0){
		foreach($array as $village){
		$coor2 = $this->getCoor($village['wref']);
				$max = 2 * WORLD_MAX + 1;
				$x2 = intval($coor2['x']);
				$y2 = intval($coor2['y']);
				$distanceX = min(abs($x2 - $x1), abs($max - abs($x2 - $x1)));
				$distanceY = min(abs($y2 - $y1), abs($max - abs($y2 - $y1)));
				$dist = sqrt(pow($distanceX, 2) + pow($distanceY, 2));
		if($dist < $prevdist or $prevdist == 0){
				$prevdist = $dist;
				$vill = $village['wref'];
		}
		}
		}
				return $vill;
		}

	//general statistics

	function addGeneralAttack($casualties) {
		$time = time();
		$q = "INSERT INTO " . TB_PREFIX . "general values (0,'$casualties','$time',1)";
		return mysqli_query($this->connection,$q) or die(mysqli_error());
	}

	function getAttackByDate($time) {
		$q = "SELECT * FROM " . TB_PREFIX . "general where shown = 1";
		$result = $this->query_return($q);
		$attack = 0;
		foreach($result as $general){
		if(date("j. M",$time) == date("j. M",$general['time'])){
		$attack += 1;
		}
		}
		return $attack;
	}

	function getAttackCasualties($time) {
		$q = "SELECT * FROM " . TB_PREFIX . "general where shown = 1";
		$result = $this->query_return($q);
		$casualties = 0;
		foreach($result as $general){
		if(date("j. M",$time) == date("j. M",$general['time'])){
		$casualties += $general['casualties'];
		}
		}
		return $casualties;
	}

	//end general statistics

	function addFriend($uid, $column, $friend) {
		$q = "UPDATE " . TB_PREFIX . "users SET $column = $friend WHERE id = $uid";
		return mysqli_query($this->connection,$q);
	}

	function deleteFriend($uid, $column) {
		$q = "UPDATE " . TB_PREFIX . "users SET $column = 0 WHERE id = $uid";
		return mysqli_query($this->connection,$q);
	}

	function checkFriends($uid) {
		$user = $this->getUserArray($uid, 1);
		for($i=0;$i<=19;$i++) {
		if($user['friend'.$i] == 0 && $user['friend'.$i.'wait'] == 0){
		for($j=$i+1;$j<=19;$j++) {
		$k = $j-1;
		if($user['friend'.$j] != 0){
		$friend = $this->getUserField($uid, "friend".$j, 0);
		$this->addFriend($uid,"friend".$k,$friend);
		$this->deleteFriend($uid,"friend".$j);
		}
		if($user['friend'.$j.'wait'] == 0){
		$friendwait = $this->getUserField($uid, "friend".$j."wait", 0);
		$this->addFriend($sessionuid,"friend".$k."wait",$friendwait);
		$this->deleteFriend($uid,"friend".$j."wait");
		}
		}
		}
		}
	}

	function allocateTrapsProportionally($troops,$limit) {
		$normalized = array();
		$total = 0;
		for($i = 1; $i <= 11; $i++) {
			$normalized[$i] = max(0,(int)(isset($troops[$i]) ? $troops[$i] : 0));
			$total += $normalized[$i];
		}
		$limit = min(max(0,(int)$limit),$total);
		$allocated = array_fill(1,11,0);
		if($limit === 0 || $total === 0) {
			return $allocated;
		}

		$remainders = array();
		$assigned = 0;
		for($i = 1; $i <= 11; $i++) {
			$product = $normalized[$i] * $limit;
			$allocated[$i] = (int)floor($product / $total);
			$assigned += $allocated[$i];
			$remainders[] = array(
				'position' => $i,
				'remainder' => $product % $total,
				'tie' => mt_rand()
			);
		}
		usort($remainders,function($first,$second) {
			if($first['remainder'] === $second['remainder']) {
				return $first['tie'] <=> $second['tie'];
			}
			return $second['remainder'] <=> $first['remainder'];
		});
		$remaining = $limit - $assigned;
		foreach($remainders as $candidate) {
			if($remaining <= 0) {
				break;
			}
			$position = $candidate['position'];
			if($allocated[$position] < $normalized[$position]) {
				$allocated[$position]++;
				$remaining--;
			}
		}
		return $allocated;
	}

	function capturePrisonersAtomic($wid,$from,$troops,$capacity) {
		$wid = (int)$wid;
		$from = (int)$from;
		$capacity = max(0,(int)$capacity);
		$empty = array_fill(1,11,0);
		if($wid <= 0 || $from <= 0 || $capacity <= 0) {
			return $empty;
		}

		$locked = mysqli_query(
			$this->connection,
			"LOCK TABLES ".TB_PREFIX."units WRITE, ".TB_PREFIX."prisoners WRITE"
		);
		if(!$locked) {
			return $empty;
		}
		try {
			$result = mysqli_query(
				$this->connection,
				"SELECT u99,u99o FROM ".TB_PREFIX."units WHERE vref = $wid LIMIT 1"
			);
			$traps = $result ? mysqli_fetch_assoc($result) : false;
			if(!$traps) {
				return $empty;
			}
			$available = min(
				max(0,(int)$traps['u99'] - (int)$traps['u99o']),
				max(0,$capacity - (int)$traps['u99o'])
			);
			$allocated = $this->allocateTrapsProportionally($troops,$available);
			$total = array_sum($allocated);
			if($total <= 0) {
				return $empty;
			}

			$occupied = mysqli_query(
				$this->connection,
				"UPDATE ".TB_PREFIX."units SET u99o = u99o + $total ".
				"WHERE vref = $wid AND u99o + $total <= u99 AND u99o + $total <= $capacity"
			);
			if(!$occupied || mysqli_affected_rows($this->connection) !== 1) {
				return $empty;
			}

			$result = mysqli_query(
				$this->connection,
				"SELECT id FROM ".TB_PREFIX."prisoners WHERE wref = $wid AND `from` = $from LIMIT 1"
			);
			$existing = $result ? mysqli_fetch_assoc($result) : false;
			$parts = array();
			for($i = 1; $i <= 11; $i++) {
				$parts[] = "t$i = t$i + ".$allocated[$i];
			}
			if($existing) {
				$persisted = mysqli_query(
					$this->connection,
					"UPDATE ".TB_PREFIX."prisoners SET ".implode(',',$parts)." WHERE id = ".(int)$existing['id']
				);
			} else {
				$values = array($wid,$from);
				for($i = 1; $i <= 11; $i++) {
					$values[] = $allocated[$i];
				}
				$persisted = mysqli_query(
					$this->connection,
					"INSERT INTO ".TB_PREFIX."prisoners (wref,`from`,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11) ".
					"VALUES (".implode(',',$values).")"
				);
			}
			if(!$persisted) {
				mysqli_query(
					$this->connection,
					"UPDATE ".TB_PREFIX."units SET u99o = GREATEST(0,u99o - $total) WHERE vref = $wid"
				);
				return $empty;
			}
			return $allocated;
		} finally {
			mysqli_query($this->connection,"UNLOCK TABLES");
		}
	}

	function returnPrisonersAtomic($id,$wref,$from,$troops,$start,$endtime,$destroyTraps=false,$expectedCaptured=array()) {
		$id = (int)$id;
		$wref = (int)$wref;
		$from = (int)$from;
		$start = max(0,(int)$start);
		$endtime = max($start + 1,(int)$endtime);
		if($id <= 0 || $wref <= 0 || $from <= 0) {
			return false;
		}
		$returning = array();
		for($i = 1; $i <= 11; $i++) {
			$returning[$i] = max(0,(int)(isset($troops[$i]) ? $troops[$i] : 0));
		}
		if(array_sum($returning) <= 0) {
			return false;
		}

		$locked = mysqli_query(
			$this->connection,
			"LOCK TABLES ".TB_PREFIX."prisoners WRITE, ".TB_PREFIX."units WRITE, ".
			TB_PREFIX."attacks WRITE, ".TB_PREFIX."movement WRITE"
		);
		if(!$locked) {
			return false;
		}
		try {
			$result = mysqli_query(
				$this->connection,
				"SELECT * FROM ".TB_PREFIX."prisoners WHERE id = $id AND wref = $wref AND `from` = $from LIMIT 1"
			);
			$prisoner = $result ? mysqli_fetch_assoc($result) : false;
			if(!$prisoner) {
				return false;
			}
			$captured = 0;
			for($i = 1; $i <= 11; $i++) {
				$captured += max(0,(int)$prisoner['t'.$i]);
				$expected = isset($expectedCaptured[$i])
					? max(0,(int)$expectedCaptured[$i])
					: ($destroyTraps ? null : $returning[$i]);
				if($expected !== null && $expected !== max(0,(int)$prisoner['t'.$i])) {
					return false;
				}
			}
			if($captured <= 0) {
				return false;
			}

			$attackValues = array($from);
			for($i = 1; $i <= 11; $i++) {
				$attackValues[] = $returning[$i];
			}
			$attackValues = array_merge($attackValues,array(3,0,0,0));
			$attackCreated = mysqli_query(
				$this->connection,
				"INSERT INTO ".TB_PREFIX."attacks (vref,t1,t2,t3,t4,t5,t6,t7,t8,t9,t10,t11,attack_type,ctar1,ctar2,spy) ".
				"VALUES (".implode(',',$attackValues).")"
			);
			$attackId = $attackCreated ? (int)mysqli_insert_id($this->connection) : 0;
			if($attackId <= 0) {
				return false;
			}
			$movementCreated = mysqli_query(
				$this->connection,
				"INSERT INTO ".TB_PREFIX."movement (sort_type,`from`,`to`,ref,ref2,data,endtime,proc,send,wood,clay,iron,crop) ".
				"VALUES (4,$wref,$from,$attackId,0,'$start',$endtime,0,1,0,0,0,0)"
			);
			$movementId = $movementCreated ? (int)mysqli_insert_id($this->connection) : 0;
			if(!$movementCreated || $movementId <= 0) {
				mysqli_query($this->connection,"DELETE FROM ".TB_PREFIX."attacks WHERE id = $attackId");
				return false;
			}

			$trapUpdate = $destroyTraps
				? "u99 = GREATEST(0,u99 - $captured), u99o = GREATEST(0,u99o - $captured)"
				: "u99o = GREATEST(0,u99o - $captured)";
			$trapsChanged = mysqli_query(
				$this->connection,
				"UPDATE ".TB_PREFIX."units SET $trapUpdate WHERE vref = $wref"
			);
			if(!$trapsChanged || mysqli_affected_rows($this->connection) !== 1) {
				mysqli_query($this->connection,"DELETE FROM ".TB_PREFIX."movement WHERE moveid = $movementId");
				mysqli_query($this->connection,"DELETE FROM ".TB_PREFIX."attacks WHERE id = $attackId");
				return false;
			}

			$removed = mysqli_query(
				$this->connection,
				"DELETE FROM ".TB_PREFIX."prisoners WHERE id = $id AND wref = $wref AND `from` = $from"
			);
			if(!$removed || mysqli_affected_rows($this->connection) !== 1) {
				$restore = $destroyTraps
					? "u99 = u99 + $captured, u99o = u99o + $captured"
					: "u99o = u99o + $captured";
				mysqli_query($this->connection,"UPDATE ".TB_PREFIX."units SET $restore WHERE vref = $wref");
				mysqli_query($this->connection,"DELETE FROM ".TB_PREFIX."movement WHERE moveid = $movementId");
				mysqli_query($this->connection,"DELETE FROM ".TB_PREFIX."attacks WHERE id = $attackId");
				return false;
			}
			return true;
		} finally {
			mysqli_query($this->connection,"UNLOCK TABLES");
		}
	}

	function mergePrisonersIntoAttackAtomic($id,$wref,$from,$attackId,$survivors,$expectedCaptured=array()) {
		$id = (int)$id;
		$wref = (int)$wref;
		$from = (int)$from;
		$attackId = (int)$attackId;
		if($id <= 0 || $wref <= 0 || $from <= 0 || $attackId <= 0) {
			return false;
		}
		$units = array();
		for($i = 1; $i <= 11; $i++) {
			$units[$i] = max(0,(int)(isset($survivors[$i]) ? $survivors[$i] : 0));
		}

		$locked = mysqli_query(
			$this->connection,
			"LOCK TABLES ".TB_PREFIX."prisoners WRITE, ".TB_PREFIX."units WRITE, ".TB_PREFIX."attacks WRITE"
		);
		if(!$locked) {
			return false;
		}
		try {
			$result = mysqli_query(
				$this->connection,
				"SELECT * FROM ".TB_PREFIX."prisoners WHERE id = $id AND wref = $wref AND `from` = $from LIMIT 1"
			);
			$prisoner = $result ? mysqli_fetch_assoc($result) : false;
			if(!$prisoner) {
				return false;
			}
			$captured = 0;
			$additions = array();
			$subtractions = array();
			for($i = 1; $i <= 11; $i++) {
				$captured += max(0,(int)$prisoner['t'.$i]);
				if(isset($expectedCaptured[$i])
					&& max(0,(int)$expectedCaptured[$i]) !== max(0,(int)$prisoner['t'.$i])) {
					return false;
				}
				$additions[] = "t$i = t$i + ".$units[$i];
				$subtractions[] = "t$i = GREATEST(0,t$i - ".$units[$i].")";
			}
			$attackChanged = mysqli_query(
				$this->connection,
				"UPDATE ".TB_PREFIX."attacks SET ".implode(',',$additions)." WHERE id = $attackId"
			);
			if(!$attackChanged || mysqli_affected_rows($this->connection) !== 1) {
				return false;
			}
			$trapsChanged = mysqli_query(
				$this->connection,
				"UPDATE ".TB_PREFIX."units SET u99 = GREATEST(0,u99 - $captured), ".
				"u99o = GREATEST(0,u99o - $captured) WHERE vref = $wref"
			);
			if(!$trapsChanged || mysqli_affected_rows($this->connection) !== 1) {
				mysqli_query($this->connection,"UPDATE ".TB_PREFIX."attacks SET ".implode(',',$subtractions)." WHERE id = $attackId");
				return false;
			}
			$removed = mysqli_query(
				$this->connection,
				"DELETE FROM ".TB_PREFIX."prisoners WHERE id = $id AND wref = $wref AND `from` = $from"
			);
			if(!$removed || mysqli_affected_rows($this->connection) !== 1) {
				mysqli_query($this->connection,"UPDATE ".TB_PREFIX."units SET u99 = u99 + $captured, u99o = u99o + $captured WHERE vref = $wref");
				mysqli_query($this->connection,"UPDATE ".TB_PREFIX."attacks SET ".implode(',',$subtractions)." WHERE id = $attackId");
				return false;
			}
			return true;
		} finally {
			mysqli_query($this->connection,"UNLOCK TABLES");
		}
	}

	function disbandPrisonersAtomic($id,$wref,$from,$owner) {
		$id = (int)$id;
		$wref = (int)$wref;
		$from = (int)$from;
		$owner = (int)$owner;
		if($id <= 0 || $wref <= 0 || $from <= 0 || $owner <= 0) {
			return false;
		}
		$locked = mysqli_query(
			$this->connection,
			"LOCK TABLES ".TB_PREFIX."prisoners WRITE, ".TB_PREFIX."units WRITE, ".
			TB_PREFIX."hero WRITE, ".TB_PREFIX."vdata READ"
		);
		if(!$locked) {
			return false;
		}
		try {
			$result = mysqli_query(
				$this->connection,
				"SELECT * FROM ".TB_PREFIX."prisoners WHERE id = $id AND wref = $wref AND `from` = $from LIMIT 1"
			);
			$prisoner = $result ? mysqli_fetch_assoc($result) : false;
			if(!$prisoner) {
				return false;
			}
			$result = mysqli_query(
				$this->connection,
				"SELECT owner FROM ".TB_PREFIX."vdata WHERE wref = $from LIMIT 1"
			);
			$origin = $result ? mysqli_fetch_assoc($result) : false;
			if(!$origin || (int)$origin['owner'] !== $owner) {
				return false;
			}
			$captured = 0;
			for($i = 1; $i <= 11; $i++) {
				$captured += max(0,(int)$prisoner['t'.$i]);
			}
			$heroBefore = false;
			if((int)$prisoner['t11'] > 0) {
				$result = mysqli_query(
					$this->connection,
					"SELECT dead,health FROM ".TB_PREFIX."hero WHERE uid = $owner LIMIT 1"
				);
				$heroBefore = $result ? mysqli_fetch_assoc($result) : false;
				if(!$heroBefore) {
					return false;
				}
			}
			$trapsChanged = mysqli_query(
				$this->connection,
				"UPDATE ".TB_PREFIX."units SET u99o = GREATEST(0,u99o - $captured) WHERE vref = $wref"
			);
			if(!$trapsChanged || mysqli_affected_rows($this->connection) !== 1) {
				return false;
			}
			if($heroBefore) {
				$heroChanged = mysqli_query(
					$this->connection,
					"UPDATE ".TB_PREFIX."hero SET dead = 1, health = 0 WHERE uid = $owner"
				);
				if(!$heroChanged) {
					mysqli_query($this->connection,"UPDATE ".TB_PREFIX."units SET u99o = u99o + $captured WHERE vref = $wref");
					return false;
				}
			}
			$removed = mysqli_query(
				$this->connection,
				"DELETE FROM ".TB_PREFIX."prisoners WHERE id = $id AND wref = $wref AND `from` = $from"
			);
			if(!$removed || mysqli_affected_rows($this->connection) !== 1) {
				mysqli_query($this->connection,"UPDATE ".TB_PREFIX."units SET u99o = u99o + $captured WHERE vref = $wref");
				if($heroBefore) {
					$dead = (int)$heroBefore['dead'];
					$health = (float)$heroBefore['health'];
					mysqli_query($this->connection,"UPDATE ".TB_PREFIX."hero SET dead = $dead, health = $health WHERE uid = $owner");
				}
				return false;
			}
			return true;
		} finally {
			mysqli_query($this->connection,"UNLOCK TABLES");
		}
	}

	function addPrisoners($wid,$from,$t1,$t2,$t3,$t4,$t5,$t6,$t7,$t8,$t9,$t10,$t11) {
		$q = "INSERT INTO " . TB_PREFIX . "prisoners values (0,$wid,$from,$t1,$t2,$t3,$t4,$t5,$t6,$t7,$t8,$t9,$t10,$t11)";
		mysqli_query($this->connection,$q);
		return mysqli_insert_id($this->connection);
	}

	function updatePrisoners($wid,$from,$t1,$t2,$t3,$t4,$t5,$t6,$t7,$t8,$t9,$t10,$t11) {
		$q = "UPDATE " . TB_PREFIX . "prisoners set t1 = t1 + $t1, t2 = t2 + $t2, t3 = t3 + $t3, t4 = t4 + $t4, t5 = t5 + $t5, t6 = t6 + $t6, t7 = t7 + $t7, t8 = t8 + $t8, t9 = t9 + $t9, t10 = t10 + $t10, t11 = t11 + $t11 where wref = $wid and `from` = $from";
		return mysqli_query($this->connection,$q) or die(mysqli_error());
	}

	function destroyUsedTraps($wid,$amount) {
		$wid = (int)$wid;
		$amount = max(0, (int)$amount);
		if($wid <= 0 || $amount === 0) {
			return true;
		}
		$q = "UPDATE " . TB_PREFIX . "units SET u99 = GREATEST(0, u99 - $amount), u99o = GREATEST(0, u99o - $amount) WHERE vref = $wid";
		return mysqli_query($this->connection,$q);
	}

	function areAlliancesAllied($firstAlliance,$secondAlliance) {
		$firstAlliance = (int)$firstAlliance;
		$secondAlliance = (int)$secondAlliance;
		if($firstAlliance <= 0 || $secondAlliance <= 0) {
			return false;
		}
		$q = "SELECT 1 FROM " . TB_PREFIX . "diplomacy WHERE accepted = 1 AND type = 1 AND ((alli1 = $firstAlliance AND alli2 = $secondAlliance) OR (alli1 = $secondAlliance AND alli2 = $firstAlliance)) LIMIT 1";
		$result = mysqli_query($this->connection,$q);
		return $result && mysqli_num_rows($result) > 0;
	}

	function getPrisoners($wid) {
		$q = "SELECT * FROM " . TB_PREFIX . "prisoners where wref = $wid";
		$result = mysqli_query($this->connection,$q);
		return $this->mysqli_fetch_all($result);
	}

	function getPrisoners2($wid,$from) {
		$q = "SELECT * FROM " . TB_PREFIX . "prisoners where wref = $wid and `from` = $from";
		$result = mysqli_query($this->connection,$q);
		return $this->mysqli_fetch_all($result);
	}

	function getPrisonersByID($id) {
		$id = (int)$id;
		$q = "SELECT * FROM " . TB_PREFIX . "prisoners where id = $id";
		$result = mysqli_query($this->connection,$q);
		return mysqli_fetch_array($result);
	}

	function getPrisoners3($from) {
		$q = "SELECT * FROM " . TB_PREFIX . "prisoners where `from` = $from";
		$result = mysqli_query($this->connection,$q);
		return $this->mysqli_fetch_all($result);
	}

	function deletePrisoners($id) {
		$id = (int)$id;
		$q = "DELETE from " . TB_PREFIX . "prisoners where id = $id";
		return mysqli_query($this->connection,$q);
	}

	function claimPrisoners($id,$wref,$from) {
		$id = (int)$id;
		$wref = (int)$wref;
		$from = (int)$from;
		if($id <= 0 || $wref <= 0 || $from <= 0) {
			return false;
		}
		$q = "DELETE FROM " . TB_PREFIX . "prisoners WHERE id = $id AND wref = $wref AND `from` = $from";
		$result = mysqli_query($this->connection,$q);
		return $result && mysqli_affected_rows($this->connection) === 1;
	}

	function freeUsedTraps($wid,$amount) {
		$wid = (int)$wid;
		$amount = max(0, (int)$amount);
		if($wid <= 0 || $amount <= 0) {
			return false;
		}
		$q = "UPDATE " . TB_PREFIX . "units SET u99o = GREATEST(0, u99o - $amount) WHERE vref = $wid";
		return mysqli_query($this->connection,$q);
	}

	function hasActiveAdventures($adv_time, $uid) {
        $time = time();
        $q = "SELECT * FROM " . TB_PREFIX . "hero where $time - lastadv > $adv_time AND uid = " . $uid;
        $result = mysqli_query($this->connection,$q);
        if (mysqli_num_rows($result)) {
            return true;
        } else {
            return false;
        }
    }

    private function mysqli_result($result, $row, $field = 0) {
        // Adjust the result pointer to that specific row
        $result->data_seek($row);
        // Fetch result array
        $data = $result->fetch_array();
        return $data[$field];
    }
};

		$database = new mysqli_DB;

function mysql_query($sql) {
	global $database;
	return mysqli_query($database->connection, $sql);
}

function mysql_fetch_assoc($var) {
	return mysqli_fetch_assoc($var);
}

function mysql_real_escape_string($var) {
	global $database;
	return mysqli_real_escape_string($database->connection, $var);
}

function mysql_num_rows($var) {
	return mysqli_num_rows($var);
}

function mysql_fetch_array($var) {
	return mysqli_fetch_array($var);
}

// Varios "or die(mysql_error())" repartidos por el proyecto (sysmsg, winner,
// el instalador). Sin esto, un query fallido termina en "undefined function"
// en vez del error real de MySQL.
function mysql_error() {
	global $database;
	return mysqli_error($database->connection);
}

?>
