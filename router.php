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
        'absences' => 'views/absences.php',
        'manage_classes' => 'views/manage_classes.php',
        'teams' => 'views/teams.php',
        // Old controles routes (redirect to unified hub)
        'controle_edit' => 'views/controles.php',
        'controle_notes' => 'views/controles.php',
        'controle_results' => 'views/controles.php',
        // Unified controles system
        'controles' => 'views/controles.php',
        'controle_templates' => 'views/controles.php',
        'controle_fill_points' => 'views/controles.php',
        'controle_template_edit' => 'views/controle_template_edit.php',
        'controle_fill_notes' => 'views/controle_fill_notes.php',
        'controle_instance_results' => 'views/controle_instance_results.php',
        'certificates' => 'views/certificates.php',
        'absence_history' => 'views/absence_history.php',
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