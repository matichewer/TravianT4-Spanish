<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       Profile.php                                                 ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################


class Profile {

	public function procProfile($post) {
		if(isset($post['ft'])) {
			switch($post['ft']) {
				case "p1":
				$this->updateProfile($post);
				break;
				case "p3":
				$this->updateAccount($post);
				break;
			}
		}
		if(isset($post['s'])) {
			switch($post['s']) {
			case "4":
				$this->gpack($post);
			break;
			}
		}
	}

	public function procSpecial($get) {
		if(isset($get['e'])) {
			switch($get['e']) {
				case 2:
				$this->removeMeSit($get);
				break;
				case 3:
				$this->removeSitter($get);
				break;
				case 4:
				$this->cancelDeleting($get);
				break;
			}
		}
	}

	private function updateProfile($post) {
		global $database;
		$birthday = $post['jahr'].'-'.$post['monat'].'-'.$post['tag'];
		$database->submitProfile($database->RemoveXSS($post['uid']),$database->RemoveXSS($post['mw']),$database->RemoveXSS($post['ort']),$database->RemoveXSS($birthday),$database->RemoveXSS($post['be2']),$database->RemoveXSS($post['be1']));
		$varray = $database->getProfileVillages($post['uid']);
		for($i=0;$i<=count($varray)-1;$i++) {
			if(trim($post['dname'.$i]) != ""){
			$database->setVillageName($database->RemoveXSS($varray[$i]['wref']),$database->RemoveXSS($post['dname'.$i]));
			}
		}
		header("Location: ?uid=".$post['uid']);
	}

	private function gpack($post) {
		global $database, $session;
		$database->gpack($database->RemoveXSS($session->uid),$database->RemoveXSS($post['custom_url']));
		header("Location: ?uid=".$session->uid);
	}
    private function updateAccount($post) {
        global $database,$session,$form;
        if(!empty($session->is_sitter) || !isset($post['a']) || !hash_equals((string)$session->checker, (string)$post['a'])) { return; }
        $post['uid'] = (int)$session->uid;
        if(!empty($post['pw1']) || !empty($post['pw2']) || !empty($post['pw3'])) {
            if(!empty($post['pw2']) && $post['pw2'] == $post['pw3']) {
                if($database->login($session->username,$post['pw1'])) {
                    $database->updateUserField($post['uid'],"password",md5($post['pw2']),1);
                }
                else {
                    $form->addError("pw",LOGIN_PW_ERROR);
                }
            }
            else {
                $form->addError("pw",PASS_MISMATCH);
            }
        }
        if(!empty($post['email_alt']) || !empty($post['email_neu'])) {
            if($post['email_alt'] == $session->userinfo['email']) {
                $database->updateUserField($post['uid'],"email",$post['email_neu'],1);
            }
            else {
                $form->addError("email",EMAIL_ERROR);
            }
        }
        if(!empty($post['del'])) {
            if($post['del'] && md5($post['del_pw']) == $session->userinfo['password']) {
                if($database->isAllianceOwner($post['uid'])) {
                    $form->addError("del",ALLI_OWNER);
                }
                else {
                    $database->setDeleting($post['uid'],0);
                }
            }
            else {
                $form->addError("del",PASS_MISMATCH);
            }
        }
        $owner = $database->getUserArray($session->uid, 1);
        foreach(array(1, 2) as $slot) {
            $name = trim((string)($post['v'.$slot] ?? ''));
            if($name === '') { continue; }
            $candidate = $database->getUserArray(mysqli_real_escape_string($database->connection, $name), 0);
            $id = (int)($candidate['id'] ?? 0);
            if(!$id || !isPlayerAccount($id)) {
                $form->addError('sit', 'No existe un jugador con ese nombre.');
            } elseif($id === (int)$session->uid) {
                $form->addError('sit', 'No puedes ser tu propio representante.');
            } elseif((int)$candidate['access'] === BANNED) {
                $form->addError('sit', 'Este jugador está sancionado.');
            } elseif($id === (int)$owner['sit1'] || $id === (int)$owner['sit2']) {
                $form->addError('sit', 'Este jugador ya es tu representante.');
            } elseif(!empty($owner['sit'.$slot])) {
                $form->addError('sit', 'Ese espacio de representante ya está ocupado.');
            } elseif(!$database->updateUserField($session->uid, 'sit'.$slot, $id, 1)) {
                $form->addError('sit', 'No se pudo guardar el representante. Inténtalo nuevamente.');
            } else {
                $owner['sit'.$slot] = $id;
            }
        }
        $_SESSION['valuearray'] = array();
        $_SESSION['errorarray'] = $form->getErrors();
        header("Location: spieler.php?s=3");
    }

    private function removeSitter($get) {
        global $database,$session;
        if(empty($session->is_sitter) && isset($get['a']) && hash_equals((string)$session->checker, (string)$get['a'])) {
            $type = (int)($get['type'] ?? 0);
            if($type === 3) {
                $database->removeMeSit((int)($get['owner'] ?? 0), (int)$session->uid);
            } elseif(in_array($type, array(1, 2), true) && (int)$session->userinfo['sit'.$type] === (int)($get['id'] ?? 0)) {
                $database->updateUserField($session->uid, 'sit'.$type, 0, 1);
            }
            $session->changeChecker();
        }
        header("Location: spieler.php?s=".$get['s']);
    }

	private function cancelDeleting($get) {
		global $database;
		$database->setDeleting($get['id'],1);
		header("Location: spieler.php?s=".$get['s']);
	}

	private function removeMeSit($get) {
		global $database,$session;
		if(empty($session->is_sitter) && isset($get['a']) && hash_equals((string)$session->checker, (string)$get['a'])) {
			$database->removeMeSit((int)$get['id'],(int)$session->uid);
			$session->changeChecker();
		}
		header("Location: spieler.php?s=".$get['s']);
	}
};
$profile = new Profile;
?>