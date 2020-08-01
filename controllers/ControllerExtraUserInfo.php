<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Class:           ControllerExtraUserInfo
 * Description:     Controller Informations Compte étendu
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         09/06/2020
 */
class ControllerExtraUserInfo
{
    use MixinsExtraUserInfoCommon, MixinsExtraUserInfoGet, MixinsExtraUserInfoCommit;

    private static $instance;
    private function __construct()
    {
        $this->api_database = ApiDatabase::__instance_singleton();
        $this->extra_user_info = array();
    }

    /**
     * Type SETTER
     * Instance la Class directement dans la class.
     *
     * return
     */
    public static function __instance_modular_singleton(string $method)
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
            self::$instance->require_table();
        }

        self::$instance->router();
    }

    /**
     * Type SETTER
     * Verifie que les tables requises sont presentes, les ajoutes le cas échéant.
     *
     * no return
     */
    private function require_table()
    {
        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("
            START TRANSACTION;

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "extra_info` (
            `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `id_account` int(11) NOT NULL,
            `gender` enum('male','female') NOT NULL DEFAULT 'male',
            `firstName` varchar(50) NOT NULL DEFAULT '',
            `lastName` varchar(50) NOT NULL DEFAULT '',
            `phone` varchar(32) NOT NULL DEFAULT '',
            `age` DATE NOT NULL DEFAULT '1982-01-01',
            `adress` varchar(255) NOT NULL DEFAULT '',
            `citycode` varchar(10) NOT NULL DEFAULT '00000',
            `city` varchar(50) NOT NULL DEFAULT '',
            UNIQUE KEY `id_account` (`id_account`),
            CONSTRAINT `account_extra_info_related_id` FOREIGN KEY (`id_account`) REFERENCES `" . Config::Database()['prefix'] . "account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            COMMIT;
        ");
    }

    private function router()
    {

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                if (ApiMisc::isRouteUsed('api/personnal/info')) {
                    $this->get();
                }
                break;
            case 'POST': // Création/Ajout de données
                if (ApiMisc::isRouteUsed('api/auth/login')) {
                    $this->get();
                }
                if (ApiMisc::isRouteUsed('api/auth/register')) {
                    $this->insert();
                }
                break;
            case 'PUT': // Mise à jour des données
                if (ApiMisc::isRouteUsed('api/auth/change')) {
                    $this->change();
                }
                break;
            case 'DELETE': // Suppression de données
                if (ApiMisc::isRouteUsed('api/auth/logout')) {
                    $this->logout();
                }
                break;
        }

    }

}