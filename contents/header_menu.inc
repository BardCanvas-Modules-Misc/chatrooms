<?php
/**
 * Accounts header buttons
 *
 * @package    HNG2
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * @var account  $account
 * @var settings $settings
 * @var config   $config
 * @var template $template
 */

use hng2_base\account;
use hng2_base\config;
use hng2_base\settings;
use hng2_base\template;
use hng2_modules\chatrooms\chatroom_messages_repository;

if( ! $account->_exists ) return;
if( $account->level < $config::NEWCOMER_USER_LEVEL ) return;
if( $settings->get("modules:chatrooms.show_in_menu_bar") != "true" ) return;

$repository = new chatroom_messages_repository();
$all_chats  = $repository->get_chatrooms_list();
if( empty($all_chats) ) return;

$chatrooms = array();
foreach($all_chats as $name => $details)
    if( $account->level >= $details->min_level )
        $chatrooms[$name] = $details;
if( empty($chatrooms) ) return;

if( count($chatrooms) == 1 )
{
    $chat_name = key($chatrooms);
    $chat_slug = wp_sanitize_filename($chat_name);
    
    $template->add_menu_item(
        trim($this_module->language->single->menu),
        "
        <a class='main_menu_item' data-module='chatrooms' href='{$config->full_root_path}/chatroom/{$chat_slug}'>
            <span class='fa {$this_module->language->icon}'></span>
            {$this_module->language->single->menu}
        </a>
        ",
        800
    );
    
    $config->globals["@chatrooms:collection_for_submenu"] = null;
}
else
{
    $template->add_menu_item(
        trim($this_module->language->menu_caption),
        "
        <span class='main_menu_item is_submenu_trigger' data-module='chatrooms' data-submenu='#chatrooms_menu_list'
              style='cursor: pointer'>
            <span class='fa {$this_module->language->icon}'></span>
            {$this_module->language->menu_caption}
        </span>
        ",
        800
    );
    
    $config->globals["@chatrooms:collection_for_submenu"] = $chatrooms;
}
