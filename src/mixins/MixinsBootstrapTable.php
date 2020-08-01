<?php
// Empeche le chargement de cette page en dehors de api.php
if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}

trait MixinsBootstrapTable
{
    /**
     * Type GETTER
     * Récupère les champs et les nettoient
     *
     * return {array}
     */
    private function tableCheckInput()
    {
        $REQ_DATA = ApiMisc::REQ_DATA();

        $input = array(
            'perPage' => isset($REQ_DATA['perPage']) ? intval($REQ_DATA['perPage']) : 5,
            'currentPage' => isset($REQ_DATA['currentPage']) ? intval($REQ_DATA['currentPage'] - 1) : 0,
            'filter' => isset($REQ_DATA['filter']) ? ApiMisc::sanitize_string($REQ_DATA['filter']) : '',
            'sortBy' => isset($REQ_DATA['sortBy']) ? ApiMisc::sanitize_string($REQ_DATA['sortBy']) : '',
            'sortDesc' => isset($REQ_DATA['sortDesc']) ? $REQ_DATA['sortDesc'] : '',
        );

        $input['currentPage'] = $input['currentPage'] * $input['perPage'];

        return $input;
    }

    /**
     * Type GETTER
     * Récupère une requete orderBy pour MySQL
     *
     * return {string}
     */
    private function tableOrderBy()
    {
        $check_input = $this->tableCheckInput();

        $orderBy = $check_input['sortBy'] !== '' ? "ORDER BY `" . $check_input['sortBy'] . "` " . ($check_input['sortDesc'] === 'true' ? 'DESC' : 'ASC') . " " : "";

        return $orderBy;
    }

    /**
     * Type GETTER
     * Compose la requete WHERE
     *
     * return {string} || retourne '' si tableau vide
     */
    private function tableFilterSearch(array $sql_keys, bool $where_permanent = false)
    {
        $req = '';
        if (count($sql_keys)) {
            $req .= $where_permanent ? '' : ' WHERE ';
            $map_keys = array_map(function ($value) {
                return $value . ' LIKE :filter';
            }, $sql_keys);

            $req .= implode(' OR ', $map_keys);
        }

        return $req;
    }
}