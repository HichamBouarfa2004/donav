<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);

$message = '';
$error = '';

// Handle absence recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_absence'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $absence_date = trim($_POST['absence_date'] ?? '');
    
    // Verify class belongs to teacher
    if (!$classroom->getById($class_id) || $classroom->enseignant_id != $_SESSION['teacher_id']) {
        $error = "Classe non autorisée.";
    } elseif ($student_id && $absence_date && $student->getById($student_id) && $student->classe_id == $class_id) {
        $query = "INSERT INTO student_absences (student_id, class_id, absence_date, created_by) 
                  VALUES (:student_id, :class_id, :absence_date, :created_by)
                  ON DUPLICATE KEY UPDATE created_at = NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':absence_date', $absence_date);
        $stmt->bindParam(':created_by', $_SESSION['teacher_id']);
        
        if ($stmt->execute()) {
            $message = "Absence marquée avec succès.";
        } else {
            $error = "Erreur lors de l'enregistrement de l'absence.";
        }
    } else {
        $error = "Données invalides.";
    }
}

// Handle absence deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_absence'])) {
    $absence_id = intval($_POST['absence_id'] ?? 0);
    
    if ($absence_id) {
        $query = "DELETE sa FROM student_absences sa 
                  JOIN classes c ON sa.class_id = c.id 
                  WHERE sa.id = :absence_id AND c.enseignant_id = :teacher_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':absence_id', $absence_id);
        $stmt->bindParam(':teacher_id', $_SESSION['teacher_id']);
        
        if ($stmt->execute()) {
            $message = "Absence supprimée avec succès.";
        } else {
            $error = "Erreur lors de la suppression de l'absence.";
        }
    }
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all absences for teacher's classes with filters
$filter_class_id = isset($_GET['filter_class']) ? intval($_GET['filter_class']) : '';
$filter_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

$query = "SELECT sa.*, e.nom as student_name, c.nom as class_name 
          FROM student_absences sa 
          JOIN eleves e ON sa.student_id = e.id 
          JOIN classes c ON sa.class_id = c.id 
          WHERE c.enseignant_id = :teacher_id";

$params = [':teacher_id' => $_SESSION['teacher_id']];

if ($filter_class_id) {
    $query .= " AND sa.class_id = :class_id";
    $params[':class_id'] = $filter_class_id;
}

if ($filter_date) {
    $query .= " AND sa.absence_date = :absence_date";
    $params[':absence_date'] = $filter_date;
}

