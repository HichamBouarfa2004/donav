<?php
class ClassActivity {
    private $conn;
    private $table_name = "class_activities";

    public $id;
    public $class_id;
    public $title;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET class_id = :class_id, title = :title";
        $stmt = $this->conn->prepare($query);

        $this->title = htmlspecialchars(strip_tags($this->title));
        $stmt->bindParam(':class_id', $this->class_id);
        $stmt->bindParam(':title', $this->title);

        return $stmt->execute();
    }

    public function getByClass($class_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE class_id = :class_id ORDER BY title ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function delete($id, $class_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND class_id = :class_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':class_id', $class_id);
        return $stmt->execute();
    }
}

?>
