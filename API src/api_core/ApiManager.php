<?php
require_once 'SecurityIncludeIdentifier.php'; // Empeche le chargement de cette page en dehors de api.php

/****************************************************************
 *                                                              *
 *  1.Section Class/Librairies
 *                                                              *
 ****************************************************************/

// Utilisation d'un chargeur de Class.
spl_autoload_register(function ($ClassName) {
    $security_include_identifier = crc32(mt_rand());

    $api_core = "api_core/" . $ClassName . ".php";
    $dependencies = "api_core/dependencies/" . $ClassName . ".php";
    $views_encoders = "views/" . $ClassName . ".php";
    $mixins = "mixins/" . $ClassName . ".php";
    $controllers = "controllers/" . $ClassName . ".php";

    if (file_exists($api_core)) {
        require_once $api_core;
    } else if (file_exists($dependencies)) {
        require_once $dependencies;
    } else if (file_exists($views_encoders)) {
        require_once $views_encoders;
    } else if (file_exists($mixins)) {
        require_once $mixins;
    } else if (file_exists($controllers)) {
        require_once $controllers;
    }
});

/****************************************************************
 *                                                              *
 *  2.Securité
 *                                                              *
 ****************************************************************/

const ALLOW_REQUEST_METHOD = array('GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS');

// Interdit toutes les autres methodes http qui ne sont pas présentes dans la liste + CORS respecté.
ApiMisc::forbidden_request_method(ALLOW_REQUEST_METHOD);

// Politique CORS - Cross Origin Resource Sharing
ApiMisc::setCorsPolicy(ALLOW_REQUEST_METHOD);

/****************************************************************
 *                                                              *
 *  3.Instanciation des Class Noyau de l'API.
 *                                                              *
 ****************************************************************/
ApiCacheData::__instance_singleton($api_version, $api_last_update, $time_app_exec);
ApiDatabase::__instance_singleton();

/****************************************************************
 *                                                              *
 *  4.Authentification Json Web Token / Auth / Credentiels
 *                                                              *
 ****************************************************************/

ApiToken::__instance_singleton()->router(); // Json Web Token
ApiAuthentification::__instance_singleton()->router(); // Authentification en base de données
ApiCaptcha::__instance_singleton()->router();
ApiSessionActive::__instance_singleton()->router();

/****************************************************************
 *                                                              *
 *  5.Chargeur de modules pour les authentifiés
 *                                                              *
 ****************************************************************/

require_once 'ApiControllers.php'; // Modules utilises dans l'application, dynamique par rapport au projet API Rest PHP

/****************************************************************
 *                                                              *
 *  6.Envoi de Mail
 *                                                              *
 ****************************************************************/

ApiInternalMail::__instance_singleton()->router();

/****************************************************************
 *                                                              *
 *  7.Retour de données finales
 *                                                              *
 ****************************************************************/

ApiEncoders::__instance_singleton()->router(); // Affichge le(s) contenu(s)

/****************************************************************
 *                                                              *
 *  8.Fin
 *                                                              *
 ****************************************************************/