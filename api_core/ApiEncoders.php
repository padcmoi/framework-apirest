<?php
require_once 'SecurityIncludeIdentifier.php'; // Empeche le chargement de cette page en dehors de api.php

/**
 * Class:           ApiEncoders
 * Description:     Permet le choix de l'encoder et envoyer celui vers l'encodeur désiré, par défaut JSON.
 *
 * Auteur:          Julien JEAN
 * Version:         v1.0.0
 * Crée le:         03/05/2020
 */
class ApiEncoders
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
            self::$instance->instanceOutputEncoders();
            self::$singleton = true;
        }
        return self::$instance;
    }

    /**
     * Type GETTER
     * Instanciation des Class Sources de données de l'API.
     *
     * no return
     */
    private function instanceOutputEncoders()
    {
        self::$instance->output_json = new JSON;
        self::$instance->output_xml = new XML;
        self::$instance->output_html = new HTML;
    }

    public function router()
    {
        $api_cache_data = ApiCacheData::__instance_singleton();

        $routes = array(
            'show' => 'json', // Encoders par defaut
        );

        // Récupère dans le paramètre [GET] api_output le type de données à retourner (par défaut JSON).
        $api_output = $_GET && isset($_GET['api_output']) ? $_GET['api_output'] : 'json';
        switch ($api_output) {
            case 'json':
                $routes['show'] = $api_output;
                break;
            case 'xml':
                $routes['show'] = $api_output;
                break;
            case 'html':
                $routes['show'] = $api_output;
                break;
        }

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET': // Lecture de données
                if (ApiMisc::isRouteUsed('api/info')) {
                    $api_cache_data->getFullInfo();
                }
                break;
        }

        if ($api_output === '') {
            ApiMisc::http_response_code(201);
            $api_cache_data->getInfo();

            $this->show('html', $api_cache_data);
        } else {
            $this->show($routes['show'], $api_cache_data);
        }

    }

    /**
     * Type GETTER
     * Appel la Class correspondante à la variable {string} $show
     * et affiche le contenu à travers cette Class.
     *
     * no return
     */
    private function show(string $show, $api_cache_data)
    {
        switch ($show) {
            case 'json':
                $this->output_json->show($api_cache_data);
                break;
            case 'xml':
                $this->output_xml->show($api_cache_data);
                break;
            case 'html':
                $this->output_html->show($api_cache_data);
                break;
        }
    }
}