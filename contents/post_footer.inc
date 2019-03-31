<? if( empty($config->globals["@chatrooms:collection_for_submenu"]) ) return; ?>

<!-- chatrooms menu items -->

<div id="chatrooms_menu_list" class="dropdown_menu" style="display: none;">
    
    <? foreach($config->globals["@chatrooms:collection_for_submenu"] as $chat_name => $details):
        $chat_slug = wp_sanitize_filename($chat_name); ?>
        
        <a class="main_menu_item" href="<?= "$config->full_root_path/chatroom/$chat_slug" ?>">
            <i class="fa fa-commenting-o"></i>
            <?= $chat_name ?>
        </a>
        
    <? endforeach; ?>
    
</div>
