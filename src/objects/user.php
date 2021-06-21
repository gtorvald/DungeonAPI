<?php

class User {
    private $user_id = 0;
    private $user_score = 0;
    private $mess = "";
    private $rooms_table;
    private $users_table;
    private $history_table;

    private $db;

    public function __construct($db, $rooms_table, $users_table, $history_table) {
        $this->db = $db;
        $this->rooms_table = $rooms_table;
        $this->users_table = $users_table;
        $this->history_table = $history_table;
    }

    public function getMessage():string {
        return $this->mess;
    }

    /**
     * Считывает из базы данных id комнаты, в которую пользователь должен перейти.
     *
     * @throws Exception
     */
    private function readNextRoomFromDb($route, $currentRoom) {
        $query = "SELECT ".$route." end FROM ".$this->rooms_table." WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, htmlspecialchars(strip_tags($currentRoom)));
        if ($stmt->execute() == false)
            throw new Exception("Database error");
        if ($stmt->rowCount != 1) {
            $data = $stmt->fetch();
            if ($data["end"] == $route)
                return -1;
            $nextRoom = $data[$route];
            if ($nextRoom == -1)
                throw new Exception("Incorrect movement");
        } else {
            throw new Exception("Database error");
        }
        return $nextRoom;
    }

    /**
     * Считывает из базы данных id комнаты, в которой сейчас находится пользователь.
     *
     * @throws Exception
     */
    private function readCurrentRoomFromDb() {
        $query = "SELECT room FROM ".$this->users_table." WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, htmlspecialchars(strip_tags($this->user_id)));
        if ($stmt->execute() == false)
            throw new Exception("Database error");
        if ($stmt->rowCount != 1) {
            $data = $stmt->fetch();
            $currentRoom = $data["room"];
        } else {
            throw new Exception("Database error");
        }
        return $currentRoom;
    }

    /**
     * Считывает из базы данных информацию о комнате, в которую пользователь должен перейти.
     *
     * @throws Exception
     */
    private function readNextRoomInfoFromDb($room_id) {
        $query = "SELECT was_here type lvl FROM ".$this->rooms_table." WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, htmlspecialchars(strip_tags($room_id)));
        if ($stmt->execute() == false)
            throw new Exception("Database error");
        if ($stmt->rowCount != 1) {
            return $stmt->fetch();
        } else {
            throw new Exception("Database error");
        }
    }

    /**
     * Считывает из базы данных score пользователя.
     *
     * @throws Exception
     */
    private function readUserScoreFromDb() {
        $query = "SELECT score FROM ".$this->users_table." WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, htmlspecialchars(strip_tags($this->user_id)));
        if ($stmt->execute() == false)
            throw new Exception("Database error");
        if ($stmt->rowCount != 1) {
            $data = $stmt->fetch();
            $score = $data["score"];
        } else {
            throw new Exception("Database error");
        }
        return $score;
    }

    /*
     * Обрабатывает сценарий нахождения персонажем сокровищ.
     */
    private function getGoldFromRoom($lvl) {
        switch ($lvl) {
            case 1: $this->user_score += rand(1, 10); break;
            case 2: $this->user_score += rand(11, 20); break;
            case 3: $this->user_score += rand(21, 30);
        }
    }

    /*
     * Обрабатывает сценарий битвы персонажа с монстром.
     */
    private function goBattleWithMonster($lvl) {
        $monsterPower = 0;
        $diffMonsterPower = 0;
        switch ($lvl) {
            case 1:
                $monsterPower = rand(1, 10);
                $diffMonsterPower = 2;
                break;
            case 2:
                $monsterPower = rand(11, 20);
                $diffMonsterPower = 4;
                break;
            case 3:
                $monsterPower = rand(21, 30);
                $diffMonsterPower = 5;
                break;
        }
        $user_power = rand(1, 30);
        while ($user_power <= $monsterPower) {
            $monsterPower = max(0, $monsterPower - $diffMonsterPower);
        }
        $this->user_score += $monsterPower;
    }

    /**
     * Обновляет статус комнаты после ее прохождения (комната становится пустой).
     *
     * @throws Exception
     */
    private function updateRoomStatusInDb($room_id) {
        $query = "UPDATE ".$this->rooms_table." SET was_here = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $newStatus = true;
        $stmt->bindParam(1, htmlspecialchars(strip_tags($newStatus)));
        $stmt->bindParam(2, htmlspecialchars(strip_tags($room_id)));
        if ($stmt->execute() == false)
            throw new Exception("Database error");
    }

    /**
     * Обновляет в базе данных информацию о текущей комнате пользователя и его score.
     *
     * @throws Exception
     */
    private function updateUserInfoInDb($newRoom) {
        $query = "UPDATE ".$this->users_table." SET room = ? score = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, htmlspecialchars(strip_tags($newRoom)));
        $stmt->bindParam(2, htmlspecialchars(strip_tags($this->user_score)));
        $stmt->bindParam(3, htmlspecialchars(strip_tags($this->user_id)));
        if ($stmt->execute() == false)
            throw new Exception("Database error");
    }

    /**
     * Загружает в базу данных id и score пользователя в случае его победы.
     *
     * @throws Exception
     */
    private function userToDbHistory() {
        $query = "INSERT INTO ".$this->history_table." SET user_id=:id, score=:score";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", htmlspecialchars(strip_tags($this->user_id)));
        $stmt->bindParam(":score", htmlspecialchars(strip_tags($this->user_score)));
        if ($stmt->execute() == false)
            throw new Exception("Database error");
    }

    /**
     * Обрабатывает информацию о передвижении пользователя.
     * Обновляет score пользователя.
     * Составялет сообщение о том, что произошло после хода пользователя.
     *
     * @throws Exception
     */
    public function move($route, $id) {
        if ($route != "up" && $route != "down" && $route != "left" && $route != "right")
            throw new Exception("Incorrect movement");
        $this->user_id = $id;
        $this->user_score = $this->readUserScoreFromDb();
        $currentRoom = $this->readCurrentRoomFromDb();
        $nextRoom = $this->readNextRoomFromDb($route, $currentRoom);
        if ($nextRoom == -1) {
            $this->userToDbHistory();
            $this->mess = "User ".$this->user_id." won! Score: ".$this->user_score;
            return ;
        }
        $this->mess = "User $this->user_id went $route to $nextRoom room.";
        $nextRoomInfo = $this->readNextRoomInfoFromDb($nextRoom);
        if ($nextRoomInfo["was_here"] == true) {
            $this->mess = $this->mess." Score: $this->user_score";
            return;
        }
        switch ($nextRoomInfo["type"]) {
            case "gold":$this->getGoldFromRoom($nextRoomInfo["lvl"]); break;
            case "monster": $this->goBattleWithMonster($nextRoomInfo["lvl"]); break;
        }
        $this->mess = $this->mess." Score: $this->user_score";
        $this->updateRoomStatusInDb($nextRoom);
        $this->updateUserInfoInDb($nextRoom);
    }
}
