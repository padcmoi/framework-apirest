<?php
require_once 'SecurityIncludeIdentifier.php'; // Empeche le chargement de cette page en dehors de api.php

/**
 *
 */
class ApiAuthentification
{
    use PreventSpamIP, PreventSpamMail;

    private $user_permission, $state;

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
            self::$instance->user_permission = [];
            self::$instance->state = [
                'logged' => false,
                'registered' => false,
                'changed' => false,
                'logout' => false,
            ];
            self::$instance->pdo_db = ApiDatabase::__instance_singleton()->pdo_useDB();

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

            CREATE TABLE IF NOT EXISTS `" . Config::Database()['prefix'] . "account` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `enable` BOOLEAN NOT NULL DEFAULT TRUE,
                `user` varchar(30) NOT NULL,
                `password` varchar(128) NOT NULL DEFAULT '',
                `rank` enum('guest','user','operator','admin','root') NOT NULL DEFAULT 'user',
                `email` varchar(80) NOT NULL,
                `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_connected` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `guid` varchar(150) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
                PRIMARY KEY (`id`),
                UNIQUE KEY `user` (`user`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB AUTO_INCREMENT=" . mt_rand(1011, 5011) . " DEFAULT CHARSET=utf8;

            COMMIT;
        ");
    }

    public function router()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();
        $api_token = ApiToken::__instance_singleton();

        // ApiCacheData::__instance_singleton()->add(['dqssfddfsdsf' => ApiMisc::createGUID(), 0]);

