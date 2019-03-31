
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
        chatroom.$container.find('.target .messages').append(
            '<div class="framed_content state_ko aligncenter">' +
            '<i class="fa fa-warning"></i> ' + text +
            '</div>'
        );
        
        ion.sound.play("computer_error");
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
        
        chatroom.start();
        
        ion.sound({
            sounds: [{name: "pop_cork"}, {name: 'computer_error'}],
            volume:  1,
            path:    $_FULL_ROOT_PATH + "/lib/ion.sound-3.0.7/sounds/",
            preload: true
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
        
        if( response.data.length > 0 )
            $container.find('.empty-chat').fadeOut('fast', function() { $(this).remove(); });
        
        for(var i in response.data)
        {
            var item               = response.data[i];
            var time               = new Date(item.sent);
            item.time              = sprintf('%02f', time.getHours()) + ':' + sprintf('%02f', time.getMinutes());
            item.class             = item.id_sender === chatroom.__accountId ? 'outgoing' : 'incoming';
            item.message           = item.contents;
            
            var $item = $( chatroom.__getTemplate('chat_message', item) );
            $item.find('img').load(function() { $container.scrollTo('max'); });
            
            $container.append( $item );
        }
        
        if( response.meta.last_message_timestamp !== '' )
            chatroom.params.since = response.meta.last_message_timestamp;
        
        if( response.data.length > 0 ) ion.sound.play("pop_cork");
        if( response.data.length > 0 ) $container.scrollTo('max');
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
    }
};

$(document).ready(function() { chatroom.init( $('#chatroom'), $('#chatroom_messages') ); });
