<?php
class Teacher {
    private $conn;
    private $table_name = "enseignants";

    public $id;
    public $nom;
    public $email;
    public $mot_de_passe;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET nom=:nom, email=:email, mot_de_passe=:mot_de_passe";

        $stmt = $this->conn->prepare($query);

        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->mot_de_passe = password_hash($this->mot_de_passe, PASSWORD_DEFAULT);

        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":mot_de_passe", $this->mot_de_passe);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function login() {
        $query = "SELECT id, nom, email, mot_de_passe FROM " . $this->table_name . " 
                  WHERE email = :email LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row && password_verify($this->mot_de_passe, $row['mot_de_passe'])) {
            $this->id = $row['id'];
            $this->nom = $row['nom'];
            return true;
        }
        return false;
    }

    public function getById($id) {
        $query = "SELECT nom, email FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->id = $id;
            $this->nom = $row['nom'];
            $this->email = $row['email'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET nom = :nom, email = :email 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->nom = htmlspecialchars(strip_tags($this->nom));
        $this->email = htmlspecialchars(strip_tags($this->email));

        $stmt->bindParam(':nom', $this->nom);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    public function updatePassword($new_password) {
        $query = "UPDATE " . $this->table_name . " 
                  SET mot_de_passe = :mot_de_passe 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt->bindParam(':mot_de_passe', $hashed_password);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }
}
?>