<?php
namespace hng2_modules\chatrooms;

use hng2_repository\abstract_record;

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
    
    public function set_new_id()
    {
        throw new \Exception("Method not implemented");
    }
    
    public function set_from_object($object_or_array)
    {
        if( is_array($object_or_array) ) $object_or_array = (object) $object_or_array;
        
        $this->message_id = $object_or_array->message_id;
        $this->chat_name  = $object_or_array->chat_name;
        $this->id_sender  = $object_or_array->id_sender;
        $this->contents   = $object_or_array->contents;
        $this->sent       = $object_or_array->sent;
        
        if( ! empty($object_or_array->__sender_data) )
        {
            $parts = explode("\t", $object_or_array->__sender_data);
            unset( $object_or_array->__sender_data );
            
            $this->sender_user_name    = $parts[0];
            $this->sender_display_name = $parts[1];
            $this->sender_level        = $parts[2];
        }
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
            $return["sender_level"]
        );
        
        foreach( $return as $key => &$val )
            if( is_string($val) )
                $val = addslashes($val);
        
        return (object) $return;
    }
}