$query .= " ORDER BY sa.absence_date DESC, e.nom ASC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindParam($key, $params[$key]);
}
$stmt->execute();
$absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Absences - No9ati</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 212px; padding-top: 68px; min-height: 100vh;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.1);">
            <div class="container-fluid px-4">
                <button class="btn d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" style="background: rgba(0,0,0,0.04); border: none; border-radius: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1c1c1c" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Absences</h1>

                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid" style="padding: 28px;">
            <?php if (isset($message) && !empty($message)): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3 align-items-center mb-4">
                <div class="col-12 col-xl-8">
                    <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Absences</h1>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Gérez les absences de vos élèves</p>
                </div>
                <div class="col-12 col-xl-4">
                    <button class="btn" id="showAddAbsenceBtn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 10px 16px; font-size: 14px; width: 100%;">
                        <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Marquer Absent
                    </button>
                </div>
            </div>

            <!-- Add Absence Form -->
            <div class="card" id="addAbsenceCard" style="background: #fff; border-radius: 20px; border: none; margin-bottom: 24px; display: none;">
                <div class="card-body" style="padding: 24px;">
                    <h2 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin-bottom: 20px;">Enregistrer une absence</h2>
                    
                    <form method="POST" action="?page=absences">
                        <input type="hidden" name="record_absence" value="1">
                        
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="absence_class" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Classe</label>
                                <select name="class_id" id="absence_class" class="form-select" required onchange="updateStudentSelect()" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 10px 16px; font-size: 14px;">
                                    <option value="">-- Sélectionner une classe --</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="absence_student" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Élève</label>
                                <select name="student_id" id="absence_student" class="form-select" required style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 10px 16px; font-size: 14px;">
                                    <option value="">-- Sélectionner un élève --</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="absence_date" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Date d'absence</label>
                                <input type="date" name="absence_date" id="absence_date" class="form-control" required style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 10px 16px; font-size: 14px;">
                            </div>

                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn" style="background: #ef4444; color: #fff; border-radius: 8px; padding: 10px 20px; font-size: 14px; flex: 1;">Marquer Absent</button>
                                <button type="button" class="btn" id="cancelAbsenceBtn" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 8px; padding: 10px 20px; font-size: 14px; flex: 1;">Annuler</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filters -->
            <div class="card" style="background: #fff; border-radius: 20px; border: none; margin-bottom: 24px;">
                <div class="card-body" style="padding: 24px;">
                    <form method="GET" action="?page=absences" class="row g-3 align-items-end">
                        <div class="col-12 col-md-6">
                            <label for="filter_class" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Filtrer par classe</label>
                            <select name="filter_class" id="filter_class" class="form-select" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 10px 16px; font-size: 14px;">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $filter_class_id == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="filter_date" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Filtrer par date</label>
                            <input type="date" name="filter_date" id="filter_date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 10px 16px; font-size: 14px;">
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 8px; padding: 8px 16px; font-size: 14px;">Filtrer</button>
                            <a href="?page=absences" class="btn" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 8px; padding: 8px 16px; font-size: 14px; text-decoration: none;">Réinitialiser</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Absences List -->
            <div class="card" style="background: #fff; border-radius: 20px; border: none;">
                <div class="card-body" style="padding: 24px;">
                    <?php if (empty($absences)): ?>
                        <div class="text-center py-5">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.2)" stroke-width="2" style="margin-bottom: 16px;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="6" x2="12" y2="12"></line>
                                <line x1="12" y1="12" x2="15" y2="15"></line>
                            </svg>
                            <p style="color: rgba(0,0,0,0.4); margin-bottom: 0;">Aucune absence enregistrée.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Élève</th>
                                        <th scope="col" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Classe</th>
                                        <th scope="col" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Date</th>
                                        <th scope="col" class="text-end" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($absences as $absence): ?>
                                        <tr>
                                            <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                <span style="font-size: 14px; font-weight: 500; color: #1c1c1c;"><?php echo htmlspecialchars($absence['student_name']); ?></span>
                                            </td>
                                            <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                <span style="font-size: 14px; color: rgba(0,0,0,0.6);"><?php echo htmlspecialchars($absence['class_name']); ?></span>
                                            </td>
                                            <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                <span style="font-size: 14px; color: rgba(0,0,0,0.6);"><?php echo date('d/m/Y', strtotime($absence['absence_date'])); ?></span>
                                            </td>
                                            <td class="text-end" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                <form method="POST" action="?page=absences" class="d-inline" style="margin: 0;">
                                                    <input type="hidden" name="delete_absence" value="1">
                                                    <input type="hidden" name="absence_id" value="<?php echo $absence['id']; ?>">
                                                    <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                                        <svg class="me-1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                        Supprimer
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Get students for selected class
        const classStudentsMap = <?php 
            $map = [];
            foreach ($classes as $c) {
                $students_stmt = $student->getByClass($c['id']);
                $class_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
                $map[$c['id']] = $class_students;
            }
            echo json_encode($map);
        ?>;

        function updateStudentSelect() {
            const classSelect = document.getElementById('absence_class');
            const studentSelect = document.getElementById('absence_student');
            const classId = classSelect.value;
            
            studentSelect.innerHTML = '<option value="">-- Sélectionner un élève --</option>';
            
            if (classId && classStudentsMap[classId]) {
                classStudentsMap[classId].forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = student.nom;
                    studentSelect.appendChild(option);
                });
            }
        }

        // Toggle add absence form
        document.getElementById('showAddAbsenceBtn').addEventListener('click', function() {
            const card = document.getElementById('addAbsenceCard');
            card.style.display = card.style.display === 'none' ? 'block' : 'none';
            if (card.style.display === 'block') {
                document.getElementById('absence_class').focus();
            }
        });

        document.getElementById('cancelAbsenceBtn').addEventListener('click', function() {
            document.getElementById('addAbsenceCard').style.display = 'none';
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
