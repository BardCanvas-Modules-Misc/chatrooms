<?php
/**
 * Chatroom messages
 *
 * @package    BardCanvas
 * @subpackage chatrooms
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */
?>

<div id="chatroom_messages" style="display: none">
    
    <div class="chat_critical_error"><?= unindent($this_module->language->messages->critical_error) ?></div>
    <div class="chat_response_data_undefined"><?= unindent($this_module->language->messages->response_data_undefined) ?></div>
    <div class="chat_response_data_invalid"><?= unindent($this_module->language->messages->response_data_invalid) ?></div>
    
    <template class="chat_body">
        
        <div class="messages"></div>
        <div class="input">
            <textarea class="expandible_textarea" placeholder="<?= $this_module->language->input_placeholder ?>"></textarea>
            <div class="buttons">
                <i class="fa send-button fa-paper-plane fa-lg pseudo_link" onclick="chatroom.__sendMessage()"></i>
            </div>
        </div>
        
    </template>
    
    <template class="chat_message">
        
        <div class="chat-message {{class}}">
            <div class="sent"><span class="time">{{time}}</span></div>
            <div class="arrow left"><span class="tip"></span></div>
            <div class="message">
                <p class="author" data-user-id="{{id_sender}}">
                    {{#if sender_avatar}}
                        <span class="avatar">
                            <img src="{{sender_avatar}}">
                        </span>
                    {{/if}}
                    <span class="user_display_name" data-user-level="{{sender_level}}">
                        {{sender_display_name}}<br>
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
