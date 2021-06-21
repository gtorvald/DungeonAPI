<?php

/*
 * Получение от пользователей команды перехода между комнатами.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once "../config/database.php";
include_once "../objects/user.php";

$database = new Database();
$db = $database->getDBConnection();
$user = new User($db, $database->getRoomsTable(), $database->getUserTable(), $database->getHistoryTable());

$data = json_decode(file_get_contents("php://input"));
if (!empty($data->user_id) && !empty($data->route)) {
    try {
        $user->move($data->route, $data->user_id);
        http_response_code(200);
        echo json_encode(array("message" => $user->getMessage()), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(array("message" => $e->getMessage()), JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Incorrect movement"), JSON_UNESCAPED_UNICODE);
}