        if ($api_token->isValidated()) {
            if (ApiCaptcha::__instance_singleton()->verifyCaptcha()) {
                switch ($_SERVER['REQUEST_METHOD']) {
                    case 'GET': // Lecture de données
                        if (ApiMisc::isRouteUsed('api/auth/info')) {}
                        break;
                    case 'POST': // Création/Ajout de données
                        if (ApiMisc::isRouteUsed('api/auth/login')) {
                            $this->PreventSpamMail();
                            $this->PreventSpamIP();
                            $this->state['logged'] = $this->login($api_cache_data, $api_token);
                        }
                        if (ApiMisc::isRouteUsed('api/auth/register')) {
                            $this->state['registered'] = $this->register($api_cache_data, $api_token);
                        }
                        break;
                    case 'PUT': // Mise à jour des données
                        if (ApiMisc::isRouteUsed('api/auth/change')) {
                            $this->state['changed'] = $this->change();
                            // ApiCacheData::__instance_singleton()->add(['AAAAAAAAAAAA ' . $this->state['changed'] . ' ' . mt_rand() => 123], 0);
                        }
                        break;
                    case 'DELETE': // Suppression de données
                        if (ApiMisc::isRouteUsed('api/auth/logout')) {
                            $this->state['logout'] = $this->logout($api_cache_data, $api_token);
                        }
                        break;
                }
            } else {
                // Affichage du captcha echoué
                if (ApiMisc::isRouteUsed('api/auth/login') ||
                    ApiMisc::isRouteUsed('api/auth/register')) {
                    ApiCacheData::__instance_singleton()->add_check_input(['captcha_failed']);
                }
            }

            $api_cache_data->add(array(
                'user_permission' => $this->user_permission,
            ));
        }
    }

    /**
     * Type GETTER
     * Utilisateur authentifié ?
     *
     * return {bool} - true pour oui
     *               - false pour non
     */
    public function isLogged()
    {
        $getPayload = ApiToken::__instance_singleton()->getPayload();

        return isset($getPayload['content']) && isset($getPayload['content']['loggedIn'])
        ? $getPayload['content']['loggedIn']
        : false;
    }

    /**
     * Type SETTER
     * Login ...
     *
     * return {bool} - true pour logged / false pour erreur auth
     */
    private function login(ApiCacheData $api_cache_data, ApiToken $api_token)
    {
        $REQ_DATA = ApiMisc::REQ_DATA();

        $username = isset($REQ_DATA['username']) ? ApiMisc::sanitize_string($REQ_DATA['username']) : '';
        $password = isset($REQ_DATA['password']) ? ApiMisc::sanitize_string($REQ_DATA['password']) : '';

        // Deja connecté donc ne va plus loin
        if ($this->isLogged()) {
            $api_cache_data->add(array('auth_response' => 'already_logged'));
            return true;
        }

        $where_clause = array(
            'user' => $username,
            'password' => self::hash_password($password),
        );
        $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', $where_clause, [], 1, 0); // table , where clause , selector clause , limit , n/a

        // User && Passwd ok -> Auth
        if (count($req)) {
            if (!$req[0]['enable']) {
                $api_cache_data->add(array(
                    'auth_response' => 'account_disabled',
                ));
                ApiCacheData::__instance_singleton()->add_check_input(['account_disabled']);

                return false;
            } else {
                $req[0]['guid'] = ApiMisc::createGUID();
                ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'account', ['guid' => $req[0]['guid'], 'last_connected' => date('Y-m-d H:i:s', time())], ['id' => intval($req[0]['id'])], 1);

                ApiSessionActive::__instance_singleton()->login(intval($req[0]['id']));

                self::setPayload($req);

                $api_cache_data->add(array('auth_response' => 'new_login'));

                return true;
            }
        }

        // Mot de passe erroné ou utilisateur inconnu
        if (!count($req)) {
            ApiCaptcha::__instance_singleton()->addCaptcha();

            $api_cache_data->add(array(
                'auth_response' => 'login_failed',
            ));
            ApiCacheData::__instance_singleton()->add_check_input(['login_failed']);

            return false;
        }
    }

    /**
     * Type SETTER
     * Change ...
     *
     * return {bool} - true pour changed / false pour not changed ou erreur
     */
    private function change()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();
        $api_token = ApiToken::__instance_singleton();

        // Doit être connecté !
        if (!$this->mustBeConnected()) {
            return false;
        }

        $payload = $api_token->getPayload();
        if (isset($payload['content']) && isset($payload['content']['id'])) {

            $key_id = $payload['content']['id'];
            $update = $this->checkUserInput();

            $update['guid'] = ApiMisc::createGUID();
            ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'account', $update, ['id' => $key_id], 1);
            $result = $this->reloadData($key_id);

            return $result;
        } else {
            return false;
        }
    }

    /**
     * Type SETTER
     * Register ...
     *
     * return {bool}{Int} - lastInsertId pour registered / false pour erreur registered
     */
    private function register(ApiCacheData $api_cache_data, ApiToken $api_token)
    {
        $payload = ApiToken::__instance_singleton()->getPayload();

        if ($this->isInPreventSpam()) {
            ApiCacheData::__instance_singleton()->add_check_input(['spam_prevention']);
            return false;
        }

        if (isset($payload['content']) && isset($payload['content']['captchaPassed'])) {
            if (!$payload['content']['captchaPassed']) {
                $api_cache_data->add(array('auth_response' => 'captcha_failed'));
                return false;
            }
        } else {
            ApiMisc::http_response_code(500);
            exit;
        }

        // Deja connecté donc ne va plus loin
        if ($this->isLogged()) {
            $api_cache_data->add(array('auth_response' => 'currently_logged'));
            return false;
        }

        $REQ_DATA = ApiMisc::REQ_DATA();

        $username = isset($REQ_DATA['username']) ? ApiMisc::sanitize_string($REQ_DATA['username']) : '';
        $email = isset($REQ_DATA['email']) ? ApiMisc::sanitize_string($REQ_DATA['email']) : '';

        $verify_if_user_exist = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['user' => $username], [], 1, 0);
        $verify_if_email_exist = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['email' => $email], [], 1, 0);

        // On verifie que les données soient présente et surtout qu'elle n'existe pas deja
        if ($username === '') {
            ApiCacheData::__instance_singleton()->add_check_input(['user_cant_empty']);
        } else if ($email === '') {
            ApiCacheData::__instance_singleton()->add_check_input(['email_cant_empty']);
        } else if (count($verify_if_user_exist)) {
            ApiCacheData::__instance_singleton()->add_check_input(['user_already_exist']);
        } else if (count($verify_if_email_exist)) {
            ApiCacheData::__instance_singleton()->add_check_input(['email_already_exist']);
        } else {
            // l'adresse email et utilisateur n'existe pas deja dans la base de données alors on peut continuer

            $insert = array(
                'user' => $username,
                'rank' => 'user',
                'guid' => ApiMisc::createGUID(),
            );
            $checkUserInput = $this->checkUserInput();

            $insert = array_merge($insert, $checkUserInput);

            if (!count(ApiCacheData::__instance_singleton()->get_check_input())) {
                ApiDatabase::__instance_singleton()->pdo_start_transation();
                $result = ApiDatabase::__instance_singleton()->pdo_insert(Config::Database()['prefix'] . 'account', $insert);
                $last_insert_id = intval(ApiDatabase::__instance_singleton()->pdo_lastInsertId());

                if ($result) {
                    $api_cache_data->add(array('auth_response' => 'account_created'));

                    $this->addIPForPreventSpam();
                    return $last_insert_id;
                } else {
                    $api_cache_data->add(array('auth_response' => 'account_failed'));
                    ApiCacheData::__instance_singleton()->add_check_input(['something_is_wrong']);
                    ApiDatabase::__instance_singleton()->pdo_rollback_transation();
                    ApiCaptcha::__instance_singleton()->addCaptcha();
                    return false;
                }
            } else {
                return false;
            }

        }

    }

    /**
     * Type SETTER
     * Logout ...
     *
     * return {bool} - true pour logout successfull / false pour erreur
     */
    private function logout(ApiCacheData $api_cache_data, ApiToken $api_token)
    {
        // Pas connecté donc ne va plus loin
        if (!$this->isLogged()) {
            $api_cache_data->add(array('auth_response' => 'currently_disconnected'));
            return false;
        }

        $getPayload = ApiToken::__instance_singleton()->getPayload();
        if (isset($getPayload['content']) && isset($getPayload['content']['id'])) {
            ApiSessionActive::__instance_singleton()->logout(intval($getPayload['content']['id']));
            ApiDatabase::__instance_singleton()->pdo_update(Config::Database()['prefix'] . 'account', ['guid' => ApiMisc::createGUID()], ['id' => intval($getPayload['content']['id'])], 1);
        }

        $api_token->resetPayload();

        $api_cache_data->add($api_token->newToken($api_cache_data));

        $api_cache_data->add(array('auth_response' => 'disconnected'));

        return true;
    }

    /**
     * Type SETTER
     * Mise à jour des données pour toutes raisons, requiert ID
     *
     * no return
     */
    private function reloadData(int $key_id)
    {
        $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['id' => $key_id], [], 1, 0);

        if (count($req)) {
            self::setPayload($req);
            ApiCacheData::__instance_singleton()->add(array('auth_response' => 'change_login_info'));
            return true;
        } else {
            return false;
        }
    }

    /**
     * Type GETTER
     * Compare les hashs entre le payload et le contenu stocké en base de données.
     *
     * return {bool} true pour different false pour identique
     */
    public function isDifferentContentHash(int $key_id)
    {
        $getPayload = ApiToken::__instance_singleton()->getPayload();

        $content_hash_payload = md5(json_encode([
            $getPayload['content']['username'],
            $getPayload['content']['rank'],
            $getPayload['content']['email'],
        ]));

        $req_account = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['id' => $key_id], [], 1, 0);

        if (count($req_account)) {
            $content_hash_db = md5(json_encode([
                $req_account[0]['user'],
                $req_account[0]['rank'],
                $req_account[0]['email'],
            ]));
        }

        $result = $content_hash_payload === $content_hash_db ? false : true;

        if ($result) {
            $this->reloadData($key_id);
        }

        return $result;
    }

    /**
     * Type GETTER
     * Verifie l'UUID du payload avec la base de données,
     * si identique alors le jwt est autorisé à se connecter a la base de données.
     * ceci aide à lutter contre l'eventuel vol de jeton JWT.
     *
     * return {bool} true pour autorisé
     */
    public function isFullyAuthenticated()
    {
        $getPayload = ApiToken::__instance_singleton()->getPayload();

        $key_id = ApiMisc::getMyId();

        $req_account = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', ['id' => $key_id], [], 1, 0);

        if (count($req_account)) {
            if (isset($getPayload['content']) && isset($getPayload['content']['guid'])) {
                $result = $getPayload['content']['guid'] === $req_account[0]['guid'] ? true : false;
            } else {
                $result = false;
            }
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * Type SETTER
     * Prepare le champ Payload et renvoi un token avec ces informations
     *
     * no return
     */
    private static function setPayload(array $req)
    {
        $api_cache_data = ApiCacheData::__instance_singleton();
        $api_token = ApiToken::__instance_singleton();

        if (count($req)) {

            $data = array(
                'content' => [
                    'id' => intval($req[0]['id']),
                    'loggedIn' => true,
                    'username' => ApiMisc::sanitize_string($req[0]['user']),
                    'rank' => ApiMisc::sanitize_string($req[0]['rank']),
                    'email' => ApiMisc::sanitize_string($req[0]['email']),
                    'created' => date('d-m-Y H:i:s', strtotime($req[0]['created'])),
                    'last_connected' => date('d-m-Y H:i:s', strtotime($req[0]['last_connected'])),
                    'guid' => ApiMisc::sanitize_string($req[0]['guid']),
                    'captchaPassed' => true,
                ],
            );

            $api_token->setPayload($data);

            $api_cache_data->add($api_token->newToken($api_cache_data));
        }
    }

    /**
     * Type GETTER
     * Verifie si l'utilisateur est connecté,
     * retourne false si pas connecté avec un passage Captcha.
     *
     * return {bool}
     */
    private function mustBeConnected()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();

        if (!$this->isLogged()) {
            ApiCaptcha::__instance_singleton()->addCaptcha();
            $api_cache_data->add(array('auth_response' => 'not_connected'));
            return false;
        } else {
            return true;
        }
    }

    /**
     * Type GETTER
     * Verifie les champs envoyés par l'utilisateur.
     *
     * return {array}
     */
    private function checkUserInput()
    {
        $REQ_DATA = ApiMisc::REQ_DATA();
        $update = array();

        if (isset($REQ_DATA['set_password1'])) {
            $check_password = self::check_password($REQ_DATA['set_password1'], isset($REQ_DATA['set_password2']) ? $REQ_DATA['set_password2'] : '');

            if ($this->isLogged()) {
                if (isset($REQ_DATA['current_password']) && $REQ_DATA['current_password'] !== "") {

                    $where_clause = array(
                        'user' => ApiToken::__instance_singleton()->getPayload()['content']['username'],
                        'password' => self::hash_password($REQ_DATA['current_password']),
                    );
                    $req = ApiDatabase::__instance_singleton()->pdo_select(Config::Database()['prefix'] . 'account', $where_clause, [], 1, 0); // table , where clause , selector clause , limit , n/a

                    if (count($req)) {
                        if (count($check_password) === 0) {
                            $update['password'] = self::hash_password(ApiMisc::sanitize_string($REQ_DATA['set_password1']));
                        } else {
                            ApiCacheData::__instance_singleton()->add_check_input($check_password);
                        }
                    } else {
                        ApiCacheData::__instance_singleton()->add_check_input(array(
                            'bad_password',
                        ));
                    }
                } else {
                    ApiCacheData::__instance_singleton()->add_check_input(array(
                        'empty_password',
                    ));
                }
            } else {
                if (count($check_password) === 0) {
                    $update['password'] = self::hash_password(ApiMisc::sanitize_string($REQ_DATA['set_password1']));
                } else {
                    ApiCacheData::__instance_singleton()->add_check_input($check_password);
                }
            }
        }
        if (isset($REQ_DATA['email'])) {
            if (filter_var($REQ_DATA['email'], FILTER_VALIDATE_EMAIL)) {
                $getPayload = ApiToken::__instance_singleton()->getPayload();
                $new_email = ApiMisc::sanitize_string($REQ_DATA['email']);
                if (isset($getPayload['content']) && isset($getPayload['content']['email']) && $getPayload['content']['loggedIn'] && $getPayload['content']['email'] !== $new_email) {
                    ApiInternalMail::__instance_singleton()->update($getPayload['content']['email'], $new_email);
                    ApiCacheData::__instance_singleton()->add(['confirm_new_email_address' => true], 1);
                } else {
                    $update['email'] = ApiMisc::sanitize_string($REQ_DATA['email']);
                }
            } else {
                ApiCacheData::__instance_singleton()->add_check_input(['email_not_valid']);
            }
        }

        return $update;
    }

    /**
     * type GETTER
     * Récupère le retour d'etat d'une requete,
     * retournera false si jamais la clé state n'est pas connu.
     *
     * return {bool}
     */
    public function getState(string $state)
    {
        return array_key_exists($state, $this->state) ? $this->state[$state] : false;
    }

    /**
     * type GETTER
     * Hash le mot de passe avec la clé secrete.
     *
     * return {string}
     */
    public static function hash_password(string $password)
    {
        return hash_hmac(Config::Authentification()['ALGORITHM'], $password, Config::Authentification()['KEY'], false);
    }

    /**
     * Type GETTER
     * Verifie la solidité du mot de passe, la comparaison et
     * retourne un tableau des éléments manquant, si le tableau retour de type {array} est vide c'est que tous les tests ont réussi.
     *
     * return {array} - annalyser avec count( {array} ) si = 0 tous les tests ont réussi.
     */
    private static function check_password(string $password1, string $password2)
    {
        $result_check = array();

        $require_check = array(
            'matches' => ($password1 === $password2),
            'size' => strlen($password1),
            'uppercase' => 0,
            'lowercase' => 0,
            'number' => 0,

        );

        for ($i = 0; $i < $require_check['size']; $i++) {
            if ($password1[$i] >= 'a' && $password1[$i] <= 'z') {
                $require_check['lowercase']++;
            } else if ($password1[$i] >= 'A' && $password1[$i] <= 'Z') {
                $require_check['uppercase']++;
            } else if ($password1[$i] >= '0' && $password1[$i] <= '9') {
                $require_check['number']++;
            }
        }

        if (!$require_check['matches']) {
            $result_check[] = 'passwords_dont_match';
        }
        if ($require_check['size'] < 8) {
            $result_check[] = 'password_too_short';
        }
        if ($require_check['uppercase'] < 1) {
            $result_check[] = 'one_uppercase_required';
        }
        if ($require_check['lowercase'] < 2) {
            $result_check[] = 'two_lowercase_required';
        }
        if ($require_check['number'] < 2) {
            $result_check[] = 'two_digits_required';
        }

        return $result_check;
    }

    /**
     * Type GETTER
     * Liste les rangs disponible par rapport au rang actuel.
     *
     * no return
     */
    public function get_ranks_available_for_rank()
    {
        $api_token = ApiToken::__instance_singleton();
        $api_cache_data = ApiCacheData::__instance_singleton();
        $payload = $api_token->getPayload();

        if (isset($payload['content']) && isset($payload['content']['rank'])) {
            $rank = array();

            switch ($payload['content']['rank']) {
                case 'guest':
                    $rank = ['guest'];
                    break;
                case 'user':
                    $rank = ['user'];
                    break;
                case 'operator':
                    $rank = ['guest', 'user'];
                    break;
                case 'admin':
                    $rank = ['guest', 'user', 'operator'];
                    break;
                case 'root':
                    $rank = ['guest', 'user', 'operator', 'admin', 'root'];
                    break;
            }

            $this->user_permission = array_merge($this->user_permission, array('available_ranks' => $rank));
        }
    }
}