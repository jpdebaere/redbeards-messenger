<?php
/**
 * Details:
 * This class is to provide static methods to easily use Public Private 
 * key encryption / decryption.
 * 
 * Modified: 03-Dec-2016
 * Date: 12-Nov-2016
 * Author: Hosvir
 * 
 * */
class PublicPrivateKey {

    /**
     * Generate a public private key pair.
     * NOTE: Supply folder/keyname
     * 
     * @usage: PublicPrivateKey::generateKeyPair("mypublickey","mypriavtekey");
     * 
     * @returns: Boolean
     * */
    public static function generateKeyPair($public_name, $private_name, $return_keys = false, $passphrase = null, $key_bits = 4096)
    {
        $private_key = openssl_pkey_new(
            array(
                'private_key_bits' => $key_bits,  
                'private_key_type' => OPENSSL_KEYTYPE_RSA, 
                'encrypted' => true
            )
        );

        //Save the private key to private.key file. Never share this file with anyone.
        if (!$return_keys) {
            openssl_pkey_export_to_file($private_key, $private_name . ".key", $passphrase);
        } else {
            openssl_pkey_export($private_key, $private_key_out, $passphrase);
        }

        //Generate the public key for the private key
        $a_key = openssl_pkey_get_details($private_key);

        //Save the public key in public.key file. Send this file to anyone who want to send you the encrypted data.
        if (!$return_keys) file_put_contents($public_name . ".pem", $a_key['key']);

        //Free the private Key.
        openssl_free_key($private_key);

        if (!$return_keys) {
            return self::testKeys($public_name, $private_name, $passphrase);
        } else {
            return array($a_key['key'], $private_key_out);
        }
    }

    /**
     * Encrypt the data with the supplied public key.
     * NOTE: Supply folder/keyname
     * 
     * @usage: PublicPrivateKey::encrypt("some data", "mypublickey");
     * 
     * @returns: Encrypted data
     * */
    public static function encrypt($plain_text, $public_name, $pem = null)
    {
        //Compress the data to be sent
        $plain_text = gzcompress($plain_text);

        //Get the public Key of the recipient
        if ($pem == null) {
            $public_key = openssl_pkey_get_public(file_get_contents($public_name . ".pem"));
        } else {
            $public_key = openssl_pkey_get_public($pem);
        }
        $a_key = openssl_pkey_get_details($public_key);

        //Encrypt the data in small chunks and then combine and send it.
        $chunk_size = ceil($a_key['bits'] / 8) - 11;
        $output = "";

        while ($plain_text) {
            $chunk = substr($plain_text, 0, $chunk_size);
            $plain_text = substr($plain_text, $chunk_size);
            $encrypted = "";
            if (!openssl_public_encrypt($chunk, $encrypted, $public_key)) die('Failed to encrypt data.');
            $output .= $encrypted;
        }

        //Free the key
        openssl_free_key($public_key);

        return $output;
    }

    /**
     * Decrypt the data with the supplied private key.
     * NOTE: Supply folder/keyname
     * 
     * @usage: PublicPrivateKey::encrypt("encrypteddata", "myprivatekey");
     * 
     * @returns: Decrypted data
     * */
    public static function decrypt($encrypted, $private_name, $passphrase = null, $key = null)
    {
        if ($key == null) {
            if (!$private_key = openssl_pkey_get_private(file_get_contents($private_name . ".key"), $passphrase)) die('Private Key failed, check your passphrase.');
        } else {
            if (!$private_key = openssl_pkey_get_private($key, $passphrase)) die('Private Key failed, check your passphrase.');
        }
        $a_key = openssl_pkey_get_details($private_key);

        //Decrypt the data in the small chunks
        $chunk_size = ceil($a_key['bits'] / 8);
        $output = "";

        while ($encrypted) {
            $chunk = substr($encrypted, 0, $chunk_size);
            $encrypted = substr($encrypted, $chunk_size);
            $decrypted = "";
            if (!openssl_private_decrypt($chunk, $decrypted, $private_key)) die('Failed to decrypt data.');
            $output .= $decrypted;
        }

        //Free the key
        openssl_free_key($private_key);

        //Uncompress the unencrypted data.
        $output = gzuncompress($output);

        return $output;
    }

    /**
     * Internal test to ensure the keys work.
     * 
     * @returns: Boolean
     * */
    private static function testKeys($public_name, $private_name, $passphrase = null)
    {
        $raw = "Hi there, my name is slim shady.";
        $encrypted = self::encrypt($raw, $public_name);
        $decrypted = self::decrypt($encrypted, $private_name, $passphrase);

        return ($raw == $decrypted);
    }
}