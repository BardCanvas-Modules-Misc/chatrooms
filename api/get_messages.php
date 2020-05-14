<?php
/**
 * Chatroom messages deliverer
 *
 * @package    BardCanvas
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

use hng2_modules\chatrooms\accounts_repository_extender;
use hng2_modules\chatrooms\chatroom_messages_repository;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

if( $account->level < $config::NEWCOMER_USER_LEVEL || $account->state != "enabled" || ! $account->_exists )
    # die(json_encode(array("message" => trim($language->errors->access_denied))));
    throw_fake_401();

$chat_name = trim(stripslashes($_GET["chat"]));
$since     = trim(stripslashes($_GET["since"]));

if( empty($chat_name) )
    die(json_encode(array("message" => trim($current_module->language->messages->chat_name_missing))));

if( ! empty($since) && ! strtotime($since) )
    die(json_encode(array("message" => trim($current_module->language->messages->invalid_timestamp))));

$check = array($chat_name);
if( ! empty($since) ) $check[] = $since;
try
{
    check_sql_injection($check);
}
catch(\Exception $e)
{
    die(json_encode(array("message" => $e->getMessage())));
}

$repository = new chatroom_messages_repository();
$chats = $repository->get_chatrooms_list();

if( ! isset($chats[$chat_name]) )
    die(json_encode(array("message" => sprintf($current_module->language->messages->chat_unexistent, $chat_name), "chats_registry" => $chats)));

$chat = $chats[$chat_name];
if( $account->level < $chat->min_level )
    # die(json_encode(array("message" => trim($language->errors->access_denied))));
    throw_fake_401();

$banned_until = $account->engine_prefs["@chatrooms:{$chat_name}.banned_until"];
if( ! empty($banned_until) )
{
    if( date("Y-m-d H:i:s") >= $banned_until )
    {
        $account->set_engine_pref("@chatrooms:{$chat_name}.banned_until", "");
    }
    else
    {
        die(json_encode(array(
            "message" => "OK",
            "data"    => array(),
            "meta"    => array(
                "since"                  => $since,
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

$since = empty($since) ? date("Y-m-d H:i:s", strtotime("now - 24 hours")) : $since;
$filter = array("chat_name = '{$chat_name}'", "sent > '$since'");
$rows   = $repository->find($filter, 0, 0, "sent desc");

$active_users = array();

$lts = "";
if( ! empty($rows) )
{
    $row = current($rows);
    $lts = $row->sent;
    $rows = array_reverse($rows);
    
    $aids = array();
    foreach($rows as $row) $aids[] = $row->id_sender;
    $aids = array_unique($aids);
    
    # Banned users flagging
    $arepo = new accounts_repository_extender();
    $prefs  = $arepo->get_multiple_engine_prefs($aids, "@chatrooms:{$chat_name}.banned_until");
    $colors = $arepo->get_multiple_engine_prefs($aids, "@chatrooms:default_color");
    if( ! empty($colors) )
    {
        foreach($rows as $key => $row)
            if( isset($colors[$row->id_sender]) )
                $rows[$key]->_color = $colors[$row->id_sender];
    }
    if( ! empty($prefs) )
    {
        foreach($rows as $key => $row)
        {
            if( empty($prefs[$row->id_sender]) ) continue;
            if( date("Y-m-d H:i:s") > $prefs[$row->id_sender] ) continue;
            
            $rows[$key]->_sender_is_banned = true;
        }
    }
    
    # Greeting with chatting users list
    if( empty($since) )
    {
        $boundary = date("Y-m-d H:i:s", strtotime("now - 5 minutes"));
        
        foreach($rows as $row)
            if( $row->sent >= $boundary && ! $row->_sender_is_banned && $row->id_sender != $account->id_account )
                $active_users[$row->sender_display_name]
                    = "<a class='user_display_name' data-user-level='{$row->sender_level}'
                          href='{$config->full_root_path}/user/{$row->sender_user_name}'><i 
                          class='fa fa-user fa-fw'></i>{$row->sender_display_name}</a>";
    }
}

$meta = (object) array(
    "since"                  => $since,
    "last_message_timestamp" => $lts,
);

if( empty($since) && ! empty($active_users) ) $meta->active_users = array_values($active_users);

if( $account->level >= $config::MODERATOR_USER_LEVEL )
{
    $warns = array();
    foreach($account->engine_prefs as $key => $val)
    {
        if( ! stristr($key, "@chatrooms:{$chat_name}.report/") ) continue;
        if( empty($val) ) continue;
        
        $warns[] = $val;
        $account->set_engine_pref($key, "");
    }
    
    if( count($warns) > 0 ) $meta->warns = $warns;
}

die(json_encode(array("message" => "OK", "data" => $rows, "meta" => $meta)));
