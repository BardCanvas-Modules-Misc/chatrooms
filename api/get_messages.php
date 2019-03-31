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

use hng2_base\accounts_repository;
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

$banned_until = $account->engine_prefs["@chatrooms:{$_GET["chat"]}.banned_until"];
if( ! empty($banned_until) )
{
    if( date("Y-m-d H:i:s") >= $banned_until )
    {
        $account->set_engine_pref("@chatrooms:{$_GET["chat"]}.banned_until", "");
    }
    else
    {
        die(json_encode(array(
            "message" => "OK",
            "data"    => array(),
            "meta"    => array(
                "since"                  => $_GET["since"],
                "last_message_timestamp" => "",
                "warns"                  => array(replace_escaped_objects(
                    $current_module->language->messages->banned_until,
                    array('{$time}' => current(explode(" ", time_remaining_string($banned_until))))
                )),
                "suspend_ops" => true,
            )
        )));
    }
}

$since = empty($_GET["since"]) ? date("Y-m-d H:i:s", strtotime("now - 24 hours")) : $_GET["since"];
$filter = array("chat_name = '{$_GET["chat"]}'", "sent > '$since'");
$rows   = $repository->find($filter, 0, 0, "sent desc");

$active_users = array();

$lts = "";
if( ! empty($rows) )
{
    $row = current($rows);
    $lts = $row->sent;
    $rows = array_reverse($rows);
    reset($rows);
    
    $aids = array();
    foreach($rows as $row) $aids[] = $row->id_sender;
    reset($rows);
    
    # Banned users flagging
    $arepo = new accounts_repository();
    $prefs = $arepo->get_multiple_engine_prefs($aids, "@chatrooms:{$_GET["chat"]}.banned_until");
    if( ! empty($prefs) )
    {
        foreach($rows as &$row)
        {
            if( empty($prefs[$row->id_sender]) ) continue;
            if( date("Y-m-d H:i:s") > $prefs[$row->id_sender] ) continue;
            
            $row->_sender_is_banned = true;
        }
    }
    
    # Greeting with chatting users list
    if( empty($_GET["since"]) )
    {
        $boundary = date("Y-m-d H:i:s", strtotime("now - 5 minutes"));
        
        foreach($rows as $row)
            if( $row->sent >= $boundary && ! $row->_sender_is_banned && $row->id_sender != $account->id_account )
                $active_users[] = "<a class='user_display_name' data-user-level='{$row->sender_level}'
                                      href='{$config->full_root_path}/user/{$row->sender_user_name}'><i 
                                      class='fa fa-user fa-fw'></i>{$row->sender_display_name}</a>";
    }
}

$meta = (object) array(
    "since"                  => $_GET["since"],
    "last_message_timestamp" => $lts,
);

if( empty($_GET["since"]) && ! empty($active_users) ) $meta->active_users = $active_users;

if( $account->level >= $config::MODERATOR_USER_LEVEL )
{
    $warns = array();
    foreach($account->engine_prefs as $key => $val)
    {
        if( ! stristr($key, "@chatrooms:{$_GET["chat"]}.report/") ) continue;
        if( empty($val) ) continue;
        
        $warns[] = $val;
        $account->set_engine_pref($key, "");
    }
    
    if( count($warns) > 0 ) $meta->warns = $warns;
}

die(json_encode(array("message" => "OK", "data" => $rows, "meta" => $meta)));
