<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

/**
 * Class:           ApiSessionActive.php
 * Description:     Verifie que les utilisateurs sont connecté en permanence
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         20/06/2020
 */
class ApiSessionActive
{

    /**
     * Type SETTER
     * Class __instance_singleton
     * Instance la Class directement dans la class une unique fois.
     *
     * return {object} Instance
     */
    private static $singleton = false, $instance = null;
    private function __construct()
    {}
    public static function __instance_singleton()
    {
        if (!self::$singleton) {
            self::$instance = new self();
            self::$instance->require_table();
            self::$instance->purge();
            self::$instance->isSessionActive();
            self::$singleton = true;
        }

        return self::$instance;
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

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "session_active` (
                `sql_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `id_account` int(11) NOT NULL,
                `loggedIn` BOOLEAN NOT NULL DEFAULT FALSE,
                `jwt_hash` varchar(32) NOT NULL,
                `last_access_data` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `id_account` (`id_account`),
                CONSTRAINT `account_session_active_related_id` FOREIGN KEY (`id_account`) REFERENCES `" . Config::Database()['prefix'] . "account` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            COMMIT;
        ");
    }

    /**
     * Type SETTER
     * logout toutes les sessions actives de plus de 24 heures.
     *
     * no return
     */
    private function purge()
    {
        ApiDatabase::__instance_singleton()->pdo_useDB()->exec("UPDATE " . Config::Database()['prefix'] . "session_active SET loggedIn = 0 WHERE loggedIn = 1 AND DATEDIFF( CURRENT_TIMESTAMP, last_access_data) > 7;");
    }

    public function router()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                break;
            case 'POST': // Création/Ajout de données
                if (ApiMisc::isRouteUsed('api/auth/register')) {
                    $this->insert();
                }
                break;
            case 'PUT': // Mise à jour des données
                break;
            case 'DELETE': // Suppression de données
                break;
        }
    }

    /**
     * Type SETTER
     * Insert ...
     *
     * no return
     */
    private function insert()
    {
        if (!ApiAuthentification::__instance_singleton()->isLogged() && ApiAuthentification::__instance_singleton()->getState('registered')) {
            $key_id = intval(ApiAuthentification::__instance_singleton()->getState('registered'));
            ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'session_active', ['id_account' => $key_id, 'jwt_hash' => md5(ApiMisc::getJWT())]);
        }
    }

    /**
     * Type SETTER
     * Verifie si la session est active en base de données.
     *
     * no return
     */
    private function isSessionActive()
    {
        $getPayload = ApiToken::__instance_singleton()->getPayload();
        $jwt_hash = md5(ApiMisc::getJWT());

        if (ApiAuthentification::__instance_singleton()->isLogged()) {
            if (isset($getPayload['content']) && isset($getPayload['content']['id'])) {
                $key_id = $getPayload['content']['id'];
                $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'session_active', ['id_account' => $key_id], [], 1, 0);
                if (count($req)) {
                    if ($req[0]['loggedIn'] === '1' && ApiAuthentification::__instance_singleton()->isFullyAuthenticated()) {
                        if ($req[0]['jwt_hash'] !== $jwt_hash) { // si le jwt_hash du token jwt n'est pas egale à la bdd alors ...
                            ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'session_active', ['jwt_hash' => $jwt_hash, 'last_access_data' => date('Y-m-d H:i:s', time())], ['id_account' => $key_id], 1);
                        }

                        ApiAuthentification::__instance_singleton()->isDifferentContentHash($key_id);
                    } else {
                        // TO DO disconnect
                        ApiCacheData::__instance_singleton()->add(['JWT' => ''], 0);
                        ApiToken::__instance_singleton()->resetPayload();
                        ApiCacheData::__instance_singleton()->add(ApiToken::__instance_singleton()->newToken(ApiCacheData::__instance_singleton()));
                    }
                } else {
                    ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'session_active', ['id_account' => $key_id, 'jwt_hash' => $jwt_hash]);
                    // TO DO disconnect
                    ApiCacheData::__instance_singleton()->add(['JWT' => ''], 0);
                    ApiToken::__instance_singleton()->resetPayload();
                    ApiCacheData::__instance_singleton()->add(ApiToken::__instance_singleton()->newToken(ApiCacheData::__instance_singleton()));
                }
            }
        }
    }

    /**
     * Type SETTER
     * Login
     *
     * no return
     */
    public function login(int $key_id)
    {
        $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'session_active', ['id_account' => $key_id], [], 1, 0);
        if (count($req)) {
            ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'session_active', ['loggedIn' => 1, 'jwt_hash' => md5(ApiMisc::getJWT()), 'last_access_data' => date('Y-m-d H:i:s', time())], ['id_account' => $key_id], 1);
        } else {
            ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'session_active', ['loggedIn' => 1, 'id_account' => $key_id, 'jwt_hash' => md5(ApiMisc::getJWT())]);
        }
    }

    /**
     * Type SETTER
     * logout
     *
     * no return
     */
    public function logout(int $key_id)
    {
        $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'session_active', ['id_account' => $key_id], [], 1, 0);
        if (count($req)) {
            ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'session_active', ['loggedIn' => 0, 'jwt_hash' => md5(ApiMisc::getJWT()), 'last_access_data' => date('Y-m-d H:i:s', time())], ['id_account' => $key_id], 1);
        } else {
            ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'session_active', ['loggedIn' => 0, 'id_account' => $key_id, 'jwt_hash' => md5(ApiMisc::getJWT())]);
        }
    }
}