<?php
namespace hng2_modules\chatrooms;

use hng2_base\accounts_repository;

class accounts_repository_extender extends accounts_repository
{
    
    /**
     * Grabs engine preferences for multiple accounts
     *
     * @param array  $account_ids
     * @param string $pref_name
     *
     * @return array [id_account => value, id_account => value, ...]
     * @throws \Exception
     */
    public function get_multiple_engine_prefs(array $account_ids, $pref_name)
    {
        global $database, $mem_cache;
        
        $key_hash      = md5($pref_name . ":" . implode(",", $account_ids));
        $mem_cache_key = "@chatrooms:accounts_repository/get_multiple_engine_prefs/hash:$key_hash";
        $mem_cache_ttl = 60;
        $cached_value  = $mem_cache->get($mem_cache_key);
        if( is_array($cached_value) ) return $cached_value;
        if( $cached_value == "none" ) return array();
        
        foreach($account_ids as &$id) $id = "'$id'";
        $account_ids = implode(", ", $account_ids);
        
        $res = $database->query("
            select id_account, value from account_engine_prefs where name = '$pref_name'
            and id_account in ($account_ids)
        ");
        
        if( $database->num_rows($res) == 0 )
        {
            $mem_cache->set($mem_cache_key, "none", 0, $mem_cache_ttl);
            
            return array();
        }
        
        $return = array();
        while($row = $database->fetch_object($res))
            $return[$row->id_account] = json_decode($row->value);
        
        $mem_cache->set($mem_cache_key, $return, 0, $mem_cache_ttl);
        
        return $return;
    }
}
