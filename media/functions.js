
var chatroom = {
    
    script         : '',
    updateInterval : 2 * 1000,
    timeout        : 4 * 1000,
    
    /**
     * @type {jQuery}
     */
    $container : null,
    
    /**
     * @type {jQuery}
     */
    $messages: null,
    
    params : {},
    
    __compiledTemplates: {},
    
    __started       : false,
    __intervalIndex : null,
    __$xhr          : null,
    __running       : false,
    
    __accountId     : 0,
    __accountLevel  : 0,
    
    __sendMessageScript : '',
    
    __initialized : false,
    __firstRun    : true,
    __title       : '',
    __minLevel    : 0,
    
    __setLoaded: function()
    {
        this.$container.attr('data-loaded', true);
    },
    
    __getMessage: function(selector, asText)
    {
        var $message = chatroom.$messages.find('.' + selector);
        
        if( $message.length === 0 )
        {
            console.warn('Internal message "%s" not found.', selector);
            
            return '';
        }
        
        if( asText ) return $message.text().trim();
        
        return $message.html().trim();
    },
    
    __getTemplate: function(templateClass, context)
    {
        if( typeof chatroom.__compiledTemplates[templateClass] !== 'undefined' )
            return chatroom.__compiledTemplates[templateClass](context);
        
        var template         = chatroom.__getMessage(templateClass);
        var compiledTemplate = Template7.compile(template);
        
        chatroom.__compiledTemplates[templateClass] = compiledTemplate;
        
        return compiledTemplate(context);
    },
    
    __post: function( url, params, success, error, timeout )
    {
        if( success === null ) success = function() {};
        
        if( typeof params === 'undefined' || params === null ) params = {};
        params.wasuuup = wasuuup();
        
        /** @type {jQuery} $xhr */
        var $xhr = $.post(url, params, success);
        
        if( error !== null ) $xhr.fail(error);
        
        if( typeof timeout == 'number' ) $xhr.timeout = timeout;
        
        $xhr.url = url;
        
        return $xhr;
    },
    
    __getJSON: function(url, params, success, error, timeout)
    {
        if( success === null ) success = function() {};
        
        if( typeof params === 'undefined' || params === null ) params = {};
        
        /** @type {jQuery} $xhr */
        var $xhr = $.getJSON(url, params, success);
        
        if( error !== null ) $xhr.fail(error);
        
        if( typeof timeout == 'number' ) $xhr.timeout = timeout;
        
        $xhr.url = url;
        
        return $xhr;
    },
    
    __addWarning: function(text)
    {
        var $container = chatroom.$container.find('.target .messages');
        
        $container.append(
            '<div class="framed_content state_ko aligncenter">' +
            '<i class="fa fa-warning"></i> ' + text +
            '</div>'
        );
        
        ion.sound.play("computer_error");
        $container.scrollTo('max');
    },
    
    __addInfo: function(text)
    {
        var $container = chatroom.$container.find('.target .messages');
        
        $container.append(
            '<div class="framed_content state_highlight aligncenter">' +
            '<i class="fa fa-info-circle"></i> ' + text +
            '</div>'
        );
        
        ion.sound.play("pop_cork");
        $container.scrollTo('max');
    },
    
    init: function($container, $messages)
    {
        chatroom.$container          = $container;
        chatroom.$messages           = $messages;
        chatroom.script              = $_CHATROOM_SCRIPT;
        chatroom.__accountId         = $_CURRENT_USER_ID_ACCOUNT;
        chatroom.__accountLevel      = parseInt($_CURRENT_USER_LEVEL);
        chatroom.__sendMessageScript = $_CHATROOM_SENDER;
        
        var params = $container.attr('data-params');
        if( params ) chatroom.params = JSON.parse(params);
        
        chatroom.__init_helpers();
        
        ion.sound({
            sounds: [{name: "pop_cork"}, {name: 'computer_error'}],
            volume:  1,
            path:    $_FULL_ROOT_PATH + "/lib/ion.sound-3.0.7/sounds/",
            preload: true
        });
        
        $(document).keyup(function(e)
        {
            if( ! $('body').hasClass('popup') ) return;
            
            if( e.key === "Escape" ) hide_dropdown_menus();
        });
        
        chatroom.start();
    },
    
    __init_helpers: function()
    {
        $('#chatroom_ban_form').ajaxForm({
            target: '#chatroom_ban_target',
            beforeSubmit: function(formData, $form, options)
            {
                $form.closest('.ui-dialog').block(blockUI_medium_params);
            },
            success: function(responseText, statusText, xhr, $form)
            {
                if( responseText !== 'OK' )
                {
                    $form.closest('.ui-dialog').unblock();
                    alert(responseText);
                    
                    return;
                }
                
                $('#chatroom_ban_dialog').dialog('close');
            }
        });
        
        var height = $(window).height() - 20;
        if( height > 400 ) height = 380;
        
        var width = $(window).width() - 20;
        if( width > 340 ) width = 320;
        
        var $dialog = $('#chatroom_ban_dialog');
        $dialog.dialog({
            autoOpen: false,
            modal: true,
            width: width,
            height: height,
            buttons: [
                {
                    text:  $dialog.attr('data-ok-caption'),
                    icons: { primary: "ui-icon-check" },
                    click: function() { $('#chatroom_ban_form').submit(); }
                }, {
                    text:  $dialog.attr('data-cancel-caption'),
                    icons: { primary: "ui-icon-cancel" },
                    click: function() { $(this).dialog('close'); }
                }
            ]
        });
    },
    
    afterInit: function()
    {
        if( chatroom.__initialized ) return;
        
        chatroom.title        = chatroom.params.title;
        chatroom.params.title = '';
        chatroom.params.since = '';
        
        if( chatroom.params.min_level ) chatroom.__minLevel = parseInt(chatroom.params.min_level);
        
        chatroom.$container.find('.target').html( chatroom.__getTemplate('chat_body', {title: chatroom.title}) );
        
        if( chatroom.__accountId === '' )
        {
            chatroom.$container.find('.target .messages').append(
                chatroom.__getTemplate('chat_only_for_members', {})
            );
            
            chatroom.__initialized  = true;
            chatroom.updateInterval = 0;
            chatroom.stop();
            chatroom.$container.attr('data-loaded', 'true');
            chatroom.$container.find('.input').remove();
            
            return;
        }
        
        if( chatroom.__accountLevel < chatroom.__minLevel )
        {
            console.log(
                '%cAccess to chat "%s" disabled. User level %s (required: %s)',
                'color: orangered',
                chatroom.title,
                chatroom.__accountLevel,
                chatroom.__minLevel
            );
            
            chatroom.$container.find('.target .messages').append(
                chatroom.__getTemplate('chat_level_unmet', {})
            );
            
            chatroom.__initialized  = true;
            chatroom.updateInterval = 0;
            chatroom.stop();
            chatroom.$container.attr('data-loaded', 'true');
            chatroom.$container.find('.input').remove();
            
            return;
        }
        
        chatroom.$container.find('.target .input textarea')
            .keyup(function(e) {
                chatroom.__readjust();
            })
            .change(function(e) {
                chatroom.__readjust();
            })
            .keypress(function(e) {
                if( e.keyCode === 13 && ! e.shiftKey ) {
                    e.preventDefault();
                    chatroom.__sendMessage();
                }
            });
        
        chatroom.$container.find('.expandible_textarea').expandingTextArea();
        chatroom.__initialized = true;
    },
    
    start: function()
    {
        chatroom.__started = true;
    
        if( chatroom.updateInterval > 0 )
            chatroom.__intervalIndex = setInterval(function() { chatroom.run(); }, chatroom.updateInterval);
        
        console.log('Chatroom started on %s', chatroom.$container.get(0));
        
        chatroom.afterInit();
    },
    
    stop: function()
    {
        if( ! chatroom.__started ) return;
        
        chatroom.abort();
        
        if( chatroom.__intervalIndex )
            clearInterval( chatroom.__intervalIndex );
        
        chatroom.__started = false;
        
        console.log('Chat stopped on %s', chatroom.$container.get(0));
    },
    
    abort: function()
    {
        if( chatroom.__$xhr )
        {
            chatroom.__$xhr.abort();
            chatroom.__$xhr = null;
            console.log('Chat AJAX call aborted on %s', chatroom.$container.get(0));
        }
        
        chatroom.__running = false;
        console.log('Chat aborted on %s', chatroom.$container.get(0));
    },
    
    run: function()
    {
        if( ! chatroom.__started )
        {
            chatroom.start();
            
            return;
        }
        
        if( chatroom.__running ) return;
        
        var url    = chatroom.script;
        var params = chatroom.params;
        
        chatroom.__running = true;
        chatroom.__$xhr    = null;
        
        chatroom.__$xhr = chatroom.__getJSON(
            url, params,
            function(response) {
                chatroom.__setLoaded();
                chatroom.__running = false;
                chatroom.success(response);
                chatroom.__$xhr = null;
            },
            function($xhr, textStatus, errorThrown) {
                chatroom.__running = false;
                chatroom.runFailed($xhr, textStatus, errorThrown);
            },
            chatroom.timeout
        );
    },
    
    success: function(response)
    {
        var $container = chatroom.$container.find('.target .messages');
        
        if( ! chatroom.__validateResponse(response) ) return;
        
        if( chatroom.__firstRun && response.data.length === 0 )
        {
            $container.append( chatroom.__getTemplate('chat_empty_message', {}) );
            chatroom.__firstRun = false;
            
            return;
        }
        
        if( typeof response.meta.warns === "object" )
        {
            for(var w in response.meta.warns)
                chatroom.__addWarning(response.meta.warns[w]);
                
            if( response.meta.suspend_ops )
            {
                chatroom.stop();
                chatroom.$container.find('.input textarea')
                        .prop('disabled', true)
                        .attr('placeholder', chatroom.__getMessage('chat_disabled', true));
                chatroom.$container.find('.input .buttons .fa')
                        .addClass('disabled')
                        .attr('onclick', null);
                
                return;
            }
        }
        
        if( response.data.length > 0 )
            $container.find('.empty-chat').fadeOut('fast', function() { $(this).remove(); });
        
        for(var i in response.data) $container.append(chatroom.__forgeMessageMarkup(response.data[i], $container));
        
        if( response.meta.last_message_timestamp !== '' )
            chatroom.params.since = response.meta.last_message_timestamp;
        
        if( response.data.length > 0 ) ion.sound.play("pop_cork");
        if( response.data.length > 0 ) $container.scrollTo('max');
        if( response.data.length > 0 ) prepare_submenus();
        chatroom.__firstRun = false;
    },
    
    __validateResponse: function(response)
    {
        if( typeof response !== 'object' )
        {
            chatroom.__addWarning(sprintf(chatroom.__getMessage('chat_critical_error'), response));
            
            return false;
        }
        
        if( response.message !== 'OK' )
        {
            chatroom.__addWarning(sprintf(chatroom.__getMessage('chat_critical_error'), response.message));
            
            return false;
        }
        
        if( typeof response.data === 'undefined' )
        {
            chatroom.__addWarning(sprintf(
                chatroom.__getMessage('chat_critical_error'), chatroom.__getMessage('chat_response_data_undefined')
            ));
            
            return false;
        }
        
        if( typeof response.data !== 'object' )
        {
            chatroom.__addWarning(sprintf(
                chatroom.__getMessage('chat_critical_error'), chatroom.__getMessage('chat_response_data_invalid')
            ));
            
            return false;
        }
        
        return true;
    },
    
    /**
     * @private
     * @param {{object}} item
     * @param {{jQuery}} $container
     * 
     * @return {{jQuery}}
     */
    __forgeMessageMarkup: function(item, $container)
    {
        var time              = new Date(item.sent);
        item.time             = sprintf('%02f', time.getHours()) + ':' + sprintf('%02f', time.getMinutes());
        item.class            = item.id_sender === chatroom.__accountId ? 'outgoing' : 'incoming';
        item.message          = item.contents;
        item.show_mod_actions = ($_CURRENT_USER_IS_MOD && parseInt(item.sender_level) < 200);
        
        if( parseInt(item.id_sender) === 0 )
            return $(
                '<div class="framed_content state_highlight aligncenter">' +
                '<i class="fa fa-info-circle"></i> ' + '[' + item.time + '] ' + item.contents +
                '</div>'
            );
        
        var $item = $( chatroom.__getTemplate('chat_message', item) );
        $item.find('img').load(function() { $container.scrollTo('max'); });
        
        return $item;
    },
    
    runFailed: function($xhr, textStatus, errorThrown)
    {
        if( parseInt($xhr.status) ===   0 ) return;
        if( parseInt($xhr.status) === 200 ) return;
        
        if( parseInt($xhr.status) === 401 )
        {
            chatroom.updateInterval = 0;
            chatroom.stop();
            
            return;
        }
        
        var contents = sprintf(
            'Cannot fetch messages for %s!<br>Remote response:<br><span class="critical">%s %s</span>',
            chatroom.title, $xhr.status, $xhr.statusText
        );
        
        chatroom.__addWarning(contents);
    },
    
    __readjust: function()
    {
        var $messages = chatroom.$container.find('.target .messages');
        var $input    = chatroom.$container.find('.target .input');
        
        if( typeof $input.attr('data-height') === 'undefined' )
            $input.attr('data-height', 0);
        
        var previousHeight = parseInt($input.attr('data-height'));
        var currentHeight  = $input.height() + 2;
        
        if( currentHeight !== previousHeight )
        {
            $messages.css('height', 'calc(100% - ' + currentHeight + 'px)');
            $messages.css('margin-bottom',           currentHeight + 'px');
            $input.attr('data-height', currentHeight);
        }
    },
    
    __sendMessage: function()
    {
        var $textarea   = chatroom.$container.find('.target .input textarea');
        var postMessage = $textarea.val().trim();
        
        if( postMessage === '' ) return;
    
        postMessage = postMessage.replace(/\r\n/g, '<br>').replace(/\n/g, '<br>');
        
        var params = {
            chat:    chatroom.params.chat,
            message: postMessage
        };
        
        var success = function(response) {
            
            var message;
            
            if( typeof response !== 'object' )
            {
                message = sprintf(
                    'Cannot post message to the chat!<br>Remote response:<br><span class="critical">%s</span>',
                    response
                );
                chatroom.__addWarning(message);
                
                $textarea.closest('.input').unblock();
                
                return;
            }
            
            if( response.message !== 'OK' )
            {
                message = sprintf(
                    'Cannot post message to the chat!<br>Remote response:<br><span class="critical">%s</span>',
                    response.message
                );
                chatroom.__addWarning(message);
                
                $textarea.closest('.input').unblock();
                
                return;
            }
            
            $textarea.val('');
            $textarea.closest('.input').unblock();
            chatroom.run();
        };
        
        var error = function($xhr, textStatus, errorThrown) {
            
            if( parseInt($xhr.status) ===   0 ) return;
            if( parseInt($xhr.status) === 200 ) return;
            
            var contents = sprintf(
                'Cannot post message to the chat!<br>Remote response:<br><span class="critical">%s %s</span>',
                $xhr.status, $xhr.statusText
            );
            
            chatroom.__addWarning(contents);
            $textarea.closest('.input').unblock();
        };
        
        $textarea.closest('.input').block(blockUI_smallest_params);
        chatroom.abort();
        chatroom.__post( chatroom.__sendMessageScript, params, success, error, chatroom.timeout );
    },
    
    __sendImage: function()
    {
        var $form  = $('#chatroom_image_submitter');
        var $chat  = $form.find('input[name="chat"]');
        var $input = $form.find('input[name="image"]');
        
        if( $form.attr('data-initialized') !== 'true' )
        {
            $form.ajaxForm({
                target: '#chatroom_image_target',
                beforeSubmit: function(formData, $form, options)
                {
                    chatroom.$container.find('.target .input').block(blockUI_smallest_params);
                },
                success: function(responseText, statusText, xhr, $form)
                {
                    chatroom.$container.find('.target .input').unblock();
                    
                    if( responseText !== 'OK' )
                        chatroom.__addWarning(responseText);
                }
            });
        }
        
        $form[0].reset();
        $chat.val( chatroom.params.chat );
        $input.click();
    },
    
    __prepareActionsMenu: function(trigger)
    {
        var $trigger      = $(trigger);
        var $author       = $trigger.closest('.message').find('.author');
        var author_id     = $author.attr('data-user-id');
        var author_lvl    = parseInt($author.attr('data-user-level'));
        var author_uname  = $author.attr('data-user-name');
        var author_banned = $author.attr('data-is-banned') === 'true';
        
        var $submenu = $( $trigger.attr('data-submenu') );
        $submenu.attr('data-user-id',    author_id);
        $submenu.attr('data-user-level', author_lvl);
        $submenu.attr('data-user-name',  author_uname);
        $submenu.find('.action').show();
        
        if( $_CURRENT_USER_IS_MOD )
        {
            $submenu.find('.action[data-action="report"]').hide();
            
            if( author_lvl >= 200 || author_banned )
            {
                $submenu.find('.action[data-action="kick"]').hide();
                $submenu.find('.action[data-action="open_ban_dialog"]').hide();
            }
            
            if( ! author_banned ) $submenu.find('.action[data-action="unban"]').hide();
        }
        else
        {
            if( author_lvl >= 200 || author_banned )
                $submenu.find('.action[data-action="report"]').hide();
            
            $submenu.find('.action[data-action="kick"]').hide();
            $submenu.find('.action[data-action="open_ban_dialog"]').hide();
            $submenu.find('.action[data-action="unban"]').hide();
        }
    },
    
    __execAction: function(trigger)
    {
        var $trigger     = $(trigger);
        var action       = $trigger.attr('data-action');
        var $submenu     = $trigger.closest('.dropdown_menu');
        var author_id    = $submenu.attr('data-user-id');
        var author_lvl   = $submenu.attr('data-user-level');
        var author_uname = $submenu.attr('data-user-name');
        
        if( action === 'view_profile' )
        {
            window.open($_FULL_ROOT_PATH + '/user/' + author_uname);
            
            return;            
        }
        
        if( action === 'open_ban_dialog' )
        {
            var $form = $('#chatroom_ban_form');
            $form[0].reset();
            $form.find('input[name="chat"]').val( chatroom.params.chat );
            $form.find('input[name="user_id"]').val( author_id );
            $form.find('label.state_active').removeClass('state_active');
            $('#chatroom_ban_dialog').dialog('open');
            
            return;
        }
        
        if( action === 'unban' )
        {
            if( ! confirm($_GENERIC_CONFIRMATION) ) return;
        }
        else
        {
            var message = chatroom.__getMessage( action === 'kick' ? 'chat_kick_prompt' : 'chat_report_prompt' );
            var reason  = prompt(message);
            if( ! reason ) return;
        }
        
        var params = {
            action:  action,
            chat:    chatroom.params.chat,
            user_id: author_id,
            reason:  reason
        };
        $.blockUI(blockUI_default_params);
        $.post($_CHATROOM_TOOLBOX, params, function(response)
        {
            $.unblockUI();
            if( response !== 'OK' )
            {
                chatroom.__addWarning(response);
                
                return;
            }
            
            if( action === 'report' )
                chatroom.__addInfo( chatroom.__getMessage('chat_user_reported') );
        });
    },
};

$(document).ready(function() { chatroom.init( $('#chatroom'), $('#chatroom_messages') ); });
