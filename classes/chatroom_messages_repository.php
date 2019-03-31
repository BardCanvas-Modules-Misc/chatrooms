<?php
namespace hng2_modules\chatrooms;

use hng2_repository\abstract_repository;

class chatroom_messages_repository extends abstract_repository
{
    protected $row_class       = 'hng2_modules\chatrooms\chatroom_message_record';
    protected $table_name      = 'chatroom_messages';
    protected $key_column_name = 'message_id';
    
    protected $additional_select_fields = array(
        "( select concat(a.user_name, '\t', a.display_name, '\t', a.level, '\t', a.avatar, '\t', a.email) 
           from account a where a.id_account = chatroom_messages.id_sender
           ) as __sender_data"
    );
    
    /**
     * @param $id
     *
     * @return chatroom_message_record|null
     * @throws \Exception
     */
    public function get($id)
    {
        return parent::get($id);
    }
    
    /**
     * @param array  $where
     * @param int    $limit
     * @param int    $offset
     * @param string $order
     *
     * @return chatroom_message_record[]
     * @throws \Exception
     */
    public function find($where, $limit, $offset, $order)
    {
        /** @noinspection PhpParamsInspection */
        return $this->process_rows(parent::find($where, $limit, $offset, $order));
    }
    
    /**
     * @param chatroom_message_record $record
     *
     * @return int
     * @throws \Exception
     */
    public function save($record)
    {
        global $database;
        
        $this->validate_record($record);
        
        $clone = $record->get_for_database_insertion();
        
        return $database->exec("
            insert ignore into {$this->table_name} set
            chat_name  = '{$clone->chat_name}',
            id_sender  = '{$clone->id_sender}',
            contents   = '{$clone->contents}',
            sent       = '{$clone->sent}'
        ");
    }
    
    /**
     * @param chatroom_message_record $record
     *
     * @throws \Exception
     */
    public function validate_record($record)
    {
        if( ! $record instanceof chatroom_message_record )
            throw new \Exception(
                "Invalid object class! Expected: {$this->row_class}, received: " . get_class($record)
            );
    }
    
    /**
     * @param chatroom_message_record[] $rows
     * 
     * @return chatroom_message_record[]
     */
    private function process_rows($rows)
    {
        global $config;
        
        if( empty($rows) ) return array();
        
        foreach($rows as &$row)
        {
            if( substr($row->contents, 0, 7) == "@image:" )
            {
                $url = $config->full_root_url
                     . "/data/uploaded_chat_images/"
                     .  str_replace("@image:", "", $row->contents);
                $row->contents = "<img class='image' src='$url'>";
                
                continue;
            }
            
            $row->contents = convert_emojis($row->contents);
        }
        
        reset($rows);
        return $rows;
    }
    
    /**
     * @return array [name => {description, min_level}, ...]
     *
     * @throws \Exception
     */
    public function get_chatrooms_list()
    {
        global $settings;
        
        $raw_chatrooms = $settings->get("modules:chatrooms.chatrooms_registry");
        if( empty($raw_chatrooms) ) return array();
        
        $return = array();
        
        foreach(explode("\n", $raw_chatrooms) as $line)
        {
            $line = trim($line);
            
            if( empty($line) ) continue;
            if(substr($line, 0, 1) == "#" ) continue;
            
            $parts = preg_split('/\s+\|\s+/', $line);
            if( count($parts) != 3 ) continue;
            
            $return[$parts[0]] = (object) array(
                "description" => $parts[1],
                "min_level"   => $parts[2],
            );
        }
        
        return $return;
    }
}
