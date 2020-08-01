<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

class HTML
{
    /**
     * Type GETTER
     * Prepare le contenu de la page qui va s'afficher en XML.
     *
     * no return
     */
    public function show(ApiCacheData $api_cache_data)
    {
        header('Content-Type: text/html');

        echo '<style>html{background:#333;color:#fff}fieldset{overflow-wrap:break-word;word-break:break-word}textarea{margin:0;width:100%;resize:none}h1{text-align:center;margin:0;}</style>';
        echo $this->showToken($api_cache_data);

        echo '<fieldset><legend><strong>HTML [' . count($api_cache_data->get()) . ']</strong></legend>';
        echo '<pre>';
        print_r($api_cache_data->get());
        echo '</pre></fieldset>';

        $api_cache_data->reset();
    }

    /**
     * Si un token est present alors l'afficher dans une fieldset differente pour séparer.
     *
     * return {string}
     */
    private function showToken(ApiCacheData $api_cache_data)
    {
        if (isset($api_cache_data->get()['JWT'])) {
            return '<fieldset><legend><strong>TOKEN</strong></legend><textarea rows="5">' . $api_cache_data->get()['JWT'] . '</textarea></fieldset>';
        } else {
            return '';
        }
    }
}