<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

class JSON
{
    /**
     * Type GETTER
     * Prepare le contenu de la page qui va s'afficher en JSON.
     *
     * no return
     */
    public function show(ApiCacheData $api_cache_data)
    {
        header('Content-Type: application/json');
        echo json_encode($api_cache_data->get(), JSON_UNESCAPED_UNICODE);
        $api_cache_data->reset();
    }
}