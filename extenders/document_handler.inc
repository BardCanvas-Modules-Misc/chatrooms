<?php
/**
 * Document handler for single chatrooms. Syntax:
 * /chatroom/<name_as_slug>
 *
 * @package    BardCanvas
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 *
 * @var template $template
 * 
 * $_GET params:
 * @param "handle"
 */

use hng2_base\template;

if( empty($_GET["handle"]) ) return;

$handle = trim(stripslashes($_GET["handle"]));
$parts  = explode("/", $handle);
if( count($parts) != 2 ) return;
if( strtolower($parts[0]) != "chatroom" ) return;

$current_chat_slug = $parts[1];

#
# Inits and checks
#

$raw_chats_registry = $settings->get("modules:chatrooms.chatrooms_registry");
if( empty($raw_chats_registry) ) throw_fake_404();

# Note: here the registry is keyed by slug
$chats_registry = array();
foreach(explode("\n", $raw_chats_registry) as $line)
{
    $line = trim($line);
    if( empty($line) ) continue;
    if( substr($line, 0, 1) == "#" ) continue;
    
    $parts = explode("|", $line);
    $name  = trim($parts[0]);
    $slug  = wp_sanitize_filename($name);
    $title = trim($parts[1]);
    $level = trim($parts[2]);
    
    $chats_registry[$slug] = (object) array("name" => $name, "title" => $title, "level" => $level);
}
if( empty($chats_registry) ) throw_fake_404();
if( ! isset($chats_registry[$current_chat_slug]) ) throw_fake_404();

$current_chat = $chats_registry[$current_chat_slug];

if( ! $account->_exists ) throw_fake_401();
if( $account->level < $current_chat->level ) throw_fake_401();

$page_title = replace_escaped_objects($this_module->language->single->title, array('{$title}' => $current_chat->name));
$template->set_page_title($page_title);
$template->page_meta_description   = $current_chat->title;
$template->page_meta_url           = $config->full_root_url . $_SERVER["REQUEST_URI"];
$template->page_extra_metas["og:type"] = "<meta property='og:type' content='article'>";
$template->append("additional_body_attributes", " data-chatroom-name='{$current_chat->name}'");
$template->set("page_tag", "chatroom");

# @hack: The definition below is a hack that shouldn't be repeated unless strictly neccesary :P
$template->page_contents_include = "single_room.inc";
include "{$template->abspath}/popup.php";
exit;
