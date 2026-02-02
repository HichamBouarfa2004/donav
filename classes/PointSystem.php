<?php
class PointSystem {
    private $conn;
    private $table_name = "journal_points";

    public $id;
    public $eleve_id;
    public $points_ajoutes;
    public $raison;
    public $date_ajout;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByStudent($student_id, $limit = 50) {
        $query = "SELECT jp.*, e.nom as eleve_nom 
                  FROM " . $this->table_name . " jp 
                  JOIN eleves e ON jp.eleve_id = e.id 
                  WHERE jp.eleve_id = :student_id 
                  ORDER BY jp.date_ajout DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    public function getByClass($class_id, $limit = 100) {
        $query = "SELECT jp.*, e.nom as eleve_nom 
                  FROM " . $this->table_name . " jp 
                  JOIN eleves e ON jp.eleve_id = e.id 
                  WHERE e.classe_id = :class_id 
                  ORDER BY jp.date_ajout DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    public function getByTeacher($teacher_id, $limit = 100) {
        $query = "SELECT jp.*, e.nom as eleve_nom, c.nom as classe_nom 
                  FROM " . $this->table_name . " jp 
                  JOIN eleves e ON jp.eleve_id = e.id 
                  JOIN classes c ON e.classe_id = c.id 
                  WHERE c.enseignant_id = :teacher_id 
                  ORDER BY jp.date_ajout DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    public function getTotalPointsByClass($class_id) {
        $query = "SELECT SUM(points_ajoutes) as total 
                  FROM " . $this->table_name . " jp 
                  JOIN eleves e ON jp.eleve_id = e.id 
                  WHERE e.classe_id = :class_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] ?: 0;
    }

    public function getRecentActivity($teacher_id, $days = 7) {
        $query = "SELECT jp.*, e.nom as eleve_nom, c.nom as classe_nom 
                  FROM " . $this->table_name . " jp 
                  JOIN eleves e ON jp.eleve_id = e.id 
                  JOIN classes c ON e.classe_id = c.id 
                  WHERE c.enseignant_id = :teacher_id 
                  AND jp.date_ajout >= DATE_SUB(NOW(), INTERVAL :days DAY) 
                  ORDER BY jp.date_ajout DESC 
                  LIMIT 20";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacher_id);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }
}
?>