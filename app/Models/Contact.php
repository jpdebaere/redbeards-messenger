<?php
/*
 *
 * Details:
 * PHP Messenger.
 *
 * Modified: 07-Dec-2016
 * Made Date: 06-Dec-2016
 * Author: Hosvir
 *
 */
namespace Messenger\Models;

use Messenger\Core\Functions;
use Messenger\Core\Database;

class Contact
{
    public $contact_guid = null;
    public $alias = null;
    public $username = null;
    public $made_date = null;

    public function __construct($contact_guid = null, $alias = null, $username = null, $made_date = null)
    {
        $this->contact_guid = $contact_guid;
        $this->alias = $alias;
        $this->username = $username;
        $this->made_date = $made_date;
    }

    public function getMadeDate()
    {
        return Functions::convertTime($this->made_date, true);
    }

    public function setAlias($alias)
    {
        $alias = Functions::cleanInput($alias);

        //Check for errors
        if (strlen($alias) > 63) {
            return 1;
        }

        //Continue
        if (!isset($error)) {
            if (Database::update(
                "UPDATE contacts SET contact_alias = ? WHERE contact_guid = ? AND user_guid = ?;",
                array(
                    $alias,
                    $this->contact_guid,
                    $_SESSION[USESSION]->user_guid
                )
            )) {
                return 0;
            } else {
                return 2;
            }
        }
    }

    public function delete($guid)
    {
        //Delete messages
        Database::update(
            "DELETE FROM messages WHERE (user1_guid = ? OR user1_guid = ?) AND (user2_guid = ? OR user2_guid = ?);",
            [
                $_SESSION[USESSION]->user_guid,
                $guid,
                $_SESSION[USESSION]->user_guid,
                $guid
            ]
        );
        
        //Delete conversations
        Database::update(
            "DELETE FROM conversations WHERE (user_guid = ? OR user_guid = ?) AND (contact_guid = ? OR contact_guid = ?);",
            [
                $_SESSION[USESSION]->user_guid,
                $guid,
                $_SESSION[USESSION]->user_guid,
                $guid
            ]
        );

        //Delete contacts
        Database::update(
            "DELETE FROM contacts WHERE (user_guid = ? OR user_guid = ?) AND (contact_guid = ? OR contact_guid = ?);",
            [
                $_SESSION[USESSION]->user_guid,
                $guid,
                $_SESSION[USESSION]->user_guid,
                $guid
            ]
        );
    }

    /*
     *
     * Get contacts.
     *
     */
    public function getContacts()
    {
        $contacts = [];
        
        $contact_data = Database::select(
            "SELECT contact_guid, contact_alias, made_date,
                (SELECT username FROM users WHERE user_guid = contact_guid) AS username
                FROM contacts
                WHERE user_guid = ?;",
            [$_SESSION[USESSION]->user_guid]
        );


        foreach ($contact_data as $contact) {
            array_push(
                $contacts,
                new Contact(
                    $contact['contact_guid'],
                    $contact['contact_alias'],
                    $contact['username'],
                    $contact['made_date']
                )
            );
        }

        return $contacts;
    }

    /**
     *
     * Get contact by guid.
     *
     * */
    public function getByGuid($guid)
    {
        $contact = null;

        $contact_data = Database::select(
            "SELECT contact_guid, contact_alias, made_date, 
                (SELECT username FROM users WHERE user_guid = contact_guid) AS username
                FROM contacts
                WHERE contact_guid = ? 
                AND user_guid = ?;",
            [$guid, $_SESSION[USESSION]->user_guid]
        );

        if (count($contact_data) > 0) {
            $contact = new Contact(
                $contact_data[0]['contact_guid'],
                htmlspecialchars($contact_data[0]['contact_alias']),
                htmlspecialchars($contact_data[0]['username']),
                $contact_data[0]['made_date']
            );
        }

        return $contact;
    }
}
