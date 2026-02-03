<?php
class Student {
    private $conn;
    private $table_name = "eleves";

    public $id;
    public $nom;
    public $classe_id;
    public $points;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET nom=:nom, classe_id=:classe_id, points=:points";

        $stmt = $this->conn->prepare($query);

        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->points = $this->points ?: 0;

        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":classe_id", $this->classe_id);
        $stmt->bindParam(":points", $this->points);

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
                  ORDER BY points DESC, nom ASC";

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
            $this->points = $row['points'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET nom = :nom, points = :points 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->nom = htmlspecialchars(strip_tags($this->nom));

        $stmt->bindParam(':nom', $this->nom);
        $stmt->bindParam(':points', $this->points);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function delete() {
        try {
            $this->conn->beginTransaction();

            // First, delete all points history for this student
            $delete_points_query = "DELETE FROM journal_points WHERE eleve_id = :eleve_id";
            $stmt = $this->conn->prepare($delete_points_query);
            $stmt->bindParam(':eleve_id', $this->id);
            $stmt->execute();

            // Then delete the student
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);
            $result = $stmt->execute();

            $this->conn->commit();
            return $result;

        } catch(Exception $e) {
            error_log('Delete student error: ' . $e->getMessage());
            $this->conn->rollback();
            return false;
        }
    }

    public function addPoints($points, $reason, $activity_id = null) {
        try {
            $this->conn->beginTransaction();

            // Get current activity-specific points
            $current_activity_points = $this->getPointsForActivity($activity_id);
            
            // Check if activity points would exceed 100
            if ($current_activity_points + $points > 100) {
                $points = 100 - $current_activity_points;
            }
            
            // Don't add points if already at 100 for this activity
            if ($current_activity_points >= 100) {
                $this->conn->rollback();
                return true;
            }

            // Log the points addition (with activity_id)
            $log_query = "INSERT INTO journal_points 
                          SET eleve_id=:eleve_id, points_ajoutes=:points, raison=:raison, activity_id=:activity_id";

            $log_stmt = $this->conn->prepare($log_query);
            $log_stmt->bindParam(':eleve_id', $this->id);
            $log_stmt->bindParam(':points', $points);
            $log_stmt->bindParam(':raison', $reason);
            $log_stmt->bindParam(':activity_id', $activity_id);
            $log_stmt->execute();

            // Update total points (sum of all activities)
            $this->updateTotalPoints();

            $this->conn->commit();
            return true;

        } catch(Exception $e) {
            error_log('AddPoints error: ' . $e->getMessage());
            $this->conn->rollback();
            return false;
        }
    }

    public function getPointsForActivity($activity_id = null) {
        if ($activity_id === null) {
            // Get points for general activities (no specific activity)
            $query = "SELECT COALESCE(SUM(points_ajoutes), 0) as total_points 
                      FROM journal_points 
                      WHERE eleve_id = :eleve_id AND activity_id IS NULL";
        } else {
            // Get points for specific activity
            $query = "SELECT COALESCE(SUM(points_ajoutes), 0) as total_points 
                      FROM journal_points 
                      WHERE eleve_id = :eleve_id AND activity_id = :activity_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':eleve_id', $this->id);
        if ($activity_id !== null) {
            $stmt->bindParam(':activity_id', $activity_id);
        }
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($row['total_points']);
    }

    private function updateTotalPoints() {
        // Update the main points field to be the sum of all activity points
        $query = "UPDATE " . $this->table_name . " 
                  SET points = (
                      SELECT COALESCE(SUM(points_ajoutes), 0) 
                      FROM journal_points 
                      WHERE eleve_id = :eleve_id
                  ) 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':eleve_id', $this->id);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        // Refresh the points value
        $this->getById($this->id);
    }

    public function getActivityBreakdown() {
        $query = "SELECT 
                    ca.id,
                    ca.title,
                    COALESCE(SUM(jp.points_ajoutes), 0) as points,
                    COUNT(jp.id) as entries_count
                  FROM class_activities ca
                  LEFT JOIN journal_points jp ON ca.id = jp.activity_id AND jp.eleve_id = :eleve_id
                  JOIN classes c ON ca.class_id = c.id
                  WHERE c.id = :classe_id
                  GROUP BY ca.id, ca.title
                  
                  UNION ALL
                  
                  SELECT 
                    NULL as id,
                    'General Activities' as title,
                    COALESCE(SUM(jp.points_ajoutes), 0) as points,
                    COUNT(jp.id) as entries_count
                  FROM journal_points jp
                  JOIN eleves e ON jp.eleve_id = e.id
                  WHERE jp.eleve_id = :eleve_id2 AND jp.activity_id IS NULL AND e.classe_id = :classe_id2
                  
                  ORDER BY points DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':eleve_id', $this->id);
        $stmt->bindParam(':classe_id', $this->classe_id);
        $stmt->bindParam(':eleve_id2', $this->id);
        $stmt->bindParam(':classe_id2', $this->classe_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resetPoints() {
        $query = "UPDATE " . $this->table_name . " 
                  SET points = 0 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function getTopStudents($enseignant_id, $limit = 10) {
        $query = "SELECT e.*, c.nom as classe_nom 
                  FROM " . $this->table_name . " e 
                  JOIN classes c ON e.classe_id = c.id 

                  where c.enseignant_id = $enseignant_id
                  
                  ORDER BY e.points DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    public function deleteAllByClass($class_id) {
        try {
            $this->conn->beginTransaction();

            // First, delete all points history for students in this class
            $delete_points_query = "DELETE jp FROM journal_points jp 
                                   JOIN eleves e ON jp.eleve_id = e.id 
                                   WHERE e.classe_id = :class_id";
            
            $stmt = $this->conn->prepare($delete_points_query);
            $stmt->bindParam(':class_id', $class_id);
            $stmt->execute();

            // Then delete all students from the class
            $delete_students_query = "DELETE FROM " . $this->table_name . " 
                                     WHERE classe_id = :class_id";
            
            $stmt = $this->conn->prepare($delete_students_query);
            $stmt->bindParam(':class_id', $class_id);
            $stmt->execute();

            $this->conn->commit();
            return true;

        } catch(Exception $e) {
            error_log('DeleteAllByClass error: ' . $e->getMessage());
            $this->conn->rollback();
            return false;
        }
    }
}
?>