<?php

/*
 * Получение от пользователей команды на использование новой карты.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once "../config/database.php";
include_once "../objects/map.php";

$database = new Database();
$db = $database->getDBConnection();
$map = new Map($db);

$data = json_decode(file_get_contents("php://input"));
if (
    !empty($data->height) &&
    !empty($data->width) &&
    !empty($data->rooms) &&
    !empty($data->rooms_types) &&
    !empty($data->end) &&
    !empty($data->user_id)
) {
    try {
        $map->useNewMap($data->height, $data->width, $data->rooms, $data->rooms_types, $data->end);
        $map->mapToDb($database->getUserTable(), $database->getRoomsTable(), $data->user_id);
        http_response_code(200);
        echo json_encode(array("message" => "Map is downloaded, user $data->user_id is in start room."), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(array("message" => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Incorrect map"), JSON_UNESCAPED_UNICODE);
}