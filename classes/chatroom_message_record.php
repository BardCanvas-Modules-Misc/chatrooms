<?php
namespace hng2_modules\chatrooms;

use hng2_repository\abstract_record;
use phpQuery;

class chatroom_message_record extends abstract_record
{
    public $message_id;
    public $chat_name;
    public $id_sender;
    public $contents;
    public $sent;
    
    #####################
    # Dynamically added #
    #####################
    
    public $__sender_data;
    public $sender_user_name;
    public $sender_display_name;
    public $sender_level;
    public $sender_level_caption;
    public $sender_avatar;
    public $sender_email;
    
    public function set_new_id()
    {
        throw new \Exception("Method not implemented");
    }
    
    public function set_from_object($object_or_array)
    {
        global $config;
        
        if( is_array($object_or_array) ) $object_or_array = (object) $object_or_array;
        
        $this->message_id = $object_or_array->message_id;
        $this->chat_name  = $object_or_array->chat_name;
        $this->id_sender  = $object_or_array->id_sender;
        $this->contents   = $object_or_array->contents;
        $this->sent       = $object_or_array->sent;
        
        if( ! empty($object_or_array->__sender_data) )
        {
            $parts = explode("\t", $object_or_array->__sender_data);
            unset( $this->__sender_data );
            
            $this->sender_user_name     = $parts[0];
            $this->sender_display_name  = $parts[1];
            $this->sender_level         = $parts[2];
            $this->sender_level_caption = $config->user_levels_by_level[$this->sender_level];
            $this->sender_avatar        = $parts[3];
            $this->sender_email         = $parts[4];
        }
        
        $this->sender_avatar = $this->get_avatar_url(true);
    }
    
    private function get_avatar_url($fully_qualified = false)
    {
        global $config;
        
        if( $this->sender_avatar == "@gravatar" )
        {
            $return = "https://www.gravatar.com/avatar/" . md5(trim(strtolower($this->sender_email)));
            unset($this->sender_email);
            
            return $return;
        }
        
        unset($this->sender_email);
        
        if( empty($this->sender_avatar) ) return "";
        
        $file = "user/{$this->sender_user_name}/avatar";
        
        if( $fully_qualified ) return "{$config->full_root_url}/{$file}";
        
        return "{$config->full_root_path}/{$file}";
    }
    
    /**
     * @return object
     */
    public function get_for_database_insertion()
    {
        $return = (array) $this;
        
        unset(
            $return["sender_user_name"],
            $return["sender_display_name"],
            $return["sender_level"],
            $return["sender_level_caption"],
            $return["sender_avatar"],
            $return["sender_email"]
        );
        
        $return["contents"] = str_replace(array("<br>", "<br/>", "<br />"), "\n", $return["contents"]);
        
        foreach( $return as $key => &$val )
            if( is_string($val) )
                $val = addslashes($val);
        
        return (object) $return;
    }
    
    public function get_processed_content()
    {
        $contents = $this->contents;
    
        $contents = preg_replace(
            '@\b(https?://([-\w\.]+[-\w])+(:\d+)?(/([\%\w/_\.#-]*(\?\S+)?[^\.\s])?)?)\b@',
            '<a href="$1" target="_blank">$1</a>',
            $contents
        );
        
        $contents = convert_emojis($contents);
        $contents = nl2br($contents);
        
        return $contents;
    }
    
    public function extract_links($content)
    {
        global $config;
        
        $config->globals["@chatrooms:extracted_urls"] = array();
        
        if( ! class_exists('phpQuery') ) include_once(ROOTPATH . "/lib/phpQuery-onefile.php");
        $pq = phpQuery::newDocument($content);
        $pq->find('a')->each(function($element)
        {
            global $config;
            
            $tag = pq($element);
            $src = trim($tag->attr('href'));
            if( empty($src) ) return;
            
            if( preg_match('/^http:|https:/i', $src) )
                $config->globals["@chatrooms:extracted_urls"][] = $src;
        });
        
        $links = $config->globals["@chatrooms:extracted_urls"];
        foreach($links as $key => $link)
            if( stristr($link, "://{$_SERVER["HTTP_HOST"]}") !== false )
                unset($links[$key]);
        reset($links);
        
        return $links;
    }
}
