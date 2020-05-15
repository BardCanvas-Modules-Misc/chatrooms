<?php
/**
 * Chatroom widget on category index
 *
 * @package    BardCanvas
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * @var module $this_module
 */

use hng2_base\module;
use hng2_modules\chatrooms\chatroom_messages_repository;
use hng2_modules\rauth_client\server;

global $settings, $category, $account, $modules;

$raw_category_chats = $settings->get("modules:chatrooms.on_category_chats");
if( empty($raw_category_chats) ) return;

$raw_chats_registry = $settings->get("modules:chatrooms.chatrooms_registry");
if( empty($raw_chats_registry) ) return;

$chats_registry = array();
foreach(explode("\n", $raw_chats_registry) as $line)
{
    $line = trim($line);
    if( empty($line) ) continue;
    if( substr($line, 0, 1) == "#" ) continue;
    
    $parts = explode("|", $line);
    $name  = trim($parts[0]);
    $title = trim($parts[1]);
    $level = trim($parts[2]);
    
    $chats_registry[$name] = (object) array("title" => $title, "level" => $level);
}
if( empty($chats_registry) ) return;

$category_chats = array();
foreach(explode("\n", $raw_category_chats) as $line)
{
    $line = trim($line);
    if( empty($line) ) continue;
    if( substr($line, 0, 1) == "#" ) continue;
    
    $parts = explode("|", $line);
    $slug  = trim($parts[0]);
    $name  = trim($parts[1]);
    
    if( ! isset($chats_registry[$name]) ) continue;
    
    $category_chats[$slug] = $name;
}
if( empty($category_chats) ) return;
if( ! isset($category_chats[$category->slug]) ) return;

$current_chat = $category_chats[$category->slug];
if( $account->level < $chats_registry[$current_chat]->level ) return;

$raw_imports = $settings->get("modules:chatrooms.imported_chats");
if( ! empty($raw_imports) )
{
    $imported_chats = array();
    foreach( explode("\n", $raw_imports) as $line )
    {
        $line = trim($line);
        if( empty($line) ) continue;
        if( substr($line, 0, 1) == "#" ) continue;
        
        $imported_chats[] = $line;
    }
    
    if( ! empty($imported_chats) && in_array($current_chat, $imported_chats) )
    {
        if( ! $modules["rauth_client"]->enabled )
        {
            echo "
                <div class='content_frame framed_content state_ko aligncenter'
                     style='font-size: 1.2em; line-height: normal; padding: 20px;'>
                     <i class='fa fa-warning'></i>
                     {$this_module->language->messages->rauth_client_disabled}
                </div>
            ";
            
            return;
        }
        
        try
        {
            $server = new server();
        }
        catch(\Exception $e)
        {
            $message = sprintf($this_module->language->messages->rauth_client_init_error, $e->getMessage());
            echo "
                <div class='content_frame framed_content state_ko aligncenter'
                     style='font-size: 1.2em; line-height: normal; padding: 20px;'>
                     <i class='fa fa-warning'></i>
                     $message
                </div>
            ";
            
            return;
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
                $account->id_account . "," . (time() + (10 * 60)),
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
                $message = sprintf(
                    $this_module->language->messages->rauth_mapping_error, $server->auth_server_title, $e->getMessage()
                );
                echo "
                    <div class='content_frame framed_content state_ko aligncenter'
                         style='font-size: 1.2em; line-height: normal; padding: 20px;'>
                         <i class='fa fa-warning'></i>
                         $message
                    </div>
                ";
                
                return;
            }
            
            $token = urlencode($server->tokenize_mapped_account_id($xaccount, 10));
        }
        
        $link = "{$server->auth_server_url}/chatroom/" . wp_sanitize_filename($current_chat) . "?wsh={$server->auth_website_handle}&token={$token}&embedded=true";
        ?>
        
        <div class="content_frame" style="padding: 0; background-color: white;">
            <iframe id="embedded_chatroom" src="<?= $link ?>"
                    frameborder="0" width="100%"
                    scrollbars="none" allowtransparency="false"
                    style="height: 40vh; min-height: 200px; background-color: white; margin-bottom: -3px"></iframe>
        </div>
        
        <?
        
        return;
    }
}

if( preg_match("/ios|android|phone|mobile/i", $_SERVER["HTTP_USER_AGENT"]) )
{
    $repository = new chatroom_messages_repository();
    $active     = $repository->get_active_users_list($current_chat);
    $count      = count($active);
    
    if( $count == 0 )
        $message = replace_escaped_objects($this_module->language->invitations->empty, array(
            '{$link}'  => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($current_chat),
            '{$chat}'  => $current_chat,
        ));
    else
        $message = replace_escaped_objects($this_module->language->invitations->filled, array(
            '{$link}'  => "{$config->full_root_path}/chatroom/" . wp_sanitize_filename($current_chat),
            '{$count}' => $count,
            '{$chat}'  => $current_chat,
        ));
    
    echo "
        <div class='content_frame framed_content state_ok aligncenter'
             style='font-size: 1.2em; line-height: normal; padding: 20px;'>
            $message
        </div>
    ";
    
    return;
}
?>

<script type="text/javascript" src="<?= $this_module->get_url() ?>/lib/template7-1.4.0/template7.min.js"></script>

<link rel="stylesheet" type="text/css" href="<?= $this_module->get_url() ?>/media/styles~v<?= time() ?>.css">
<script type="text/javascript">
    $_CHATROOM_SCRIPT  = '<?= $this_module->get_url() ?>/api/get_messages.php';
    $_CHATROOM_SENDER  = '<?= $this_module->get_url() ?>/api/post_message.php';
    $_CHATROOM_TOOLBOX = '<?= $this_module->get_url() ?>/api/toolbox.php';
</script>

<script type="text/javascript" src="<?= $this_module->get_url() ?>/media/functions~v<?= time() ?>.js"></script>

<div id="chatroom" class="content_frame chatroom" data-chatroom="<?= $current_chat ?>"
     data-params='{"chat": "<?= $current_chat ?>"}'
     data-hide-self-details="true"
     style="padding: 0;">
    <div class="chat_title clearfix">
        <a class="detach_icon" href="<?= $config->full_root_path ?>/chatroom/<?= wp_sanitize_filename($current_chat) ?>"
           target="_blank"><i class="fa fa-window-maximize fa-fw"></i></a>
        <?= "{$current_chat} ({$chats_registry[$current_chat]->title})" ?>
    </div>
    <div class="target"></div>
</div>

<?
include "{$this_module->abspath}/contents/controller_templates.inc";
