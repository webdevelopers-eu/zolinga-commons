<?php

declare(strict_types=1);

namespace Zolinga\Commons\Upload;

require($_SERVER['DOCUMENT_ROOT'] . "/../system/loader.php");

define("Zolinga\\Commons\\Upload\\ZOLINGA_UPLOAD_ERRORS", [
        0 => dgettext('zolinga-commons', 'File uploaded'),
        1 => sprintf(dgettext('zolinga-commons', 'The uploaded file exceeds the %s MB'), $api->convert->memoryUnits(ini_get('upload_max_filesize'), 'M')),
        2 => dgettext('zolinga-commons', 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
        3 => dgettext('zolinga-commons', 'The uploaded file was only partially uploaded'),
        4 => dgettext('zolinga-commons', 'No file was uploaded'),
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
]);

/**
 * $uploadData = {
 *     "name": "Screenshot from 2024-05-15 15-01-30.png",
 *     "full_path": "Screenshot from 2024-05-15 15-01-30.png",
 *     "type": "image/png",
 *     "tmp_name": "/tmp/phpHCP37S",
 *     "error": 0,
 *     "size": 31494
 * }
 */
try {
    $data = $_FILES['file'];

    if ($data['error'] !== 0) {
        throw new \Exception(sprintf(dgettext("zolinga-commons", "File upload error: %s"), ZOLINGA_UPLOAD_ERRORS[$data['error']]), 500);
    }

    if (!$data['size']) {
        throw new \Exception(dgettext("zolinga-commons", "File is empty"), 400);
    }

    $ret = [
        "id" => $api->uploader->upload($data['tmp_name'], $data['name'], $data['type']),
        "status" => 200,
        "message" => ZOLINGA_UPLOAD_ERRORS[0]
    ];

    header('Content-Type: application/json');
    echo json_encode($ret);
} catch (\Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode([
        "message" => $e->getMessage(),
        "status" => $e->getCode()
    ]);
}
