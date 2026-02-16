<?php
class Team {
    private $conn;
    private $table_name = "teams";
    private $members_table = "team_members";

    public $id;
    public $name;
    public $class_id;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new team
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (name, class_id) VALUES (:name, :class_id)";
        
        $stmt = $this->conn->prepare($query);
        
        $this->name = htmlspecialchars(strip_tags($this->name));
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':class_id', $this->class_id);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Get team by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->class_id = $row['class_id'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    /**
     * Get all teams for a class
     */
    public function getByClass($class_id) {
        $query = "SELECT t.*, 
                         (SELECT COUNT(*) FROM " . $this->members_table . " WHERE team_id = t.id) as member_count
                  FROM " . $this->table_name . " t
                  WHERE t.class_id = :class_id
                  ORDER BY t.name ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Update team name
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET name = :name WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $this->name = htmlspecialchars(strip_tags($this->name));
        
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    /**
     * Delete a team (students remain in the class but are removed from team)
     */
    public function delete() {
        // Team members are automatically removed due to CASCADE
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        
        return $stmt->execute();
    }

    /**
     * Add a student to a team
     * Returns: true on success, 'already_in_team' if student is in another team, false on error
     */
    public function addMember($student_id) {
        // First verify the student belongs to the same class as the team
        $verify_query = "SELECT e.id FROM eleves e 
                        JOIN " . $this->table_name . " t ON t.class_id = e.classe_id 
                        WHERE e.id = :student_id AND t.id = :team_id";
        
        $stmt = $this->conn->prepare($verify_query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':team_id', $this->id);
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            return 'invalid_student'; // Student not in the same class
        }
        
        // Check if student is already in any team for this class
        $check_query = "SELECT tm.team_id FROM " . $this->members_table . " tm
                       JOIN " . $this->table_name . " t ON tm.team_id = t.id
                       WHERE tm.student_id = :student_id AND t.class_id = (
                           SELECT class_id FROM " . $this->table_name . " WHERE id = :team_id
                       )";
        
        $stmt = $this->conn->prepare($check_query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':team_id', $this->id);
        $stmt->execute();
        
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            if ($existing['team_id'] == $this->id) {
                return 'already_in_this_team';
            }
            return 'already_in_team'; // Student is in another team
        }
        
        // Add student to team
        $query = "INSERT INTO " . $this->members_table . " (team_id, student_id) VALUES (:team_id, :student_id)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':team_id', $this->id);
        $stmt->bindParam(':student_id', $student_id);
        
        return $stmt->execute();
    }

    /**
     * Remove a student from a team (student stays in class)
     */
    public function removeMember($student_id) {
        $query = "DELETE FROM " . $this->members_table . " 
                  WHERE team_id = :team_id AND student_id = :student_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':team_id', $this->id);
        $stmt->bindParam(':student_id', $student_id);
        
        return $stmt->execute();
    }

    /**
     * Move a student from one team to another within the same class
     */
    public function moveMember($student_id, $new_team_id) {
        // Verify both teams belong to the same class
        $verify_query = "SELECT t1.class_id as class1, t2.class_id as class2 
                        FROM " . $this->table_name . " t1, " . $this->table_name . " t2 
                        WHERE t1.id = :team_id AND t2.id = :new_team_id";
        
        $stmt = $this->conn->prepare($verify_query);
        $stmt->bindParam(':team_id', $this->id);
        $stmt->bindParam(':new_team_id', $new_team_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row || $row['class1'] != $row['class2']) {
            return false; // Teams not in same class
        }
        
        // Update the team assignment
        $query = "UPDATE " . $this->members_table . " 
                  SET team_id = :new_team_id 
                  WHERE team_id = :team_id AND student_id = :student_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':new_team_id', $new_team_id);
        $stmt->bindParam(':team_id', $this->id);
        $stmt->bindParam(':student_id', $student_id);
        
        return $stmt->execute();
    }

    /**
     * Get all members of a team
     */
    public function getMembers() {
        $query = "SELECT e.*, tm.joined_at 
                  FROM eleves e 
                  JOIN " . $this->members_table . " tm ON e.id = tm.student_id 
                  WHERE tm.team_id = :team_id 
                  ORDER BY e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':team_id', $this->id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Get students in a class who are not assigned to any team
     */
    public function getUnassignedStudents($class_id) {
        $query = "SELECT e.* FROM eleves e 
                  WHERE e.classe_id = :class_id 
                  AND e.id NOT IN (
                      SELECT tm.student_id FROM " . $this->members_table . " tm
                      JOIN " . $this->table_name . " t ON tm.team_id = t.id
                      WHERE t.class_id = :class_id2
                  )
                  ORDER BY e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':class_id', $class_id);
        $stmt->bindValue(':class_id2', $class_id);
        $stmt->execute();
        
        return $stmt;
    }

    /**
     * Check if team name exists in a class
     */
    public function nameExistsInClass($name, $class_id, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE name = :name AND class_id = :class_id";
        
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':class_id', $class_id);
        
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        
        $stmt->execute();
        
        return $stmt->fetch() !== false;
    }

    /**
     * Get the team a student belongs to in a specific class
     */
    public function getStudentTeam($student_id, $class_id) {
        $query = "SELECT t.* FROM " . $this->table_name . " t
                  JOIN " . $this->members_table . " tm ON t.id = tm.team_id
                  WHERE tm.student_id = :student_id AND t.class_id = :class_id
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get team count for a class
     */
    public function getCountByClass($class_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE class_id = :class_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }

    /**
     * Batch create multiple teams at once
     * Returns array of created team IDs or false on error
     */
    public function batchCreate($names, $class_id) {
        $created = [];
        try {
            $this->conn->beginTransaction();
            $query = "INSERT INTO " . $this->table_name . " (name, class_id) VALUES (:name, :class_id)";
            $stmt = $this->conn->prepare($query);
            
            foreach ($names as $name) {
                $name = htmlspecialchars(strip_tags(trim($name)));
                if (empty($name)) continue;
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':class_id', $class_id);
                $stmt->execute();
                $created[] = $this->conn->lastInsertId();
            }
            
            $this->conn->commit();
            return $created;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * Auto-distribute unassigned students evenly across all teams in a class
     * Returns number of students distributed
     */
    public function autoDistribute($class_id) {
        // Get teams for this class
        $teams_stmt = $this->getByClass($class_id);
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($teams)) return 0;
        
        // Get unassigned students
        $unassigned_stmt = $this->getUnassignedStudents($class_id);
        $unassigned = $unassigned_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($unassigned)) return 0;
        
        // Build array of [team_id => current_count]
        $team_counts = [];
        foreach ($teams as $t) {
            $team_counts[$t['id']] = intval($t['member_count']);
        }
        
        $distributed = 0;
        $query = "INSERT INTO " . $this->members_table . " (team_id, student_id) VALUES (:team_id, :student_id)";
        $stmt = $this->conn->prepare($query);
        
        try {
            $this->conn->beginTransaction();
            
            foreach ($unassigned as $s) {
                // Find team with fewest members
                $min_team_id = array_keys($team_counts, min($team_counts))[0];
                
                $stmt->bindParam(':team_id', $min_team_id);
                $stmt->bindParam(':student_id', $s['id']);
                $stmt->execute();
                
                $team_counts[$min_team_id]++;
                $distributed++;
            }
            
            $this->conn->commit();
            return $distributed;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return 0;
        }
    }

    /**
     * Delete all teams for a class
     */
    public function deleteAllByClass($class_id) {
        // CASCADE will remove team_members automatically
        $query = "DELETE FROM " . $this->table_name . " WHERE class_id = :class_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        return $stmt->execute();
    }

    /**
     * Remove all members from a team (unassign all)
     */
    public function clearMembers() {
        $query = "DELETE FROM " . $this->members_table . " WHERE team_id = :team_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':team_id', $this->id);
        return $stmt->execute();
    }

    /**
     * Get all teams with members for a class in a single query (optimized)
     */
    public function getTeamsWithMembers($class_id) {
        $query = "SELECT t.id as team_id, t.name as team_name, t.created_at as team_created,
                         e.id as student_id, e.nom as student_name, tm.joined_at
                  FROM " . $this->table_name . " t
                  LEFT JOIN " . $this->members_table . " tm ON t.id = tm.team_id
                  LEFT JOIN eleves e ON tm.student_id = e.id
                  WHERE t.class_id = :class_id
                  ORDER BY t.name ASC, e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by team
        $teams = [];
        foreach ($rows as $row) {
            $tid = $row['team_id'];
            if (!isset($teams[$tid])) {
                $teams[$tid] = [
                    'id' => $tid,
                    'name' => $row['team_name'],
                    'created_at' => $row['team_created'],
                    'members' => []
                ];
            }
            if ($row['student_id']) {
                $teams[$tid]['members'][] = [
                    'id' => $row['student_id'],
                    'nom' => $row['student_name'],
                    'joined_at' => $row['joined_at']
                ];
            }
        }
        
        return array_values($teams);
    }
}
?>
