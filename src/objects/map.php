<?php /** @noinspection SqlResolve */

class Map {
    private $rooms = [];
    private $start_id = 0;

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /*
     * Проверяет корректность указания уровней всех комнат с сокровищами и монстрами.
     */
    private function checkGoldAndMonstersRooms($rooms_types, $i, $j) {
        foreach ($rooms_types as $cur) {
            if (count($cur) != 3)
                return -1;
            if ($cur[0] == $i + 1 && $cur[1] == $j + 1 && $cur[2] > 0 && $cur[2] < 4) {
                return $cur[2];
            }
        }
        return -1;
    }

    /*
     * Проверяет корректность указанного выхода.
     */
    private function checkEnd($height, $width, $rooms, $end, $i, $j): bool {
        switch ($end) {
            case "up":
                if ($i == 0 || $rooms[$i - 1][$j] != '.')
                    return false;
                break;
            case "down":
                if ($i == $height - 1 || $rooms[$i + 1][$j] != '.')
                    return false;
                break;
            case "left":
                if ($j == 0 || $rooms[$i][$j - 1] != '.')
                    return false;
                break;
            case "right":
                if ($j == $width - 1 || $rooms[$i][$j + 1] != '.')
                    return false;
                break;
            default:
                return false;
        }
        return true;
    }

    /*
     * Проверяет корректность переданной в json карты:
     * 1) натуальныые значения размеров карты;
     * 2) соответствие схемы комнат размерам карты;
     * 3) указание корректных уровней всех комнат с сокровищами и монстрами;
     * 4) возможность нахождения выходной двери там, где она указана;
     * 5) наличие начальной и конечной комнат;
     */
    private function checkCorrectMap(int $height, int $width, $rooms, $rooms_types, $end): bool {
        if ($height < 1 || $width < 1)
            return false;

        for ($i = 0; $i < $height; ++$i) {
            if (strlen($rooms[$i]) != $width)
                return false;
        }

        $was_begin = false;
        $was_end = false;
        for ($i = 0; $i < $height; ++$i) {
            for ($j = 0; $j < $width; ++$j) {
                if ($rooms[$i][$j] == 'g' || $rooms[$i][$j] == 'm') {
                    if ($this->checkGoldAndMonstersRooms($rooms_types, $i, $j) == -1)
                        return false;
                } elseif ($rooms[$i][$j] == 'e') {
                    $was_end = true;
                    if ($this->checkEnd($height, $width, $rooms, $end, $i, $j) == false)
                        return false;
                } elseif ($rooms[$i][$j] == 'b')
                    $was_begin = true;
            }
        }
        return $was_end && $was_begin;
    }

    /**
     * Конфигурирует новую карту для загрузки в базу данных.
     *
     * @throws Exception
     */
    public function useNewMap($height, $width, $rooms, $rooms_types, $end) {

        if ($this->checkCorrectMap($height, $width, $rooms, $rooms_types, $end) == false)
            throw new Exception("Incorrect map");

        for ($i = 0; $i < $height; ++$i) {
            for ($j = 0; $j < $width; ++$j) {
                if ($rooms[$i][$j] != '.') {
                    $cur_id = $height * $i + $j;

                    $up = $i > 0 && $rooms[$i - 1][$j] != '.' ? $i - 1 : -1;
                    $down = $i < $height - 1 && $rooms[$i + 1][$j] != '.' ? $i + 1 : -1;
                    $left = $j > 0 && $rooms[$i][$j - 1] != '.' ? $j - 1 : -1;
                    $right = $j < $width - 1 && $rooms[$i][$j + 1] != '.' ? $j + 1 : -1;

                    $this->rooms = [$cur_id => [
                        "up" => $up,
                        "down" => $down,
                        "left" => $left,
                        "right" => $right,
                        "was_here" => false,
                        "type" => "usual",
                        "level" => 0,
                        "end" => ""
                    ]];

                    switch ($rooms[$i][$j]) {
                        case 'b':
                            $this->rooms[$cur_id]["was_here"] = true;
                            $this->start_id = $cur_id;
                            break;
                        case 'g':
                            $this->rooms[$cur_id]["type"] = "gold";
                            $this->rooms[$cur_id]["level"] = $this->checkGoldAndMonstersRooms($rooms_types, $i, $j);
                            break;
                        case 'm':
                            $this->rooms[$cur_id]["type"] = "monster";
                            $this->rooms[$cur_id]["level"] = $this->checkGoldAndMonstersRooms($rooms_types, $i, $j);
                            break;
                        case 'e':
                            $this->rooms[$cur_id]["end"] = $end;
                    }
                }
            }
        }
    }

    /**
     * Загружает построенную карту в базу данных.
     * Стирает информацию о прошлой карте и текущих пользователях в игре.
     * История с пользователями, прошедшими игру, остается.
     *
     * @throws Exception
     */
    public function mapToDb($users_table, $rooms_table, $user_id) {
        $query = "DELETE FROM ".$users_table;
        $stmt = $this->db->prepare($query);
        if ($stmt->execte() == false)
            throw new Exception("Database connection error");

        $query = "DELETE FROM ".$rooms_table;
        $stmt = $this->db->prepare($query);
        if ($stmt->execute() == false)
            throw new Exception("Database connection error");

        foreach ($this->rooms as $id => $room) {
            $query = "INSERT INTO ".$rooms_table."
            SET
                id=:id, up=:up, down=:down, left=:left, right=:right, was_here=:was_here, type=:type, lvl=:lvl, end=:end";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":id", htmlspecialchars(strip_tags($id)));
            $stmt->bindParam(":up", htmlspecialchars(strip_tags($room["up"])));
            $stmt->bindParam(":down", htmlspecialchars(strip_tags($room["down"])));
            $stmt->bindParam(":left", htmlspecialchars(strip_tags($room["left"])));
            $stmt->bindParam(":right", htmlspecialchars(strip_tags($room["right"])));
            $stmt->bindParam(":was_here", htmlspecialchars(strip_tags($room["was_here"])));
            $stmt->bindParam(":type", htmlspecialchars(strip_tags($room["type"])));
            $stmt->bindParam(":lvl", htmlspecialchars(strip_tags($room["lvl"])));
            $stmt->bindParam(":end", htmlspecialchars(strip_tags($room["end"])));
            if ($stmt->execute() == false)
                throw new Exception("Database connection error");
        }

        $query = "INSERT INTO ".$users_table." SET id=:id room=:room score=:score";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", htmlspecialchars(strip_tags($user_id)));
        $stmt->bindParam(":room", htmlspecialchars(strip_tags($this->start_id)));
        $stmt->bindParam(":score", htmlspecialchars(strip_tags("0")));
        if ($stmt->execute() == false)
            throw new Exception("Database connection error");
    }
}