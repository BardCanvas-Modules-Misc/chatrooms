<?php
/**
 * Chatroom messages poster
 *
 * @package    BardCanvas
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
use hng2_modules\moderation\toolbox;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

if( $account->level < $config::NEWCOMER_USER_LEVEL || $account->state != "enabled" || ! $account->_exists )
    # die(json_encode(array("message" => trim($language->errors->access_denied))));
    throw_fake_401();

$chat_name = trim(stripslashes($_POST["chat"]));
$raw_messg = trim(stripslashes($_POST["message"]));

if( empty($chat_name) )
    die(json_encode(array("message" => trim($current_module->language->messages->chat_name_missing))));

if( empty($raw_messg) )
    die(json_encode(array("message" => trim($current_module->language->messages->empty_message))));

try
{
    check_sql_injection(array($chat_name, $raw_messg));
}
catch(\Exception $e)
{
    die(json_encode(array("message" => $e->getMessage())));
}

$banned_until = $account->engine_prefs["@chatrooms:{$chat_name}.banned_until"];
if( ! empty($banned_until) )
{
    if( date("Y-m-d H:i:s") >= $banned_until )
        $account->set_engine_pref("@chatrooms:{$chat_name}.banned_until", "");
    else
        die(replace_escaped_objects($current_module->language->messages->banned_until, array(
            '{$time}' => current(explode(" ", time_remaining_string($banned_until)))
        )));
}

$repository = new chatroom_messages_repository();
$chats = $repository->get_chatrooms_list();

if( ! isset($chats[$chat_name]) )
    # die(json_encode(array("message" => sprintf($current_module->language->messages->chat_unexistent, $chat_name))));
    throw_fake_401();

$chat = $chats[$chat_name];
if( $account->level < $chat->min_level )
    die(json_encode(array("message" => trim($language->errors->access_denied))));

$message = new chatroom_message_record(array(
    "chat_name" => $chat_name,
    "id_sender" => $account->id_account,
    "contents"  => strip_tags($raw_messg,"<b><i><big><small><sup><sub><code><br>"),
));

#
# Moderation checks
#

$content       = $message->get_processed_content();
$links         = $message->extract_links($content);
$links_count   = count($links);
if( $links_count > 0 && $account->level < $config::MODERATOR_USER_LEVEL )
{
    $permitted     = (int) $settings->get("modules:chatrooms.permitted_links");
    $always_notify = $settings->get("modules:chatrooms.always_notify_link_submissions") == "true";
    
    if( $permitted < 0 ) # Check disabled
    {
        if( $always_notify ) # Notify submission to mods
        {
            broadcast_to_moderators("alert", unindent(replace_escaped_objects(
                $current_module->language->messages->user_submitted_links, array(
                '{$user}'  => $account->get_processed_display_name(),
                '{$chat}'  => $message->chat_name,
                '{$link}'  => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($message->chat_name),
                '{$links}' => "• " . implode("<br>• ", $links),
            ))));
        }
    }
    elseif( $permitted == 0 ) # No links allowed at all
    {
        if( $always_notify ) # Notify attempt to mods
        {
            broadcast_to_moderators("alert", unindent(replace_escaped_objects(
                $current_module->language->messages->user_attempted_links, array(
                '{$user}'  => $account->get_processed_display_name(),
                '{$chat}'  => $message->chat_name,
                '{$link}'  => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($message->chat_name),
                '{$links}' => "• " . implode("<br>• ", $links),
            ))));
        }
        
        # Notify the user and abort
        die(json_encode(array("message" => trim(
            $current_module->language->messages->no_links_allowed
        ))));
    }
    else # Check enabled
    {
        if( $links_count <= $permitted )
        {
            if( $always_notify ) # Notify submission to mods
            {
                broadcast_to_moderators("alert", unindent(replace_escaped_objects(
                    $current_module->language->messages->user_submitted_links, array(
                    '{$user}'  => $account->get_processed_display_name(),
                    '{$chat}'  => $message->chat_name,
                    '{$link}'  => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($message->chat_name),
                    '{$links}' => "• " . implode("<br>• ", $links),
                ))));
            }
        }
        else # More links than those allowed
        {
            if( $always_notify ) # Notify attempt to mods
            {
                broadcast_to_moderators("alert", unindent(replace_escaped_objects(
                    $current_module->language->messages->user_attempted_links, array(
                    '{$user}'  => $account->get_processed_display_name(),
                    '{$chat}'  => $message->chat_name,
                    '{$link}'  => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($message->chat_name),
                    '{$links}' => "• " . implode("<br>• ", $links),
                ))));
            }
            
            # Notify the user and abort
            die(json_encode(array("message" => trim(
                $current_module->language->messages->attempting_to_submit_links
            ))));
        }
    }
}

if( isset($modules["moderation"]) && $modules["moderation"]->enabled )
{
    $toolbox = new toolbox();
    
    #
    # Blacklist check
    #
    
    if( $settings->get("modules:chatrooms.enforce_blacklist") == "true" )
    {
        $detected = $toolbox->probe_in_words_list($message->contents, "words_blacklist");
        if( ! empty($detected) )
        {
            die(json_encode(array("message" => unindent(replace_escaped_vars(
                $modules["moderation"]->language->messages->entries_in_blacklist_found,
                '{$detected_words_list}', implode(", ", $detected)
            )))));
        }
    }
    
    #
    # Greylist check
    #
    
    if( $settings->get("modules:chatrooms.enforce_greylist") == "true" )
    {
        $detected = $toolbox->probe_in_words_list($message->contents, "words_greylist");
        if( ! empty($detected) )
        {
            $message->contents = str_replace($detected, "beep", $message->contents);
            // broadcast_to_moderators("warning", unindent(replace_escaped_objects(
            //     $current_module->language->messages->greylist_notification_for_mods, array(
            //     '{$user}' => $account->get_processed_display_name(),
            //     '{$chat}' => $message->chat_name,
            //     '{$link}' => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($message->chat_name),
            //     '{$list}' => implode("</code>, <code>", $detected),
            // ))));
        }
    }
}

$message->sent = date("Y-m-d H:i:s");
$res = $repository->save($message);

die(json_encode(array("message" => "OK", "data" => $res)));
