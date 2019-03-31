<?php
/**
 * Chatroom messages deliverer
 *
 * @package    HNG2
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * $_GET params:
 * @param string "chat"
 * @param string "since"     datetime string
 *
 * @return string JSON object:
 *                {
 *                    message:string,
 *                    data:[userid, user_name, user_display_name, message, sent],
 *                    meta: {
 *                        since:string,
 *                        last_message_timestamp:string
 *                    }
 *                }
 */

use hng2_modules\chatrooms\chatroom_messages_repository;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

if( $account->level < $config::NEWCOMER_USER_LEVEL || $account->state != "enabled" || ! $account->_exists )
    # die(json_encode(array("message" => trim($language->errors->access_denied))));
    throw_fake_401();

if( empty($_GET["chat"]) )
    die(json_encode(array("message" => trim($current_module->language->messages->chat_name_missing))));

$repository = new chatroom_messages_repository();
$chats = $repository->get_chatrooms_list();

if( ! isset($chats[$_GET["chat"]]) )
    die(json_encode(array("message" => sprintf($current_module->language->messages->chat_unexistent, $_GET["chat"]), "chats_registry" => $chats)));

$chat = $chats[$_GET["chat"]];
if( $account->level < $chat->min_level )
    # die(json_encode(array("message" => trim($language->errors->access_denied))));
    throw_fake_401();

if( ! empty($_GET["since"]) && ! strtotime($_GET["since"]) )
    die(json_encode(array("message" => trim($current_module->language->messages->invalid_timestamp))));

$since = empty($_GET["since"]) ? date("Y-m-d H:i:s", strtotime("now - 24 hours")) : $_GET["since"];
$filter = array("chat_name = '{$_GET["chat"]}'", "sent > '$since'");
$rows   = $repository->find($filter, 0, 0, "sent desc");

$lts = "";
if( ! empty($rows) )
{
    $row = current($rows);
    $lts = $row->sent;
    $rows = array_reverse($rows);
}

$meta = (object) array(
    "since"                  => $_GET["since"],
    "last_message_timestamp" => $lts,
    "query"                  => $database->get_last_query(),
);

die(json_encode(array("message" => "OK", "data" => $rows, "meta" => $meta)));
