<?php
/**
 * Chatroom messages
 *
 * @package    BardCanvas
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */
?>

<div id="chatroom_image_target" style="display: none"></div>
<form name="chatroom_image_submitter" id="chatroom_image_submitter"
      method="post" enctype="multipart/form-data"
      action="<?= $this_module->get_url() ?>/api/post_image.php"
      data-initialized="false" style="display: none">
    <input type="hidden" name="chat" value="">
    <input type="file" name="image" accept="image/png, image/jpeg, image/gif"
           onchange="$(this).closest('form').submit()">
</form>

<div id="chatroom_actions_menu" class="dropdown_menu" style="display: none"
     data-message-id="" data-user-id="" data-user-name="" data-user-level="">
    
    <span class="action main_menu_item" data-action="delete_this" onclick="chatroom.__execAction(this)"><i 
          class="fa fa-trash-o fa-fw"></i><?= $this_module->language->delete_this_message ?></span>
    
    <span class="action main_menu_item" data-action="purge_24h" onclick="chatroom.__execAction(this)"><i 
          class="fa fa-trash fa-fw"></i><?= $this_module->language->purge_24h ?></span>
    
    <span class="action main_menu_item" data-action="kick" onclick="chatroom.__execAction(this)"><i 
          class="fa fa-gavel fa-fw"></i><?= $this_module->language->kick ?></span>
    
    <span class="action main_menu_item" data-action="open_ban_dialog" onclick="chatroom.__execAction(this)"><i
          class="fa fa-ban fa-fw"></i><?= $this_module->language->ban ?></span>
    
    <span class="action main_menu_item" data-action="unban" onclick="chatroom.__execAction(this)"><i
          class="fa fa-thumbs-up fa-fw"></i><?= $this_module->language->unban ?></span>
    
    <span class="action main_menu_item" data-action="report" onclick="chatroom.__execAction(this)"><i
          class="fa fa-warning fa-fw"></i><?= $this_module->language->report ?></span>
    
    <span class="action main_menu_item" data-action="view_profile" onclick="chatroom.__execAction(this)"><i
          class="fa fa-home fa-fw"></i><?= $this_module->language->view_profile ?></span>
</div>

<div id="chatroom_ban_dialog" style="display: none"
     title="<?= $this_module->language->ban_dialog->title ?>"
     data-ok-caption="<?= $language->ok ?>" data-cancel-caption="<?= $language->cancel ?>">
    
    <div id="chatroom_ban_target" style="display: none"></div>
    
    <form name="chatroom_ban_form" id="chatroom_ban_form" method="post"
          action="<?= $this_module->get_url() ?>/api/toolbox.php">
        <input type="hidden" name="action"  value="ban">
        <input type="hidden" name="chat"    value="">
        <input type="hidden" name="user_id" value="">
        
        <div class="field">
            <div class="caption">
                <?= $this_module->language->ban_dialog->info ?>
            </div>
            <div class="input">
                <textarea name="reason" rows="3"></textarea>
            </div>
        </div>
        <div class="field">
            <?= $this_module->language->ban_dialog->select ?>
        </div>
        <div class="input">
            <? foreach($this_module->language->ban_dialog->options->option as $option): ?>
                <label class="framed_content" style="display: block; margin: 0 0 5px 25px;"
                       onmouseover="$(this).toggleClass('state_hover', true)"
                       onmouseout="$(this).toggleClass('state_hover', false)">
                    <input type="radio" name="window" value="<?= $option["value"] ?>" style="display: none;"
                           onchange="$(this).closest('.input').find('label.state_active').removeClass('state_active'); $(this).closest('label').addClass('state_active');">
                    <?= trim($option) ?>
                </label>
            <? endforeach; ?>
        </div>
    </form>
    
</div>

<div id="chatroom_color_picker" style="display: none"
     title="<?= $this_module->language->colors->dialog_title ?>">
    <div style="display: flex; flex-wrap: wrap; align-items: stretch; justify-content: stretch;">
        <?
        $index = 0;
        if( empty($account->engine_prefs["@chatrooms:default_color"]) )
            $account->engine_prefs["@chatrooms:default_color"] = "";
        foreach($this_module->language->colors->color as $color)
        {
            $selected = $account->engine_prefs["@chatrooms:default_color"] == trim($color["key"]) ? "selected" : "";
            echo "
                <div class='chat_color_picker {$color["key"]} {$selected}' data-color='{$color["key"]}'
                     onclick=\"chatroom.__setColor(this)\">
                    {$color}
                </div>
            ";
        }
        ?>
    </div>
    
