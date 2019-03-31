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

global $settings, $category, $account;

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
