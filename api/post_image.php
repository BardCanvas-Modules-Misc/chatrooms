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
 * @param string "image"
 *
 * @return string OK|Error message
 */

use hng2_modules\chatrooms\chatroom_message_record;
use hng2_modules\chatrooms\chatroom_messages_repository;

include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: text/plain; charset=utf-8");

if( $account->level < $config::NEWCOMER_USER_LEVEL || $account->state != "enabled" || ! $account->_exists )
    die( $language->errors->access_denied );

if( empty($_POST["chat"]) ) die( $current_module->language->messages->chat_name_missing );

$banned_until = $account->engine_prefs["@chatrooms:{$_POST["chat"]}.banned_until"];
if( ! empty($banned_until) )
{
    if( date("Y-m-d H:i:s") >= $banned_until )
        $account->set_engine_pref("@chatrooms:{$_POST["chat"]}.banned_until", "");
    else
        die(json_encode(array("message" => replace_escaped_objects($current_module->language->messages->banned_until,
            array('{$time}' => current(explode(" ", time_remaining_string($banned_until))))
        ))));
}

if( empty($_FILES["image"]) )
    die( $current_module->language->messages->no_image_uploaded );

if( empty($_FILES["image"]["size"]) )
    die( $current_module->language->messages->empty_image_uploaded );

if( ! is_uploaded_file($_FILES["image"]["tmp_name"]) )
    die( $current_module->language->messages->invalid_image_uploaded );

$target_dir = "{$config->datafiles_location}/uploaded_chat_images";
if( ! is_dir($target_dir) )
    if( ! @mkdir($target_dir) )
        die( $current_module->language->messages->cannot_create_images_directory );



$repository = new chatroom_messages_repository();
$chats = $repository->get_chatrooms_list();

if( ! isset($chats[$_POST["chat"]]) ) die( $current_module->language->messages->chat_unexistent );

$chat = $chats[$_POST["chat"]];
if( $account->level < $chat->min_level ) die( $language->errors->access_denied );


$slug      = wp_sanitize_filename($_POST["chat"]);
$parts     = explode(".", $_FILES["image"]["name"]);
$extension = strtolower(array_pop($parts));
$filename  = sprintf("%s-%s.%s", $account->user_name, wp_sanitize_filename(implode(".", $parts)), $extension);

if( ! in_array($extension, array("gif", "png", "jpg", "jpeg")) )
    die( $current_module->language->messages->invalid_image_uploaded );

$container   = "$slug/" . date("Y-m");
$target_dir .= "/" .$container;
if( ! is_dir($target_dir) )
    if( ! @mkdir($target_dir, 0777, true) )
        die(replace_escaped_objects($current_module->language->messages->cannot_create_directory, array(
            '{$dir}' => $container
        )));

$target_file = "$target_dir/$filename";
if( is_file($target_file) )
    die( $current_module->language->messages->file_already_uploaded );

if( ! @move_uploaded_file($_FILES["image"]["tmp_name"], $target_file) )
    die( $current_module->language->messages->cannot_move_file );

$res = $repository->save(new chatroom_message_record(array(
    "chat_name" => $_POST["chat"],
    "id_sender" => $account->id_account,
    "contents"  => "@image:$container/$filename",
    "sent"      => date("Y-m-d H:i:s"),
)));

echo "OK";
