<?php
/**
 * @author captain-redbeard
 * @since 09/12/16
 */
use Redbeard\Crew\Config;
use Redbeard\Crew\Database;
use Redbeard\Crew\Utils\Urls;

require_once '../vendor/autoload.php';

//Load config
Config::init();

//Set base url
Config::set('app.base_href', Urls::getUrl());

//Set database config
Database::init(Config::get('database'));

//Set timezone
date_default_timezone_set(Config::get('app.timezone'));

//Get expired users
$users = Database::select(
    "SELECT user_id, user_guid FROM users WHERE expire > 0 AND last_load < (NOW() - INTERVAL expire DAY);",
    []
);

if (count($users) > 0) {
    //For each user
    foreach ($users as $user) {
        //Delete public private key pair
        unlink(Config::get('app.base_dir') . Config::get('keys.public_folder') . $user['user_guid'] . '.pem');
        unlink(Config::get('app.base_dir') . Config::get('keys.private_folder') . $user['user_guid'] . '.key');
        
        //Delete messages
        Database::update(
            "DELETE FROM messages WHERE (user1_guid = ? OR user2_guid = ?);",
            [
                $user['user_guid'],
                $user['user_guid']
            ]
        );
        
        //Delete conversations
        Database::update(
            "DELETE FROM conversations WHERE (contact_guid = ? OR user_guid = ?);",
            [
                $user['user_guid'],
                $user['user_guid']
            ]
        );
        
        //Delete contacts
        Database::update(
            "DELETE FROM contacts WHERE (contact_guid = ? OR user_guid = ?);",
            [
                $user['user_guid'],
                $user['user_guid']
            ]
        );
        
        //Delete user
        Database::update(
            "DELETE FROM users WHERE user_guid = ?;",
            [$user['user_guid']]
        );
    }
}
