<?php
require_once 'SecurityIncludeIdentifier.php'; // Empeche le chargement de cette page en dehors de api.php

/**
 * Class:           ApiMisc
 * Description:     Contient des fonctions essentielles au bon fonctionnement de l'API.
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         28/04/2020
 */
class ApiMisc
{
    /**
     * Constructeur privé.
     * c'est une Class utilitaire, on refuse qu'elle soit instanciée, ne contient que des méthodes static.
     *
     * no return
     */
    private function __construct()
    {}

    /**
     **
     **  Methods Statique utilitaire
     **
     */
    public static function console(string $data = null, bool $error = false)
    {
        if ($error) {header('Content-Type: text/html');
            echo '<script>console.error(' . json_encode($data, JSON_HEX_TAG) . ')</script>';
        } else {header('Content-Type: text/html');
            echo '<script>console.log(' . json_encode($data, JSON_HEX_TAG) . ')</script>';
        }
        exit;
    }

    public static function writeLog($message = '')
    {
        file_put_contents('console.log', "{$message}\n", FILE_APPEND | LOCK_EX);
    }

    public static function forbidden_request_method($allowed_request_methods = array())
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowed_request_methods)) {
            self::writeJsonMessage("Requete http {$_SERVER['REQUEST_METHOD']} refusée !");
            self::http_response_code(403);
            exit;
        }
    }

    /**
     * Configure la politique CORS - Cross Origin Resource Sharing
     */
    public static function setCorsPolicy($allowed_request_methods = array())
    {
        // error_reporting(0); // Je ne veux pas lister d'erreur php
        // ini_set("display_errors", 0); // Je ne veux pas lister d'erreur php
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: " . implode(', ', $allowed_request_methods));
        header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, X-PINGOTHER');
        header("Access-Control-Max-Age: 3600");
    }

    /**
     * Récupere toutes les routes dans le champs URN.
     * return {array}
     */
    public static function getRoutes()
    {
        return explode("/", substr(@$_SERVER['PATH_INFO'], 1));
    }

    /**
     * Recherche si la route est dans le champs URN.
     * exemple  jwt/authentificate/now
     * return {bool}
     */
    public static function isRouteUsed(string $route)
    {
        return
        empty($route) ? false :
        strstr(substr(@$_SERVER['PATH_INFO'], 1), $route) ? true : false;
    }

    /**
     * Récupere l'eventuel Token JWT contenu dans la requete http.
     *
     * return (string) $jwt - retourne le token en 3 parties au format 'header.payload.signature'.
     */
    public static function getJWT()
    {
        return isset(self::REQ_DATA()['token']) ? self::REQ_DATA()['token'] : '';
    }

    /**
     * Type GETTER
     * Traite les champs envoyés par le client et nettoie.
     *
     * return {string}
     */
    public static function sanitize_string(string $string)
    {
        $string = htmlspecialchars($string);
        $string = trim($string);
        return stripslashes($string);
    }

    /**
     * Type GETTER
     * Retourne les méthods de requêtes
     *
     * return {array}
     */
    private static function REQUEST_METHOD()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                $request = $_GET;
                break;
            case 'POST': // Création/Ajout de données
                $request = $_POST;
                break;
            case 'PUT': // Mise à jour des données
                parse_str(file_get_contents('php://input'), $_PUT);
                $request = $_PUT;
                break;
            case 'DELETE': // Suppression de données
                parse_str(file_get_contents('php://input'), $_DELETE);
                $request = $_DELETE;
                break;
            default:
                $request = [];
        }
        return $request;
    }

    /**
     * Type GETTER
     * Recupère les paramètres de la requete d'envoi et les nettoie pour être utilisé.
     * Stock les données de la requete cliente en cache afin d'eviter de nettoyer indéfiniment
     * no return
     */
    private static $REQUEST_METHOD;
    private static function REQUEST_METHOD_SANITIZE_DATA()
    {
        if (!isset(self::$REQUEST_METHOD)) {
            self::$REQUEST_METHOD = [];
            $request = self::REQUEST_METHOD();

            foreach ($request as $key => $value) {
                self::$REQUEST_METHOD[$key] = self::sanitize_string($value);
            }
        }
    }

    /**
     * Type GETTER
     * Recupère les données de la requête client en cache
     * les données clientes sont deja nettoyé.
     *
     * return {array}
     */
    public static function REQ_DATA($not_sanitize = false)
    {
        if ($not_sanitize) {
            return self::REQUEST_METHOD();
        } else {
            self::REQUEST_METHOD_SANITIZE_DATA();
            return isset(self::$REQUEST_METHOD) ? self::$REQUEST_METHOD : [];
        }
    }

    /**
     * Type GETTER
     * Retourne l'adresse IP de l'utilisateur.
     * à utiliser dans tous les cas, utile si derrière un CDN comme cloudflare
     * qui quand on utilise $_SERVER['REMOTE_ADDR'] retournera l'adresse IP du parefeu au lieu de la vrai IP.
     *
     * return {string} - $ip
     */
    public static function remote_addr()
    {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * Type GETTER
     * Retourne l'adresse web de l'execution du script
     *
     * return {string}
     */
    public static function getFullUrl()
    {
        return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['SCRIPT_NAME'];
    }

    /**
     * Type GETTER
     * Instance une Class de type modulaire si elle existe et retourne son objet
     * pour les instanciation modulaires uniquement.
     *
     * return {object} - Class Object
     *        {bool} - false si instanciation echouée
     */
    public static function instanceClass(string $class, string $method, $defaut_value_if_error = null, bool $must_be_halt_if_error = false)
    {
        if (!class_exists($class)) {
            if ($must_be_halt_if_error) {
                http_response_code(501);
                exit;
            }
            return $defaut_value_if_error;
        }
        if (method_exists($class, '__instance_modular_singleton')) {
            $Obj = $class::__instance_modular_singleton($method);
            return $Obj;
        } else {
            return $defaut_value_if_error;
        }
    }

    /**
     * Formatte au format snake_case
     * optionnel: possible de ne pas changer en minuscules
     */
    public static function string_formatter(string $text = null, string $separator = '', bool $lowercase_change = false)
    {
        $oldLocale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'en_US.UTF-8');
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = $lowercase_change ? strtolower($clean) : $clean;
        $clean = preg_replace("/[\/_|+ -]+/", $separator, $clean);
        $clean = trim($clean, $separator);
        setlocale(LC_ALL, $oldLocale);
        return $clean;
    }

    /**
     * Formatte au format slugify
     */
    public static function slugify($string)
    {
        return self::string_formatter($string, '-', true);
    }

    /**
     * Formatte au format snake_case
     * optionnel: possible de ne pas changer en minuscules
     */
    public static function snake_case(string $string, bool $no_lowercase_change = false)
    {
        return self::string_formatter($string, '_', true);
    }

    /**
     * Convertit un message sous format JSON
     * (String) $message - le message
     *
     * return {error:'le message'}
     */
    public static function writeJsonMessage(string $message)
    {
        echo $message;
    }

    /**
     * Type GETTER
     *
     * Affiche un code de réponse http, en verifiant
     * si celui ci le serveur l'autorise ou non
     *
     * {int} $http_code - Code de reponse HTTP
     *
     * return {bool}  true si une réponse à été donnée
     *                false si aucune réponse n'est donnée
     */
    public static function http_response_code(int $http_code)
    {
        if (!isset(Config::ALLOW_HTTP_RESPONSE_CODE["{$http_code}"])) {
            http_response_code(501);exit;
        }

        if (Config::ALLOW_HTTP_RESPONSE_CODE["{$http_code}"]) {
            http_response_code($http_code);
        }

        return Config::ALLOW_HTTP_RESPONSE_CODE["{$http_code}"] ? true : false;
    }

    /**
     * Type GETTER
     *
     * PHP n'a pas de fonction base64UrlEncode, alors définissons celle qui
     * fait de la magie en remplaçant + par -, / par _ et = par ''.
     * De cette façon, nous pouvons passer la chaîne dans les URL sans
     * tout encodage d'URL.
     *
     * {string} $url
     *
     * return {string}
     */
    public static function base64UrlEncode(string $url)
    {
        return str_replace(['+', '/', '=', '.'], '', base64_encode($url));
    }

    public static function passwordRandomGenerator($size = 14)
    {
        $passwordChar =
            "azertyuiopqsdfghjklmwxcvbnAZERTYUIOPQSDFGHJKLMWXCVBN0123456789";
        $specialChar = "!?.$";
        $insertIndexSpecialChar = mt_rand(0, 10);

        $passwd = "";
        for ($i = 0; $i < $size; $i++) {
            if ($i === $insertIndexSpecialChar) {
                $passwd .= $specialChar[mt_rand(0, strlen($specialChar) - 1)];
            } else {
                $passwd .= $passwordChar[mt_rand(0, strlen($passwordChar) - 1)];
            }
        }
        return $passwd;
    }

    public static function is_sha1($str)
    {
        return (bool) preg_match('/^[0-9a-f]{40}$/i', $str);
    }

    /**
     * Verifie que la date est au format Date MySQL
     *
     * return {bool}
     */
    public static function checkFormatMysqlDate($date, $format = 'Y-m-d')
    {
        $dt = DateTime::createFromFormat($format, $date);
        return $dt && $dt->format($format) === $date;
    }

    /**
     * Type GETTER
     * Récupère l'ID de l'appelant.
     *
     * return {int}
     */
    public static function getMyId()
    {
        $payload = ApiToken::__instance_singleton()->getPayload();

        if (ApiAuthentification::__instance_singleton()->isLogged() && isset($payload['content']) && isset($payload['content']['id'])) {
            $key_id = $payload['content']['id'];
        } else {
            $key_id = 0;
        }

        return intval($key_id);
    }

    /**
     * Type GETTER
     * Génére un GUID
     *
     * return {string}
     */
    public static function createGUID()
    {
        return sprintf('%04X-%04X-%04X-%04X-%04X', mt_rand(0, 32768), mt_rand(32768, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), time() - 1593200000);
    }
}