<?php

class Database {
    private $host = "localhost";
    private $db_name = "dungeon_db";
    private $username = "root";
    private $password = "";
    private $user_table = "USERS";
    private $rooms_table = "ROOMS";
    private $history_table = "HISTORY";

    public $conn;

    public function getDBConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO ("mysql:host:$this->host;dbname:$this->db_name", $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch (Throwable $e) {
            echo "Connection to database error\n";
        }
        return $this->conn;
    }

    public function getUserTable(): string {
        return $this->user_table;
    }

    public function getRoomsTable(): string {
        return $this->rooms_table;
    }

    public function getHistoryTable(): string {
        return $this->history_table;
    }
}
