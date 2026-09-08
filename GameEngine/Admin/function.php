<?php
#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Filename       function.php                                                ##
##  Developed by:  Dzoki                                                       ##
##  License:       TravianX Project                                            ##
##  Copyright:     TravianX (c) 2010-2011. All rights reserved.                ##
##                                                                             ##
#################################################################################

class funct {   
  
  function CheckLogin(){
    if($_SESSION['access'] >= MULTIHUNTER and $_SESSION['id']){
      return true;
    }else{
      return false;
    }                
  }
         
  function Act($get){
    global $admin,$database;

    switch($get['action']){
      case recountPop:
        $admin->recountPop($get['did']); 
      break;
      case recountPopUsr:
        $admin->recountPopUser($get['uid']); 
      break;
      case StopDel:
        //stop deleting
      break;
      case delVil:
        $admin->DelVillage($get['did'], 0); 
      break;
      case delBan:              
        $admin->DelBan($get['uid'],$get['id']);         
        //remove ban 
      break;
      case addBan:    
        if($get['time']){$end = time()+$get['time']; }else{$end = '';}
          
          if(preg_match("/^[0-9]+$/",$get['uid'])){
          //if(eregi("^[0-9]*+$",$get['uid'])){
          $get['uid'] = $get['uid'];
          }else{     
          $get['uid'] = $database->getUserField($get['uid'],'id',1);
          }           
             
        $admin->AddBan($get['uid'],$end,$get['reason']);         
        //add ban 
      break;
      case delOas:
        //oaza
      break;
      case logout:
        $this->LogOut();     
      break; 
    } 
    if($get['action'] == 'logout'){
      header("Location: admin.php");  
    }else{
      header("Location: ".$_SERVER['HTTP_REFERER']);
    }                
  }
  
  function Act2($post){
    global $admin,$database;
      switch($post['action']){  
      case DelPlayer:
        $admin->DelPlayer($post['uid'],$post['pass']);
        header("Location: ?p=search&msg=ursdel");
      break;
      case punish:
        $admin->Punish($post);
        header("Location: ".$_SERVER['HTTP_REFERER']);
      break;
      case addVillage:
        $admin->AddVillage($post);
        header("Location: ".$_SERVER['HTTP_REFERER']);
      break;
      }    
  }
  
  function LogIN($username,$password){
    global $admin,$database;
    if($admin->Login($username,$password)){
      session_regenerate_id(true);
      $_SESSION['access'] = $database->getUserField($username,'access',1);
      $_SESSION['id'] = $database->getUserField($username,'id',1);
      if ($_SESSION['username'] == '') { $_SESSION['username'] = $username; }
      header("Location: ".$_SERVER['HTTP_REFERER']);
      //header("Location: admin.php");      
    }else{
      echo "Error";
    }
  }
  
	  function LogOut(){
	    $_SESSION = array();
	    if(ini_get('session.use_cookies')) {
	      $params = session_get_cookie_params();
	      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	    }
	    if(session_status() === PHP_SESSION_ACTIVE) {
	      session_destroy();
	    }
	  }

	/**
	 * El nombre de un edificio, para las pantallas del panel.
	 *
	 * Delega en `buildingDisplayName()` (GameEngine/Catapult.php), que es la ÚNICA lista de
	 * nombres del juego. Acá vivía una cuarta copia escrita a mano —después de las que ya se
	 * habían unificado en `Automation::procResType()`, `Building::procResType()` y el catálogo
	 * de objetivos de catapulta—, y como toda copia ya había derivado: estaba en inglés salvo
	 * el 34, no conocía el gid 13 ni el 42 (el Gran taller salía como "Error"), y decía
	 * "Treasury" cuando el edificio 27 se llama Tesoro. El panel mostraba nombres que no
	 * coincidían con los que el jugador ve en su aldea.
	 */
	public function procResType($ref) {
		return buildingDisplayName((int)$ref);
	}
	
};

$funct = new funct;
if($funct->CheckLogin()){
  if($_GET['action']){
    $funct->Act($_GET);
  }
  if($_POST['action']){
    $funct->Act2($_POST);
  }
}
if($_POST['action']=='login'){
  $funct->LogIN($_POST['name'],$_POST['pw']);
}
?>
