<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

class XML
{
    /**
     * Type GETTER
     * Prepare le contenu de la page qui va s'afficher en XML.
     *
     * no return
     */
    public function show(ApiCacheData $api_cache_data)
    {
        header('Content-Type: text/xml');
        header('Content-Type: application/xml');
        echo $this->array_to_xml($api_cache_data->get(), new SimpleXMLElement('<api/>'))->asXML();
        $api_cache_data->reset();
    }

    /**
     * Type GETTER
     * Formatte les caractères interdit pour XML
     *
     * return {string}
     */
    private function formatter_xml(string $string)
    {
        return str_replace(['&', '<', '>', '"', '\'', '?'], ['_', '_', '_', '_', '_', '_'], $string);
    }

    /**
     * Type GETTER
     * Convertit un tableau en balise XML.
     *
     * return {object} - balise XMl format
     */
    private function array_to_xml(array $data, SimpleXMLElement $output_xml)
    {
        foreach ($data as $key => $value) {
            if (!in_array(gettype($value), array('array', 'object'))) {
                $output_xml->addChild(gettype($key) === 'string' ? ApiMisc::string_formatter($key, '_', false) : "index_" . ApiMisc::string_formatter($key, '_', false), $this->formatter_xml($value));
            } else {
                $this->array_to_xml($value, $output_xml->addChild(gettype($key) === 'string' ? ApiMisc::string_formatter($key, '_', false) : "index_" . ApiMisc::string_formatter($key, '_', false)));
            }
        }

        return $output_xml;
    }
}