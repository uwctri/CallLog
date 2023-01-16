<?php

try {
    $result = $module->api();
    RestUtility::sendResponse(200, $result, 'json');
} catch (Exception $ex) {
    RestUtility::sendResponse(400, $ex->getMessage());
}
