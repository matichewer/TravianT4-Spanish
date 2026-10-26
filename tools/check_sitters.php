<?php
// Real Profile + DB methods, isolated users table; never modifies the game world.
if(PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
ob_start();
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) { throw new Exception("$message at $file:$line"); });
$_SESSION = array();
require 'config/connection.php';
require 'config/config.php';
require 'GameEngine/Database.php';
require 'GameEngine/Form.php';
require 'GameEngine/Profile.php';
mysql_query('CREATE TEMPORARY TABLE '.TB_PREFIX.'users (id INT PRIMARY KEY, username VARCHAR(50), password VARCHAR(32), email VARCHAR(100), access INT, sit1 INT DEFAULT 0, sit2 INT DEFAULT 0)');
mysql_query("INSERT INTO ".TB_PREFIX."users (id,username,password,email,access) VALUES (10,'Owner','hash','mail',2),(11,'First','hash','mail',2),(12,'Second','hash','mail',2),(13,'Banned','hash','mail',".BANNED."),(1,'Support','hash','mail',2),(14,'Other','hash','mail',2)");
$session = new class {
    public $gold = 0;
    public $uid = 10, $username = 'Owner', $checker = 'token', $is_sitter = 0, $userinfo;
    function changeChecker() { $this->checker .= 'x'; }
};
function verify($ok, $message) { if(!$ok) { throw new Exception($message); } }
function submitSitter($data) {
    global $session, $database, $profile, $form;
    $_SESSION = array();
    $session->userinfo = $database->getUserArray(10, 1);
    $form = new Form;
    $profile->procProfile(array_merge(array('ft'=>'p3','uid'=>14,'a'=>$session->checker), $data));
    return $form->getErrors();
}
verify(submitSitter(array('v2'=>' Second ')) === array(), 'Second field alone must work without unrelated errors');
verify((int)$database->getUserField(10,'sit2',0) === 12, 'Second slot saved to logged-in owner');
verify((int)$database->getUserField(14,'sit2',0) === 0, 'Posted uid cannot change another account');
foreach(array('Missing','Owner','Support','Banned','Second',"bad'quote") as $name) {
    $errors = submitSitter(array('v1'=>$name));
    verify(isset($errors['sit']), 'Must reject '.$name);
    $restored = new Form;
    verify($restored->getError('sit') !== '', 'Error survives redirect');
}
verify(submitSitter(array('v1'=>'First')) === array(), 'First slot saved independently');
verify((int)$database->getUserField(10,'sit1',0) === 11, 'First slot stored');
$session->userinfo = $database->getUserArray(10,1);
$profile->procSpecial(array('e'=>3,'s'=>3,'type'=>2,'id'=>12,'a'=>'invalid'));
verify((int)$database->getUserField(10,'sit2',0) === 12, 'Invalid removal token rejected');
$profile->procSpecial(array('e'=>3,'s'=>3,'type'=>2,'id'=>12,'a'=>$session->checker));
verify((int)$database->getUserField(10,'sit2',0) === 0, 'Owner removes second sitter');
mysql_query('UPDATE '.TB_PREFIX.'users SET sit1=10 WHERE id IN (11,12)');
verify(count($database->getSitee(10)) === 2, 'Two owners using the same slot are both listed');
$profile->procSpecial(array('e'=>3,'s'=>3,'type'=>3,'owner'=>11,'id'=>10,'a'=>$session->checker));
verify((int)$database->getUserField(11,'sit1',0) === 0, 'Sitter resigns from correct owner');
verify((int)$database->getUserField(12,'sit1',0) === 10, 'Other owner unchanged');
mysql_query("UPDATE ".TB_PREFIX."users SET password='".md5('sitter-password')."' WHERE id=11");
verify($database->sitterLogin('Owner', 'sitter-password'), 'Single first-slot sitter can log in without warnings');
verify(!$database->sitterLogin('Owner', 'wrong'), 'Wrong sitter password rejected');
verify(!$database->sitterLogin("missing'quote", 'sitter-password'), 'Missing login account safely rejected');
mysql_query('UPDATE '.TB_PREFIX.'users SET sit1=0, sit2=11 WHERE id=10');
verify($database->sitterLogin('Owner', 'sitter-password'), 'Single second-slot sitter can log in without warnings');
mysql_query('UPDATE '.TB_PREFIX.'users SET sit2=0 WHERE id=10');
$session->is_sitter = 1;
submitSitter(array('v2'=>'Second'));
verify((int)$database->getUserField(10,'sit2',0) === 0, 'Sitter cannot appoint another sitter');
$session->is_sitter = 0;
submitSitter(array('v2'=>'Second','a'=>'wrong'));
verify((int)$database->getUserField(10,'sit2',0) === 0, 'Invalid form token rejected');
// Render the real account tab after the redirect, including a partial success.
mysql_query('CREATE TEMPORARY TABLE '.TB_PREFIX.'deleting (uid INT, timestamp INT)');
mysql_query('INSERT INTO '.TB_PREFIX.'deleting VALUES (10,0)');
mysql_query('UPDATE '.TB_PREFIX.'users SET sit1=0,sit2=0 WHERE id=10');
$errors = submitSitter(array('v1'=>'First','v2'=>'Missing'));
verify(isset($errors['sit']) && count($_SESSION['sitter_success']) === 1, 'Partial success retains success and error');
$form = new Form;
$session->userinfo = $database->getUserArray(10,1);
ob_start();
include 'Templates/Profile/account.tpl';
$html = ob_get_clean();
verify(strpos($html, 'Representante 1 agregado correctamente: First.') !== false, 'Success appears in real template');
verify(strpos($html, 'No existe un jugador con ese nombre.') !== false, 'Error appears alongside success');
verify(strpos($html, 'id="representantes"') !== false, 'Redirect anchor exists');
verify(!isset($_SESSION['sitter_success']), 'Success notice is consumed once');
ob_start();
include 'Templates/Profile/account.tpl';
$html = ob_get_clean();
verify(strpos($html, 'agregado correctamente') === false, 'Refreshing does not repeat stale success');
$errors = submitSitter(array('v2'=>'Second','a'=>'expired'));
$form = new Form;
verify(strpos($form->getError('sit'), 'venció') !== false, 'Expired token has visible feedback');

ob_end_clean();
echo "OK: sitter assignment, validation, error persistence, ownership, listing and removal\n";
