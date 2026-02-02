<?php
class Router {
    private $routes = [
        '' => 'views/login.php',
        'login' => 'views/login.php',
        'register' => 'views/register.php',
        'dashboard' => 'views/dashboard.php',
        'class_view' => 'views/class_view.php',
        'add_student' => 'views/add_student.php',
        'import_students' => 'views/import_students.php',
        'points_log' => 'views/points_log.php',
        'absences' => 'views/absences.php',
        'generate_certificate' => 'views/generate_certificate.php',
        'manage_classes' => 'views/manage_classes.php',
        'profile' => 'views/profile.php',
        'logout' => 'views/logout.php',
        'error' => 'views/error.php'
    ];

    public function handleRequest() {
        $page = $_GET['page'] ?? '';
        
        if (array_key_exists($page, $this->routes)) {
            // Check authentication for protected pages
            if ($this->requiresAuth($page) && !$this->isAuthenticated()) {
                header('Location: index.php?page=login');
                exit;
            }
            
            include $this->routes[$page];
        } else {
            include 'views/error.php';
        }
    }

    private function requiresAuth($page) {
        $publicPages = ['', 'login', 'register', 'error'];
        return !in_array($page, $publicPages);
    }

    private function isAuthenticated() {
        return isset($_SESSION['teacher_id']);
    }
}
?>