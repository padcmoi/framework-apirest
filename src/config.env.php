<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Class:           Config
 * Description:     Tous les credentials
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         28/06/2020
 *
 * NOTE IMPORTANTE !!! changer tous les champs comportant __A_RENSEIGNER__ ***
 */
class Config
{
    /**
     * Aucune instanciation, prevu pour etre uniquement Static.
     */
    private function __construct()
    {}

    /**
     * Autorise les retours codes d'erreurs HTTP ou non
     */
    public const ALLOW_HTTP_RESPONSE_CODE = [
        '100' => true,

        '200' => true,
        '201' => true,
        '202' => true,
        '203' => true,
        '204' => true,

        '300' => true,
        '302' => true,
        '304' => true,

        '400' => true,
        '401' => false,
        '403' => true,
        '404' => true,

        '500' => true,
        '501' => true,
        '503' => true,
        '510' => true,
    ];

    /**
     * Credentials Base de données MySQL
     * @param prefix - permet de rajouter des caracteres à toutes les tables.
     */
    public static function Database()
    {
        return [
            'hostname' => 'localhost',
            'database' => '__A_RENSEIGNER__',
            'user' => '__A_RENSEIGNER__',
            'password' => '__A_RENSEIGNER__',
            'prefix' => 'aer_',
        ];
    }

    /**
     * @param ALGORITHM - Type de cryptage
     * @param KEY - La clé de hashage de tous les mots de passe stocké en base de données,
     *              attention le fait de changer cette clé
     *              rendra inutilisable les anciens mots de passe stocké en base de données.
     */
    public static function Authentification()
    {
        return [
            'ALGORITHM' => 'sha256',
            'KEY' => '__A_RENSEIGNER__', // exemple: o8JqzLm90qQsuA
        ];
    }

    /**
     * @param KEY -  Clé secrete.
     * @param EXPIRE_TIMESTAMP - Secondes à ajouter pour l'expiration du token.
     * @param TIME_LIMIT_TIMESTAMP - Délai en secondes quand le token est expiré avant qu'il soit refusé.
     */
    public static function Jwt()
    {
        return [
            'KEY' => '__A_RENSEIGNER__', // exemple: fffe2d3f157e4eb2e8edfa702e19010d343607ea243b4fffm5nb1c3t7f17e0ab
            'EXPIRE_TIMESTAMP' => 300,
            'TIME_LIMIT_TIMESTAMP' => 86400,
        ];
    }

    /**
     * Credentials Mail
     */
    public static function Mail()
    {
        return [
            'Host' => '__A_RENSEIGNER__',
            'SMTPAuth' => true,
            'SMTPSecure' => 'tls',
            'Username' => '__A_RENSEIGNER__',
            'Password' => '__A_RENSEIGNER__',
            'Port' => 587,
        ];
    }

    /**
     * OwnerMail
     */
    public static function OwnerMail()
    {
        return [
            'originMail' => '__A_RENSEIGNER__',
            'destEmail' => '__A_RENSEIGNER__',
            'footerMessage' => '__A_RENSEIGNER__',
        ];
    }

}