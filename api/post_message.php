<?php
/**
 * Chatroom messages poster
 *
 * @package    HNG2
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * $_POST params:
 * @param string "chat"
 * @param string "message"
 *
 * @return string JSON object: { message:string[, data:int] }
 */

use hng2_modules\chatrooms\chatroom_message_record;
use hng2_modules\chatrooms\chatroom_messages_repository;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

if( $account->level < $config::NEWCOMER_USER_LEVEL || $account->state != "enabled" || ! $account->_exists )
    # die(json_encode(array("message" => trim($language->errors->access_denied))));
    throw_fake_401();

if( empty($_POST["chat"]) )
    die(json_encode(array("message" => trim($current_module->language->messages->chat_name_missing))));

$banned_until = $account->engine_prefs["@chatrooms:{$_POST["chat"]}.banned_until"];
if( ! empty($banned_until) )
{
    if( date("Y-m-d H:i:s") >= $banned_until )
        $account->set_engine_pref("@chatrooms:{$_POST["chat"]}.banned_until", "");
    else
        die(replace_escaped_objects($current_module->language->messages->banned_until, array(
            '{$time}' => current(explode(" ", time_remaining_string($banned_until)))
        )));
}

$repository = new chatroom_messages_repository();
$chats = $repository->get_chatrooms_list();

if( ! isset($chats[$_POST["chat"]]) )
    # die(json_encode(array("message" => sprintf($current_module->language->messages->chat_unexistent, $_POST["chat"]))));
    throw_fake_401();

$chat = $chats[$_POST["chat"]];
if( $account->level < $chat->min_level )
    die(json_encode(array("message" => trim($language->errors->access_denied))));

$res = $repository->save(new chatroom_message_record(array(
    "chat_name" => $_POST["chat"],
    "id_sender" => $account->id_account,
    "contents"  => stripslashes($_POST["message"]),
    "sent"      => date("Y-m-d H:i:s"),
)));

die(json_encode(array("message" => "OK", "data" => $res)));
