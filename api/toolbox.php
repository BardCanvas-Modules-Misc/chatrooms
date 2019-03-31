<?php
/**
 * Chatroom toolbox
 *
 * @package    BardCanvas
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * $_POST params:
 * @param string "action" report|kick|ban|unban
 * @param string "chat"
 * @param string "user_id"
 * @param string "reason"
 * @param number "window" When action is "ban", this should be the amount of hours.
 *
 * @return string OK|Error message
 */

use hng2_base\account;
use hng2_base\accounts_repository;
use hng2_modules\chatrooms\chatroom_message_record;
use hng2_modules\chatrooms\chatroom_messages_repository;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: text/plain; charset=utf-8");

#
# Checks
#

if( $account->level < $config::NEWCOMER_USER_LEVEL || $account->state != "enabled" || ! $account->_exists )
    die( $language->errors->access_denied );

if( empty($_POST["chat"]) ) die( $current_module->language->messages->chat_name_missing );
if( empty($_POST["action"]) ) die( $current_module->language->messages->invalid_action );
if( empty($_POST["user_id"]) ) die( $current_module->language->messages->invalid_uid );
if( ! in_array($_POST["action"], array("report", "kick", "ban", "unban")) )
    die( $current_module->language->messages->invalid_action );
if( in_array($_POST["action"], array("kick", "ban", "unban")) && $account->level < $config::MODERATOR_USER_LEVEL )
    die( $language->errors->access_denied );
if( $_POST["action"] == "ban" && (empty($_POST["window"]) || ! is_numeric($_POST["window"])) )
    die( $current_module->language->messages->missing_window );
if( $_POST["action"] == "ban" && $_POST["window"] > 720 && $_POST["window"] < 999 )
    die( $current_module->language->messages->missing_window );
if( $_POST["action"] == "ban" && $_POST["window"] > 999 )
    die( $current_module->language->messages->missing_window );

$target = new account($_POST["user_id"]);

if( ! $target->_exists ) die( $current_module->language->messages->user_not_found );
if( $target->state != "enabled" ) die( $current_module->language->messages->user_disabled );
if( in_array($_POST["action"], array("report", "kick", "ban") ) && $target->level >= $config::MODERATOR_USER_LEVEL )
    die( $current_module->language->messages->mods_cannot_be_touched );

$repository = new chatroom_messages_repository();
$chats      = $repository->get_chatrooms_list();

if( ! isset($chats[$_POST["chat"]]) )
    die(sprintf($current_module->language->messages->chat_unexistent, $_POST["chat"]));

#
# Report
#

if( $_POST["action"] == "report" )
{
    if( empty($_POST["reason"]) ) die( $current_module->language->messages->missing_reason );
    
    $report_key = "@chatrooms:last_report/{$_POST["chat"]};{$target->id_account}";
    $last_report = $account->engine_prefs[$report_key];
    if( ! empty($last_report) )
        if( $last_report >= date("Y-m-d H:i:s", strtotime("now - 12 hours")) )
            die($current_module->language->messages->already_reported);
    
    $account->set_engine_pref($report_key, date("Y-m-d H:i:s"));
    
    broadcast_mail_to_moderators(
        unindent(replace_escaped_objects(
            $current_module->language->messages->user_report_template->subject, array(
            '{$user}'   => $account->get_processed_display_name(),
            '{$target}' => $target->get_processed_display_name(),
            '{$chat}'   => $_POST["chat"],
        ))),
        unindent(replace_escaped_objects(
            $current_module->language->messages->user_report_template->body, array(
            '{$user}'   => $account->get_processed_display_name(),
            '{$target}' => $target->get_processed_display_name(),
            '{$chat}'   => $_POST["chat"],
            '{$reason}' => stripslashes($_POST["reason"]),
            '{$link}'   => "{$config->full_root_url}/chatroom/" . wp_sanitize_filename($_POST["chat"]),
        )))
    );
    
    broadcast_to_moderators("warning", unindent(replace_escaped_objects(
        $current_module->language->messages->user_report_template->notification, array(
        '{$user}'   => $account->get_processed_display_name(),
        '{$target}' => $target->get_processed_display_name(),
        '{$chat}'   => $_POST["chat"],
        '{$link}'   => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($_POST["chat"]),
    ))));
    
    $arepo = new accounts_repository();
    $rows  = $arepo->get_basics_above_level($config::MODERATOR_USER_LEVEL);
    foreach($rows as $row)
    {
        $key = "@chatrooms:{$_POST["chat"]}.report/$target->id_account";
        $adm = new account($row->id_account);
        $adm->set_engine_pref($key, unindent(replace_escaped_objects(
            $current_module->language->messages->user_report_template->chat_message, array(
            '{$user}'   => $account->get_processed_display_name(),
            '{$target}' => $target->get_processed_display_name(),
            '{$reason}' => stripslashes($_POST["reason"]),
        ))));
    }
    
    die("OK");
}

