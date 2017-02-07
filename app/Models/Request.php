<?php
/**
 * @author captain-redbeard
 * @since 07/12/16
 */
namespace Redbeard\Models;

use Redbeard\Core\Config;
use Redbeard\Core\Functions;
use Redbeard\Core\Database;

class Request
{
    public $request_guid = null;
    public $request_name = null;
    public $user_guid = null;
    public $expire = null;
    public $expire_time = null;
    public $url = null;
    
    public function __construct(
        $request_guid = null,
        $request_name = null,
        $user_guid = null,
        $expire = null,
        $expire_time = null,
        $url = null
    ) {
        $this->request_guid = $request_guid;
        $this->request_name = $request_name;
        $this->user_guid = $user_guid;
        $this->expire = $expire;
        $this->expire_time = $expire_time;
        $this->url = $url;
    }
    
    public function add($name, $expire)
    {
        $name = Functions::cleanInput($name);
        $expire = Functions::cleanInput($expire, 2);
        
        if (!is_numeric($expire)) {
            return 1;
        }
        
        if ($expire < 1) {
            $expire = 1;
        }
        
        if ($expire > 48) {
            $expire = 48;
        }
        
        $guid = Functions::generateRandomString(6);
        
        if (Database::insert(
            "INSERT INTO contact_requests (request_guid, request_name, user_guid, expire) VALUES (?,?,?,?);",
            [
                $guid,
                $name,
                $_SESSION[Config::get('app.user_session')]->user_guid,
                $expire
            ]
        ) > -1) {
            return Config::get('app.base_href') . "/accept/" . $guid;
        } else {
            return 2;
        }
    }
    
    public function delete($guid)
    {
        $guid = Functions::cleanInput($guid, 2);
        
        if(!Database::update(
            "DELETE FROM contact_requests WHERE request_guid = ? AND user_guid = ?;",
            [
                $guid,
                $_SESSION[Config::get('app.user_session')]->user_guid
            ]
        )) {
            return 10;
        }
        
        return 0;
    }
    
    public function accept($guid)
    {
        $request = Database::select(
            "SELECT request_guid, user_guid FROM contact_requests WHERE request_guid = ?;",
            [$guid]
        );
        
        if (count($request) > 0 && $request[0]['user_guid'] != $_SESSION[Config::get('app.user_session')]->user_guid) {
            if (Database::insert(
                "INSERT INTO contacts (user_guid, contact_guid) VALUES (?,?);",
                [
                    $_SESSION[Config::get('app.user_session')]->user_guid,
                    $request[0]['user_guid']
                ]
            ) > -1) {
                Database::insert(
                    "INSERT INTO contacts (user_guid, contact_guid) VALUES (?,?);",
                    [
                        $request[0]['user_guid'],
                        $_SESSION[Config::get('app.user_session')]->user_guid
                    ]
                );
                
                Database::update(
                    "DELETE FROM contact_requests WHERE request_guid = ?;",
                    [$guid]
                );
                
                unset($_SESSION['request']);
                
                return 0;
            }
        } else {
            if ($request[0]['user_guid'] == $_SESSION[Config::get('app.user_session')]->user_guid) {
                unset($_SESSION['request']);
                return 1;
            } else {
                return 2;
            }
        }
    }
    
    public function getRequests()
    {
        $requests = [];
        
        $request_data = Database::select(
            "SELECT request_guid, request_name, user_guid, expire, 
                DATE_ADD(made_date, INTERVAL expire HOUR) as expire_time
                FROM contact_requests
                WHERE user_guid = ?;",
            [$_SESSION[Config::get('app.user_session')]->user_guid]
        );
        
        foreach ($request_data as $request) {
            $expire_time = Functions::niceTime($request['expire_time']);
            if (Functions::contains('ago', $expire_time)) {
                $expire_time = 'Expired';
            }
            
            array_push(
                $requests,
                new Request(
                    $request['request_guid'],
                    htmlspecialchars($request['request_name']),
                    $request['user_guid'],
                    htmlspecialchars($request['expire']),
                    $expire_time,
                    Config::get('app.base_href') . "/accept/" . $request['request_guid']
                )
            );
        }
        
        return $requests;
    }
}
