<?php
class Student {
    private $conn;
    private $table_name = "eleves";

    public $id;
    public $nom;
    public $classe_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET nom=:nom, classe_id=:classe_id";

        $stmt = $this->conn->prepare($query);

        $this->nom = htmlspecialchars(strip_tags($this->nom));

        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":classe_id", $this->classe_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function studentExists($nom, $classe_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE LOWER(TRIM(nom)) = LOWER(TRIM(:nom)) AND classe_id = :classe_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':classe_id', $classe_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'] > 0;
    }

    public function getByClass($class_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE classe_id = :class_id 
                  ORDER BY nom ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();

        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->id = $row['id'];
            $this->nom = $row['nom'];
            $this->classe_id = $row['classe_id'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET nom = :nom 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->nom = htmlspecialchars(strip_tags($this->nom));

        $stmt->bindParam(':nom', $this->nom);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    public function deleteAllByClass($class_id) {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE classe_id = :class_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        return $stmt->execute();
    }
}
?>