#
# Kick, ban prechecks
#

if( in_array($_POST["action"], array("kick", "ban")) )
{
    if( empty($_POST["reason"]) ) die( $current_module->language->messages->missing_reason );
    
    $banned_until = $target->engine_prefs["@chatrooms:{$_POST["chat"]}.banned_until"];
    if( ! empty($banned_until) )
        die(replace_escaped_objects($current_module->language->messages->user_banned_until, array(
            '{$time}' => current(explode(" ", time_remaining_string($banned_until)))
        )));
}

#
# Kick
#

if( $_POST["action"] == "kick" )
{
    $banned_until = date("Y-m-d H:i:s", strtotime("now + 10 minutes"));
    $target->set_engine_pref("@chatrooms:{$_POST["chat"]}.banned_until", $banned_until);
    $repository->save(new chatroom_message_record(array(
        "chat_name" => $_POST["chat"],
        "id_sender" => "0",
        "sent"      => date("Y-m-d H:i:s"),
        "contents"  => unindent(replace_escaped_objects(
            $current_module->language->messages->user_has_been_kicked, array(
            '{$user}'   => $target->get_processed_display_name(),
            '{$reason}' => stripslashes($_POST["reason"]),
        ))),
    )));
    
    die("OK");
}

#
# Ban
#

if( $_POST["action"] == "ban" )
{
    if( $_POST["window"] == 999 ) $_POST["window"] = 24 * 365 * 10;
    $banned_until = date("Y-m-d H:i:s", strtotime("now + {$_POST["window"]} hours"));
    $target->set_engine_pref("@chatrooms:{$_POST["chat"]}.banned_until", $banned_until);
    $repository->save(new chatroom_message_record(array(
        "chat_name" => $_POST["chat"],
        "id_sender" => "0",
        "sent"      => date("Y-m-d H:i:s"),
        "contents"  => unindent(replace_escaped_objects(
            $current_module->language->messages->user_has_been_banned, array(
            '{$user}'   => $target->get_processed_display_name(),
            '{$reason}' => stripslashes($_POST["reason"]),
            '{$time}'   => current(explode(" ", time_remaining_string($banned_until))),
        ))),
    )));
    
    broadcast_to_moderators("information", unindent(replace_escaped_objects(
        $current_module->language->messages->user_has_been_banned2, array(
        '{$user}'   => $account->get_processed_display_name(),
        '{$target}' => $target->get_processed_display_name(),
        '{$link}'   => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($_POST["chat"]),
        '{$chat}'   => $_POST["chat"],
        '{$time}'   => current(explode(" ", time_remaining_string($banned_until))),
        '{$reason}' => stripslashes($_POST["reason"]),
    ))));
    
    die("OK");
}

#
# Unban
#

if( $_POST["action"] == "unban" )
{
    $target->set_engine_pref("@chatrooms:{$_POST["chat"]}.banned_until", "");
    
    $repository->save(new chatroom_message_record(array(
        "chat_name" => $_POST["chat"],
        "id_sender" => "0",
        "sent"      => date("Y-m-d H:i:s"),
        "contents"  => unindent(replace_escaped_objects(
            $current_module->language->messages->unban->message, array(
            '{$target}' => $target->get_processed_display_name(),
        ))),
    )));
    
    send_notification($target->id_account, "success", unindent(replace_escaped_objects(
        $current_module->language->messages->unban->notification2, array(
        '{$chat}'   => $_POST["chat"],
        '{$link}'   => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($_POST["chat"]),
    ))));
    
    broadcast_to_moderators("information", unindent(replace_escaped_objects(
        $current_module->language->messages->unban->notification, array(
        '{$user}'   => $account->get_processed_display_name(),
        '{$target}' => $target->get_processed_display_name(),
        '{$chat}'   => $_POST["chat"],
        '{$link}'   => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($_POST["chat"]),
    ))));
    
    die("OK");
}

echo $language->errors->invalid_call;
