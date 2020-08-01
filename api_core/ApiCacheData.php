<?php
require_once 'SecurityIncludeIdentifier.php'; // Empeche le chargement de cette page en dehors de api.php

/**
 * Type:            Class __instance_singleton !
 * Class:           ApiCacheData
 * Description:     Permet de mettre en cache toutes les données qui seront traitées en finalité,
 *                  cette class ne doit etre instancé qu'une seule fois.
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         03/05/2020
 *
 */
class ApiCacheData
{
    private $data_encoders, $payload, $check_input, $api_version, $api_last_update, $time_app_exec = 0.00;

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
    public static function __instance_singleton($api_version = '0', $api_last_update = ['time' => 0, 'file' => ''], $time_app_exec = 0.00)
    {
        if (!self::$singleton) {
            self::$instance = new self();
            self::$instance->api_version = (string) $api_version;
            self::$instance->api_last_update = (array) $api_last_update;
            self::$instance->time_app_exec = (float) $time_app_exec;
            self::$instance->data_encoders = [];
            self::$instance->check_input = [];

            self::$singleton = true;
        }
        return self::$instance;
    }

    /**
     * Type SETTER
     * Pour la mise en cache, déclare une version des données
     * afin d'eviter de recharger completement les données
     * la version est obtenu par un hash md5 des 2 objets :
     * data_encoders - payload
     *
     * no return
     */
    private function version()
    {
        $data = $this->data_encoders;
        if (isset($data['tokenState'])) {
            unset($data['tokenState']);
        }
        if (isset($data['tokenMsg'])) {
            unset($data['tokenMsg']);
        }
        if (isset($data['tokenExp'])) {
            unset($data['tokenExp']);
        }
        if (isset($data['hash_version'])) {
            unset($data['hash_version']);
        }
        if (isset($data['perf_result_milliseconds'])) {
            unset($data['perf_result_milliseconds']);
        }

        $this->data_encoders['hash_version'] = md5(json_encode($data));
    }

    /**
     * Type GETTER
     * return {array} - retourne tous les elements contenus
     * dans l'attribut data_encoders sous forme de tableau
     *
     * return {array}
     */
    public function get()
    {
        if (count($this->data_encoders) === 0) {
            ApiMisc::http_response_code(202);
        }

        $this->if_empty_token_so_add_token();
        $this->merge_check_input();

        $this->appTimeExec();

        return $this->resultData();

    }

    public function get__()
    {
        return $this->data_encoders;

    }

    /**
     * Type GETTER
     * Renvoit une version limité de données dans le cas ou le client connait deja ces données
     *
     * return {array}
     */
    private function resultData()
    {
        $REQ_DATA = ApiMisc::REQ_DATA();
        if ($_SERVER['REQUEST_METHOD'] === "GET" &&
            isset($REQ_DATA['hash_version']) &&
            isset($this->data_encoders['hash_version']) &&
            $this->data_encoders['hash_version'] === $REQ_DATA['hash_version']
        ) {
            $data = [];

            if (isset($this->data_encoders['JWT'])) {
                // $data = array_merge($data, ['req_version' => $REQ_DATA['hash_version']]);
                if (isset($this->data_encoders['hash_version'])) {
                    $data = array_merge($data, ['hash_version' => $this->data_encoders['hash_version']]);
                }
                if (isset($this->data_encoders['tokenVerified'])) {
                    $data = array_merge($data, ['tokenVerified' => $this->data_encoders['tokenVerified']]);
                }
                if (isset($this->data_encoders['tokenExp'])) {
                    $data = array_merge($data, ['tokenExp' => $this->data_encoders['tokenExp']]);
                }
                if (isset($this->data_encoders['exp'])) {
                    $data = array_merge($data, ['exp' => $this->data_encoders['exp']]);
                }
                if (isset($this->data_encoders['content'])) {
                    $data = array_merge($data, ['content' => $this->data_encoders['content']]);
                }
                if (isset($this->data_encoders['perf_result_milliseconds'])) {
                    $data = array_merge($data, ['perf_result_milliseconds' => $this->data_encoders['perf_result_milliseconds']]);
                }
                if (isset($this->data_encoders['picture'])) {
                    $data = array_merge($data, ['picture' => $this->data_encoders['picture']]);
                }
                if (ApiMisc::getJWT() != $this->data_encoders['JWT']) {
                    $data = array_merge($data, ['JWT' => $this->data_encoders['JWT']]);
                }
            }

            return $data;
        } else {
            if (ApiMisc::getJWT() === $this->data_encoders['JWT']) {
                unset($this->data_encoders['JWT']);
            }
            return $this->data_encoders;
        }
    }

