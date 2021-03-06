<?php
/**
 * Single chatroom
 *
 * @package    BardCanvas
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * Trailing vars:
 * @var module $current_module self
 * @var object $current_chat {name:string, title:string, level:string}
 */

use hng2_base\module;

$this_module = $current_module;
?>

<script type="text/javascript" src="<?=$config->full_root_path?>/lib/jquery.exptextarea.js"></script>

<script type="text/javascript" src="<?= $this_module->get_url() ?>/lib/template7-1.4.0/template7.min.js"></script>

<link rel="stylesheet" type="text/css" href="<?= $this_module->get_url() ?>/media/styles~v<?= time() ?>.css">

<? if( $_GET["embedded"] == "true" ): ?>
    <style type="text/css">
        body, body.popup { padding: 0; background-color: white !important; }
        #body_wrapper { background-color: white !important; }
    </style>
<? endif; ?>

<style type="text/css">
    body { overflow: hidden; }
</style>

<script type="text/javascript">
    $_CHATROOM_SCRIPT  = '<?= $this_module->get_url() ?>/api/get_messages.php';
    $_CHATROOM_SENDER  = '<?= $this_module->get_url() ?>/api/post_message.php';
    $_CHATROOM_TOOLBOX = '<?= $this_module->get_url() ?>/api/toolbox.php';
</script>
<script type="text/javascript" src="<?= $this_module->get_url() ?>/media/functions~v<?= time() ?>.js"></script>

<div id="chatroom" class="chatroom" data-chatroom="<?= $current_chat->name ?>"
     data-params='{"chat": "<?= $current_chat->name ?>"}'
     data-hide-self-details="true">
    <div class="chat_title aligncenter">
        <? if( $_GET["embedded"] == "true" ): ?>
            <a class="pull-right" href="<?= $config->full_root_path ?>/chatroom/<?= wp_sanitize_filename($current_chat->name) ?>"
               target="_blank" style="color: white !important;"><i class="fa fa-window-maximize fa-fw"></i></a>
        <? endif; ?>
        
        <?= $settings->get("engine.website_name") ?>
        
        <span class="user_display_name" data-user-level="<?= $account->level ?>">
            &nbsp;[<i class="fa fa-user"></i>
            <?= $account->get_processed_display_name() ?>]&nbsp;
        </span>
        
        <?= $current_chat->name ?>
        (<?= $current_chat->title ?>)
    </div>
    <div class="target"></div>
</div>

<?
include "{$this_module->abspath}/contents/controller_templates.inc";