</div>

<div id="chatroom_messages" style="display: none">
    
    <div class="chat_critical_error"><?= unindent($this_module->language->messages->critical_error) ?></div>
    <div class="chat_response_data_undefined"><?= unindent($this_module->language->messages->response_data_undefined) ?></div>
    <div class="chat_response_data_invalid"><?= unindent($this_module->language->messages->response_data_invalid) ?></div>
    <div class="chat_report_prompt"><?= $this_module->language->report_prompt ?></div>
    <div class="chat_kick_prompt"><?= $this_module->language->kick_prompt ?></div>
    <div class="chat_user_reported"><?= $this_module->language->messages->user_reported ?></div>
    <div class="chat_disabled"><?= $this_module->language->messages->chat_disabled ?></div>
    
    <template class="welcome_banner"><?= unindent($this_module->language->welcome_banner) ?></template>
    
    <template class="chat_body">
        
        <div class="messages"></div>
        <div class="input">
            <textarea class="expandible_textarea" placeholder="<?= $this_module->language->input_placeholder ?>"></textarea>
            <div class="buttons">
                <i class="fa send-button fa-paper-plane fa-lg pseudo_link" onclick="chatroom.__sendMessage()"></i>
                <i class="fa fa-paint-brush fa-lg pseudo_link" style="margin-left: 5px;" onclick="chatroom.__showColorPicker()"></i>
                <i class="fa fa-image fa-lg pseudo_link" style="margin-left: 5px;" onclick="chatroom.__sendImage()"></i>
            </div>
        </div>
        
    </template>
    
    <template class="chat_message">
        
        <div class="chat-message {{class}}" data-color="{{_color}}">
            <div class="sent"><span class="time">{{time}}</span></div>
            <div class="arrow left"><span class="tip"></span></div>
            <div class="message" data-message-id="{{message_id}}">
                <span class="pull-right pseudo_link fa fa-ellipsis-h fa-fw is_submenu_trigger"
                      data-submenu="#chatroom_actions_menu"
                      onclick="chatroom.__prepareActionsMenu(this)"></span>
                <p class="author" data-user-id="{{id_sender}}" data-user-name="{{sender_user_name}}"
                   data-user-level="{{sender_level}}" data-is-banned="{{_sender_is_banned}}">
                    {{#if sender_avatar}}
                        <span class="avatar">
                            <img src="{{sender_avatar}}">
                        </span>
                    {{/if}}
                    <span class="user_display_name" data-user-level="{{sender_level}}">
                        {{#if _sender_is_banned}}
                            <span class="critical">
                                <i class="fa fa-ban"></i>
                                {{sender_display_name}}
                            </span><br>
                        {{else}}
                            {{sender_display_name}}<br>
                        {{/if}}
                        <span class="author_info">
                            <i class="fa fa-user"></i> {{sender_level_caption}}
                        </span>
                    </span>
                </p>
                <div class="message-body">
                    {{message}}
                </div>
            </div>
            <div class="arrow right"><span class="tip"></span></div>
        </div>
        
    </template>
    
    <template class="chat_empty_message">
        
        <div class="chat-message chat-notice empty-chat framed_content state_highlight aligncenter">
            <div>
                <div>
                    <i class="fa fa-info-circle"></i>
                    <?= $this_module->language->messages->chat_empty ?>
                </div>
            </div>
        </div>
        
    </template>
    
    <template class="chat_only_for_members">
        
        <div class="chat-message chat-notice framed_content state_ko aligncenter">
            <div style="width: 100%">
                <div>
                    <i class="fa fa-info-circle"></i>
                    <?= $this_module->language->messages->for_members_only ?><br>
                    <a class="pseudo_link" style="white-space: normal" onclick="show_login_form()"><i
                       class="fa fa-sign-in"></i> <?= $this_module->language->messages->click_here_to_login ?></a>
                    &nbsp;
                    <a href="<?= $config->full_root_path ?>/accounts/register.php" style="white-space: normal"><i
                       class="fa fa-info-circle"></i> <?= $this_module->language->messages->more_information ?></a>
                </div>
            </div>
        </div>
        
    </template>
    
    <template class="chat_level_unmet">
        
        <div class="chat-message chat-notice framed_content state_ko aligncenter">
            <div style="width: 100%">
                <div>
                    <i class="fa fa-info-circle"></i>
                    <?= $this_module->language->messages->level_unmet ?>
                </div>
            </div>
        </div>
        
    </template>

</div>
