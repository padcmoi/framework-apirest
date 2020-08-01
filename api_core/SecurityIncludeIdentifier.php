<?php

if (!isset($security_include_identifier)) {
    http_response_code(403);
    exit;
}