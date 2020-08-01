<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Controller exemple à supprimer ...
 */
class ControllerCustomExample
{
    /**
     * Pour de l'heritage multiple, plus fléxible que les namespace
     * chaque librairie peut etre stocké dans le dossier /mixins/
     */
    // use CustomLibrarie1,  CustomLibrarie2,  CustomLibrarie3;

    private static $instance;
    private function __construct()
    {
        $this->pdo_db = ApiDatabase::__instance_singleton()->pdo_useDB();
        $this->require_table();
    }
    /**
     * Instancie directement dans la Class static
     * peut etre utilisé comme un constructeur,
     * la Class ne peut s'instancier autrement que par cette method static
     * etant donné que le constructeur est en privé
     */
    public static function __instance_modular_singleton()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }
        self::$instance->router(); // Au moment d'instancier on charge le router
        return self::$instance;
    }
    /**
     * Type SETTER
     * Création de la table
     * no return
     */
    private function require_table()
    {
        $this->pdo_db->exec("
            START TRANSACTION;

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "example` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            COMMIT;
        ");
    }
    /**
     * Verifie si une URN est présente
     * comme identifiant de ressource en meme temps que la REQUEST_METHOD
     */
    private function router()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                if (ApiMisc::isRouteUsed('show/me/example/list')) {}
                break;
            case 'POST': // Création/Ajout de données
                if (ApiMisc::isRouteUsed('add/me/example')) {}
                break;
            case 'PUT': // Mise à jour des données
                if (ApiMisc::isRouteUsed('change/me/example')) {}
                break;
            case 'DELETE': // Suppression de données
                if (ApiMisc::isRouteUsed('delete/me/example')) {}
                break;
        }
    }
}