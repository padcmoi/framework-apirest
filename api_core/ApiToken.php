<?php
require_once 'SecurityIncludeIdentifier.php'; // Empeche le chargement de cette page en dehors de api.php

/**
 * Class:           ApiToken
 * Description:     Permet de verifier l'authenticité d'un jeton au format {header.payload.signature}
 *                  Peut générer un jeton ou le mettre à jour des le changement d'une donnée date ou payload.
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         01/05/2020
 */
class ApiToken
{
    private $is_validated, $jwt, $header, $payload;

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
            self::$instance->is_validated = false;
            self::$instance->jwt = ApiMisc::getJWT();
            self::$instance->header = [
                'alg' => 'HS256',
                'typ' => 'JWT',
            ];
            self::$instance->resetPayload();

            self::$singleton = true;
        }
        return self::$instance;
    }

    public function router()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                if (ApiMisc::isRouteUsed('api/token/get')) {
                    if (!$this->verifyToken($api_cache_data)) {
                        ApiMisc::http_response_code(201);
                        $the_new_token = $this->newToken($api_cache_data, false);
                        $api_cache_data->add($the_new_token, 0);
                        $this->jwt = '';
                    }
                }
                break;
            case 'POST':

                break; // Création/Ajout de données
            case 'PUT':

                break; // Mise à jour des données
            case 'DELETE': // Suppression de données
                if (ApiMisc::isRouteUsed('api/token/remove')) {
                    ApiMisc::http_response_code(201);
                    $this->jwt = '';
                    $api_cache_data->add(array(
                        'JWT' => $this->jwt,
                    ), 1);
                }
                break;
        }

        /**
         * Type SETTER
         *
         * Verification du token avec precision.
         * Mise à jour du token
         * Recupération du contenu du token.
         */
        if ($this->jwt && count(explode('.', $this->jwt)) === 3) {
            if ($this->verifyToken($api_cache_data)) {
                ApiMisc::http_response_code(201);
                if (self::isExpire($this->jwt)) { // token
                    $data = $this->newToken($api_cache_data, false);
                } else {
                    if ($this->readUserContent($this->jwt)) {
                        $data = isset($this->payload['tokenState']) && $this->payload['tokenState'] === 'valid_token'
                        ? array_merge($this->payload, array('JWT' => $this->jwt))
                        : $this->newToken($api_cache_data, true);
                    } else {
                        $this->resetPayload();
                        $data = $this->newToken($api_cache_data, true);
                    }
                }
            } else {
                // Le token a été refusé, on reset le token pour maintenir une connection minimal à l'application cliente.
                $this->jwt = '';
                $data = array(
                    'JWT' => $this->jwt,
                );
            }
            $api_cache_data->add($data, ($this->payload['tokenState'] === 'valid_token'));
            // à partir d'ici soit le token est valide soit le token est refusé et la communication s'arrete ici.
        }

    }

    /**
     * Type GETTER
     * Retourne si les vérifications sur le token ont eu lieu et
     * retourne true si valide sinon false si le token n'existe pas ou est invalide
     *
     * return {bool}
     */
    public function isValidated()
    {
        return $this->is_validated;
    }

    /**
     * Type SETTER
     * Déclare le token Verifié & Valide
     * true pour valide / false pour invalide.
     *
     * {bool} - $validated
     *
     * no return
     */
    private function setValidated(bool $validated = false)
    {
        $this->is_validated = (bool) $validated;
    }

    /**
     * type SETTER
     * Génére un Token JWT en 3 parties,
     * au format 'header.payload.signature'.
     *
     * {Class} $api_cache_data
     * {bool} $showCustomMessageNewToken - Ajoute au payload ou non le statut du token.
     *
     * return {string} $jwt - retourne le token
     */
    public function newToken(ApiCacheData $api_cache_data, bool $showCustomMessageNewToken = false)
    {
        // Crée la partie header & encode ce dernier
        $base64UrlHeader = ApiMisc::base64UrlEncode(json_encode($this->header));

        // Crée la partie Payload & encode ce dernier
        $payload = $this->payload;

        $payload['exp'] = time() + Config::Jwt()['EXPIRE_TIMESTAMP'];
        $base64UrlPayload = ApiMisc::base64UrlEncode(json_encode($payload));

        // Génére une signature basée sur le header et le payload hashé avec la KEY + SHA1 UserAgent.
        $serverSignature = self::makeSignature($this->header, $payload);

        // Return JWT Token
        $jwt_token = $base64UrlHeader . "." . $base64UrlPayload . "." . $serverSignature;
        ApiMisc::http_response_code(201);

        if (!$showCustomMessageNewToken) {
            $this->payload = array_merge($this->payload, array(
                'tokenState' => 'new_token',
                'tokenMsg' => '✓ new token',
                'tokenVerified' => false,
            ));
        }

        return array_merge($this->payload, array(
            'JWT' => $jwt_token,
        ));
    }

    /**
     * Type SETTER
     * Verifie la validité et remplace de dernier si le token est expiré
     * (string) $jwt - le token hashé séparé par 2 points
     * et sous la forme suivante 'header.payload.signature'
     *
     * les verifications se portent sur la validité de la signature
     * et la validité horaire.
     *
     * {Class} $api_cache_data
     *
     * return {bool} -  retourne true si le token est valide ou expiré mais acceptable,
     *                  retourne false si le token est invalide ou expiré et plus acceptable
     *
     */
    private function verifyToken(ApiCacheData $api_cache_data)
    {
        // coupe le token jwt en 3 et ecrit un message d'erreur de mauvais formatage.
        $tokenParts = explode('.', $this->jwt);
        if (count($tokenParts) !== 3) {
            $this->payload = array_merge($this->payload, array(
                'tokenState' => 'no_jwt_format',
                'tokenMsg' => '✗ no check because no jwt token found',
                'tokenVerified' => false,
            ));

            $this->setValidated(false);
            ApiMisc::http_response_code(100);
            return false; //RAISON: Ce token n' est pas au format JWT (JSON Web Token)
        }

        $header = self::convertStringToArray($tokenParts[0]);
        $payload = self::convertStringToArray($tokenParts[1]);
        $clientSignature = $tokenParts[2];

        // Verifie les clés du token JWT et affiche une erreur en cas de mauvais formatage.
        self::isTrueFormattedToken($header, $payload);

        if (self::isTokenExpired($payload['exp'], Config::Jwt()['TIME_LIMIT_TIMESTAMP'])) {

            $this->setValidated(false);
            ApiMisc::http_response_code(401);
            return false; //RAISON: expiré
        }

        // Génére une signature basée sur le header et le payload hashé avec la KEY + SHA1 UserAgent.
        $serverSignature = self::makeSignature($header, $payload);

        if ($serverSignature === $clientSignature) {
            $expire = $payload['exp'];

            if (self::isTokenExpired($payload['exp'], 0)) {
                $expire -= time() - Config::Jwt()['TIME_LIMIT_TIMESTAMP'];
                $this->payload = array_merge($this->payload, array(
                    'tokenState' => 'valid_token_but_expired',
                    'tokenMsg' => '✗ token expired, ' . $expire . ' seconds before it can no longer be used',
                    'tokenExp' => 0,
                    'exp' => $payload['exp'],
                    'tokenVerified' => true,
                ));
            } else {
                $expire -= time();
                $this->payload = array_merge($this->payload, array(
                    'tokenState' => 'valid_token',
                    'tokenMsg' => '✓ valid token, ' . $expire . ' seconds before expiring',
                    'tokenExp' => intval($expire),
                    'exp' => $payload['exp'],
                    'tokenVerified' => true,
                ));
            }

            $this->setValidated(true);
            ApiMisc::http_response_code(201);
            return true;
        } else {

            $this->setValidated(false);
            ApiMisc::http_response_code(403); // RAISON: La signature est fausse
            exit;
        }
    }

    /**
     * Type GETTER
     * Lit le contenu user du token afin d'obtenir les identifications cachées
     * et le stock dans l'attribut payload['content].
     *
     * return {bool} - Oui si le contenu a pu etre extrait sinon non
     */
    private function readUserContent()
    {
        // coupe le token jwt en 3 et ecrit un message d'erreur de mauvais formatage.
        $tokenParts = explode('.', $this->jwt);
        if (count($tokenParts) !== 3) {
            return false;
        }

        $header = self::convertStringToArray($tokenParts[0]);
        $payload = self::convertStringToArray($tokenParts[1]);

        // Verifie les clés du token JWT et affiche une erreur en cas de mauvais formatage.
        self::isTrueFormattedToken($header, $payload);

        $this->payload['content'] = $payload['content'];

        return true;
    }

    /**
     * Type GETTER
     * Retour tout le contenu de l'attribut payload.
     *
     * {Class} $api_cache_data
     *
     * return {array}
     */
    public function getPayload()
    {
        return (array) $this->payload;
    }

    /**
     * Type SETTER
     * fusionne à l'attribut payload, un tableau.
     *
     * {array} $data - nouvelle données
     *
     * no return
     */
    public function setPayload(array $data)
    {
        $this->payload = array_merge($this->payload, $data);
    }

    /**
     * Type SETTER
     * reset token
     *
     * no return
     */
    public function resetPayload()
    {
        $this->payload = [
            'JWT' => '',
            'content' => self::templateContent(),
            'exp' => 0,
            'tokenState' => 'no_state',
            'tokenMsg' => '',
            'tokenVerified' => false,
        ];
    }

    /****************************************************************
     *                                                              *
     *                                                              *
     *  Methods Statique
     *                                                              *
     *                                                              *
     ****************************************************************/

    /**
     * Type GETTER
     * Fournit un template vierge pour le content user d'un token
     *
     * return {array}
     */
    private static function templateContent()
    {
        return [
            'id' => -1,
            'loggedIn' => false,
            'username' => '',
            'rank' => 'guest',
            'email' => '',
            'created' => '01-01-1970 00:00:00',
            'last_connected' => '01-01-1970 00:00:00',
            'guid' => null,
            'captchaPassed' => false,
        ];
    }

    /**
     * Type GETTER
     * Verifie si le token existant est expiré ou non,
     * si il n'existe pas il sera consideré comme expiré
     *
     * {string} $jwt_token - le token jwt header.payload.signature
     *
     * return (bool) - Oui expiré - Oui si non existant - Non si il est present et non expiré
     */
    private static function isExpire(string $jwt_token)
    {
        // coupe le token jwt en 3 et ecrit un message d'erreur de mauvais formatage.
        $tokenParts = explode('.', $jwt_token);
        if (count($tokenParts) !== 3) {
            return true;
        }

        $header = self::convertStringToArray($tokenParts[0]);
        $payload = self::convertStringToArray($tokenParts[1]);

        // Verifie les clés du token JWT et affiche une erreur en cas de mauvais formatage.
        self::isTrueFormattedToken($header, $payload);

        if (self::isTokenExpired($payload['exp'], Config::Jwt()['TIME_LIMIT_TIMESTAMP'])) {
            return true;
        }

        return false;
    }

    /**
     * Type GETTER
     * Verifie si le token a expiré
     * {int} $expiration - timestamp du token qui sera comparé au timestamp actuel
     * {int} $delai - délai à ajouter pour expiration finale
     *
     * return {bool} true ou false
     */
    private static function isTokenExpired(int $expiration, int $delay = 0)
    {
        $expiration -= time() - $delay;
        return ($expiration < 1);
    }

    /**
     * Type GETTER
     *
     * Génére une nouvelle clé secrete KEY en method Static
     *
     * return {string}
     */
    public static function makeNewKey()
    {
        return bin2hex(random_bytes(32));
    }

    /****************************************************************
     *                                                              *
     *                                                              *
     *  Methods Privées
     *                                                              *
     *                                                              *
     ****************************************************************/

    /**
     * Type GETTER
     *
     * Etudie le JWT pour voir si il est au bon format.
     * {array} $header
     * {array} $payload - ! Un bug existe parfois $payload devient un object ???
     *
     * no return
     */
    private static function isTrueFormattedToken(array $header, array $payload)
    {
        // Verifie les clés du token JWT et affiche une erreur en cas de mauvais formatage.
        if (!is_array($header) || !is_array($payload) || !isset($header['alg']) || !isset($header['typ']) || !isset($payload['exp']) || !isset($payload['content'])) {
            ApiMisc::http_response_code(400);
            exit;
        }
    }

    /**
     * Type GETTER
     *
     * Génére une signature.
     * {array} $header
     * {array} $payload
     *
     * return {string}
     */
    private static function makeSignature(array $header, array $payload)
    {
        // Génére une signature basée sur le header et le payload hashé avec la KEY + SHA1 UserAgent.
        $concatHeaderPayload = ApiMisc::base64UrlEncode(json_encode($header)) . '.' . ApiMisc::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $concatHeaderPayload, '@' . Config::Jwt()['KEY'] . md5($_SERVER['HTTP_USER_AGENT']) . '@', true);
        $signature .= bin2hex(hash_hmac('md4', $concatHeaderPayload, '@' . sha1($_SERVER['HTTP_USER_AGENT']) . '@', true));
        $signature = ApiMisc::base64UrlEncode($signature);

        return str_replace(
            ['0', '2', '4', '5', '6', '7', '9'],
            ['', '0', '2', '@', '4', '6', '@'],
            $signature
        ); // Remplace les caracteres de la signature par d'autres caractères.
    }

    /**
     * Type GETTER
     * Convertit un type string en tableau associatif
     * et crée un tableau vide en cas d'erreur.
     *
     * return {array}
     */
    private static function convertStringToArray(string $string)
    {
        $arrayProbably = json_decode(base64_decode($string), true);
        return is_array($arrayProbably) ? $arrayProbably : array();
    }
}