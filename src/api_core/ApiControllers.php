<?php
require_once 'SecurityIncludeIdentifier.php'; // Empeche le chargement de cette page en dehors de api.php

/****************************************************************
 *                                                              *
 *  5b.Controlleur pour les authentifiés
 *                                                              *
 ****************************************************************/

/**
 * Ajoute à l'API les applications qui seront chargées,
 * l'API n'est concu que pour la communication Web <=> API Rest, et
 * la sécurité des communications
 *
 * Se réferer à la documentation.
 *
 * A partir d'ici, les communications doivent etre securisés, authentifiées avec un token & auth logged
 *
 * Ici je configure mes routes qui vont pointer sur mes Class.
 * Recommandé de pointer sur un router pour mieux rediriger les routes.
 *
 * Si la method $api_authentification->isLogged() vaut true c'est que la communication avec la base de données est autorisé
 * et le client est connecté.
 */

foreach (new DirectoryIterator("controllers/") as $file) {
    if ($file->isDot() || $file->getExtension() !== 'php') {
        continue;
    }

    ApiMisc::instanceClass($file->getBasename('.php'), 'router', false, false);
}