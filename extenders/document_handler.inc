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

use hng2_base\account;
use hng2_base\device;
use hng2_base\template;
use hng2_modules\rauth_client\server;
use hng2_modules\rauth_server\toolbox;

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

#region Exported chat redirect
$raw_imports = $settings->get("modules:chatrooms.imported_chats");
if( ! empty($raw_imports) )
{
    if( ! $modules["rauth_client"]->enabled ) die($current_module->language->messages->rauth_client_disabled);
    
    $imported_chats = array();
    foreach( explode("\n", $raw_imports) as $line )
    {
        $line = trim($line);
        if( empty($line) ) continue;
        if( substr($line, 0, 1) == "#" ) continue;
        
        $imported_chats[] = $line;
    }
    
    if( ! empty($imported_chats) && in_array($current_chat->name, $imported_chats) )
    {
        try
        {
            $server = new server();
        }
        catch(\Exception $e)
        {
            die(sprintf($this_module->language->messages->rauth_client_init_error, $e->getMessage()));
        }
        
        $account_is_remote = true;
        try
        {
            $res = $server->get_account($account->user_name, $account->password);
        }
        catch(\Exception $e)
        {
            $account_is_remote = false;
        }
        
        if( $account_is_remote )
        {
            $token = urlencode(three_layer_encrypt(
                $account->id_account . "\t" . (time() + (10 * 60)),
                $server->auth_server_encryption_key1,
                $server->auth_server_encryption_key2,
                $server->auth_server_encryption_key3
            ));
        }
        elseif( $server->is_local_account_mapped_remotely($account) )
        {
            $token = urlencode($server->tokenize_mapped_account_id($account, 10));
        }
        else
        {
            try
            {
                $xaccount = $server->map_local_account($account);
            }
            catch(\Exception $e)
            {
                die(sprintf(
                    $this_module->language->messages->rauth_mapping_error, $server->auth_server_title, $e->getMessage()
                ));
            }
            
            $token = urlencode($server->tokenize_mapped_account_id($xaccount, 10));
        }
        
        $link = "{$server->auth_server_url}/chatroom/" . wp_sanitize_filename($current_chat->name) . "?wsh={$server->auth_website_handle}&token={$token}";
        header("Location: $link");
        die("<html><head><title>{$language->click_here_to_continue}</title></head><body><a href='$link'>{$language->click_here_to_continue}</a></body></html>");
    }
}
#endregion

#region Rauth Server support
$wshandler = trim(stripslashes($_GET["wsh"]));
if( ! empty($wshandler) )
{
    if( ! $modules["rauth_server"]->enabled ) die($current_module->language->messages->rauth_server_disabled);
    
    $wstoken = trim(stripslashes($_GET["token"]));
    if( empty($wstoken) ) die($current_module->language->messages->rauth_token_missing);
    
    try
    {
        $ra_toolbox = new toolbox();
        $wsdata     = $ra_toolbox->init_website($_GET["wsh"], false);
    }
    catch(\Exception $e)
    {
        die(sprintf($current_module->language->messages->rauth_server_init_fail, $e->getMessage()));
    }
    
    $token = three_layer_decrypt(
        $wstoken, $wsdata["encryption_key1"], $wsdata["encryption_key2"], $wsdata["encryption_key3"]
    );
    if( ! preg_match('/[0-9]+,[0-9]+/', $token) ) die($current_module->language->messages->rauth_invalid_token);
    list($id_account, $token_ttl) = explode(",", $token);
    
    if( ! is_numeric($id_account) && ! is_numeric($token_ttl) )  die($current_module->language->messages->rauth_invalid_token2);
    if( time() > $token_ttl ) die($current_module->language->messages->rauth_token_expired);
    
    $account = new account($id_account);
    if( ! $account->_exists ) die($current_module->language->messages->rauth_account_not_found);
    if( $account->state != "enabled" ) die($current_module->language->messages->rauth_account_not_enabled);
    
    $device = new device($account->id_account);
    if( ! $device->_exists )
    {
        $device->set_new($account);
        $device->state = "enabled";
        $device->save();
    }
    else
    {
        $device->ping();
    }
    
    $account->open_session($device);
}
#endregion

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