    /**
     * Type SETTER
     * {array} $data - Tableau des données à rajouter à l'attribut data_encoders
     * {bool} $no_versioning - si true change le hash pour le versioning et mise en cache des données.
     *
     * no return
     */
    public function add(array $data, bool $no_versioning = false)
    {
        $this->data_encoders = array_merge($this->data_encoders, $data);
        if (!$no_versioning) {
            $this->version();
        }
    }

    /**
     * Type SETTER
     * Reinitialise l'attribut data_encoders {array}
     *
     * no return
     */
    public function reset()
    {
        $this->data_encoders = array();
        $this->version();
    }

    /**
     * Type GETTER
     * Obtenir les informations/version/mise à jour sur l'API,
     * et affiche quelques URI disponibles
     *
     * no return
     */
    public function getInfo()
    {
        $this->add(array(
            'welcome' => 'Bienvenue sur un petit framework simple & evolutif',
            'git_projet' => 'https://gitlab.com/juliennaskot/api-rest-php',
            'api' => array(
                'type' => 'API REST',
                'version' => $this->api_version,
                'servertime' => date('d/m/Y H:i:s e', time()),
                'last_update' => date('d/m/Y H:i:s e', $this->api_last_update['time']),
            ),
            'list' => array(
                'get_info' => 'GET /api/info',
                'get_token' => 'GET /api/token/get',
            ),
        ), 1);
    }

    /**
     * Type GETTER
     * Obtenir les informations/version/mise à jour sur l'API,
     * et affiche quelques URI disponibles
     *
     * no return
     */
    public function getFullInfo()
    {
        $this->add(array(
            'welcome' => 'Bienvenue sur un petit framework simple & evolutif',
            'git_projet' => 'https://gitlab.com/juliennaskot/api-rest-php',
            'api' => array(
                'type' => 'API REST',
                'version' => $this->api_version,
                'servertime' => date('d/m/Y H:i:s e', time()),
                'last_update' => array(
                    'date' => date('d/m/Y H:i:s e', $this->api_last_update['time']),
                    'file' => $this->api_last_update['file'],
                ),
            ),
            'list' => array(
                'get_info' => 'GET /api/info',
                'get_token' => 'GET /api/token/get',
            ),
            'complete_check_input' => array(
                'user_cant_empty',
                'user_already_exist',
                'email_not_valid',
                'account_disabled',
                'login_failed',
                'passwords_dont_match',
                'password_too_short',
                'one_uppercase_required',
                'two_lowercase_required',
                'two_digits_required',
                'captcha_not_valid',
                'unknown_email_address',
                'email_code_already_sent',
                'horse_name_not_valid',
                'horse_ueln_not_valid',
                'horse_sire_not_valid',
                'horse_race_not_valid',
                'something_is_wrong',
            ),
        ), 1);
    }

    /**
     * Type SETTER
     * Ajoute à l'attribut data_encoders dans la Clé result_generated_in,
     * les indices de performances pour chaque requête.
     *
     * no return
     */
    private function appTimeExec()
    {
        $current = microtime(true);
        $time_in_second = ($current - $this->time_app_exec);
        $time_in_second = ($time_in_second * 1000);
        $message = round($time_in_second, 2) . 'ms';
        $this->data_encoders = array_merge($this->data_encoders, array(
            'perf_result_milliseconds' => array(
                'string_type' => $message,
                'double_type' => round($time_in_second, 2),
            ),
        ));
    }

    /**
     * Type SETTER
     * Ajoute un jeton au retour de données dans le cas ou aucun jeton JWT n'a pu etre crée,
     * dans tous les cas désormais l'API retournera un jeton vide,
     * invalide ou valide selon le retour utilisateur.
     *
     * no return
     */
    private function if_empty_token_so_add_token()
    {
        if (!array_key_exists('JWT', $this->data_encoders)) {
            $this->data_encoders = array_merge(array(
                'JWT' => ApiMisc::getJWT(),
            ), $this->data_encoders);
        }
    }

    /**
     * Type SETTER
     * Fusionne le contenu de check_input dans data_encoders.
     *
     * no return
     */
    private function merge_check_input()
    {
        $this->data_encoders = array_merge($this->data_encoders, array(
            'check_input' => $this->check_input,
        ));
    }

    /**
     * Type GETTER
     * Récupère le tableau des données de l'attribut check_input
     *
     * return {array}
     */
    public function get_check_input()
    {
        return $this->check_input;
    }

    /**
     * Type SETTER
     * {array} $data - Tableau des données à rajouter à l'attribut check_input
     *
     * no return
     */
    public function add_check_input(array $data)
    {
        $this->check_input = array_merge($this->check_input, $data);
    }
}