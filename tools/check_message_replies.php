<?php
// No real messages are sent: exercise reply preparation and the actual compose template.
require_once __DIR__.'/../GameEngine/Message.php';
ob_start();
$session = (object)array('uid' => 101, 'plus' => false);
$_SESSION = array();
function checkReply($condition, $label) {
    if (!$condition) { throw new RuntimeException($label); }
}
$message = (new ReflectionClass('Message'))->newInstanceWithoutConstructor();
$row = array('id' => 180, 'owner' => 101, 'target' => 102,
    'topic' => 'RE:RE:Reportes "uno"',
    'message' => '[message]Mi respuesta anterior\nReply:\n[report1]77[/report1]</textarea>[/message]',
    'alliance' => 0, 'player' => 0, 'coor' => 0, 'report' => 1);
$message->sent = array($row);
checkReply($message->quoteMessage(180) === 102, 'Sent reply must address the original recipient');
checkReply(strpos($_SESSION['reply']['message'], 'Mi respuesta anterior') !== false, 'Keep previous answer');
checkReply(strpos($_SESSION['reply']['message'], '[report0]77[/report0]') !== false, 'Keep quoted report link');
checkReply(strpos($_SESSION['reply']['message'], '[message]') === false, 'Remove storage wrapper so sending is allowed');
class ReplyTemplateDatabase {
    function getUserField($id, $field, $mode) { return 'Destinatario'; }
    function getUserArray($id, $mode) {
        $row = array();
        for ($i = 0; $i < 20; $i++) { $row['friend'.$i] = $row['friend'.$i.'wait'] = 0; }
        return $row;
    }
}
$database = new ReplyTemplateDatabase();
$id = 102;
include __DIR__.'/../Templates/Message/write.tpl';
$html = ob_get_clean();
checkReply(strpos($html, 'value="RE:Reportes &quot;uno&quot;"') !== false, 'Stable, escaped reply subject');
checkReply(strpos($html, '&lt;/textarea&gt;') !== false, 'Quote must stay inside the editor');
checkReply(strpos($html, 'Mi respuesta anterior') !== false, 'Previous answer is visible in compose');
ob_start();
$message->sent = array();
$row['owner'] = 102; $row['target'] = 101;
$message->inbox = array($row);
checkReply($message->quoteMessage(180) === 102, 'Received reply addresses sender');
$message->inbox = array();
$message->archived = array($row);
$_SESSION = array();
checkReply($message->quoteMessage(180) === null && !isset($_SESSION['reply']), 'Archive requires Plus');
$session->plus = true;
checkReply($message->quoteMessage(180) === 102, 'Archived reply addresses sender');
$_SESSION = array();
checkReply($message->quoteMessage(999) === null && !isset($_SESSION['reply']), 'Unknown message cannot be quoted');
ob_end_clean();
echo "Message reply checks passed\n";
