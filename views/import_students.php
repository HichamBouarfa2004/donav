<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';
require_once "classes/SimpleXLSX.php";

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);

$class_id = $_GET['class_id'] ?? 0;

// Verify class belongs to teacher

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    
    if (!$classroom->getById($class_id) || $classroom->enseignant_id != $_SESSION['teacher_id']) {
        header('Location: index.php?page=dashboard');
        exit;
    }


    $file = $_FILES['excel_file'];

    if ($file['error'] == 0) {
        $allowed = ['xlsx', 'xls'];
        $filename = $file['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            try {
                $spreadsheet = SimpleXLSX::parse($file['tmp_name']);
                // $worksheet = $spreadsheet->getActiveSheet();
                // $rows = $worksheet->toArray();
                $rows = $spreadsheet->rows();

                $success = 0;
                $failed = 0;

                $rows_count = count($rows);
                $keys       = $rows[0] ?? [];
                $keys       = array_map(fn($r) => strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $r)), $keys);

                
                // Check if required columns exist
                $hasNom = isset(array_flip($keys)['nom']);
                $hasPrenom = isset(array_flip($keys)['prenom']);
                
                if ($hasNom && $hasPrenom) {
                    for ($i = 1; $i < $rows_count; $i++){
                        $row = array_combine($keys, $rows[$i]);

                        // Skip empty rows
                        if (empty(trim($row['nom'])) && empty(trim($row['prenom']))) {
                            continue;
                        }

                        $student->nom = trim($row['nom'] . ' ' . $row['prenom']);
                        $student->classe_id = $class_id;

                        if ($student->create()) {
                            $success++;
                        } else {
                            $failed++;
                        }
                    }
                } else {
                    $missing_fields = [];
                    if (!$hasNom) $missing_fields[] = 'Nom';
                    if (!$hasPrenom) $missing_fields[] = 'Prenom';
                    
                    $error = "Colonnes manquantes dans le fichier Excel : " . implode(', ', $missing_fields) . 
                             ". Veuillez vous assurer que votre fichier contient les colonnes 'Nom' et 'Prenom' en première ligne.";
                }
                // foreach ($rows as $row) {
                //     if (isset($row[0]) && !empty($row[0])) {
                //         $student->nom = $row[0];
                //         $student->classe_id = $class_id;
                        
                //         if ($student->create()) {
                //             $success++;
                //         } else {
                //             $failed++;
                //         }


                //         print_r($row); echo "<br>";
                //     }
                // }

                $message = "Importation réussie: $success élèves ajoutés, $failed échecs.";
            } catch (Exception $e) {
                $error = "Erreur lors du traitement du fichier: " . $e->getMessage();
            }
        } else {
            $error = "Format de fichier non supporté. Utilisez .xlsx ou .xls.";
        }
    } else {
        $error = "Erreur lors de l'upload du fichier.";
    }   
}



require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importer des élèves - No9ati</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 212px; padding-top: 68px; min-height: 100vh;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.1);">
            <div class="container-fluid px-4">
                <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Importer des élèves</h1>
                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>
        
        <main class="container-fluid" style="padding: 28px;">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Importer des élèves</h1>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Importez plusieurs élèves à la fois depuis un fichier Excel</p>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Import Form -->
                <div class="col-12 col-lg-8">
                    <div class="card" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Fichier d'importation</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="classe_id" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Classe de destination</label>
                                    <select name="classe_id" id="classe_id" class="form-select" required style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                        <option value="">Sélectionner une classe</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" 
                                                    <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="excel_file" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Fichier Excel</label>
                                    <input type="file" name="excel_file" id="excel_file" class="form-control" 
                                           accept=".xlsx,.xls" required style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                    <div style="font-size: 12px; color: rgba(0,0,0,0.4); margin-top: 8px;">
                                        Formats acceptés: .xlsx, .xls (maximum 5MB)
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500;">
                                        <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" y1="3" x2="12" y2="15"></line>
                                        </svg>
                                        Importer les élèves
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="col-12 col-lg-4">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Instructions</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <h6 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 12px;">Format du fichier Excel</h6>
                            <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin-bottom: 12px;">
                                Votre fichier Excel doit contenir les colonnes suivantes en première ligne :
                            </p>
                            
                            <div class="table-responsive mb-4">
                                <table class="table table-sm mb-0" style="border-radius: 8px; overflow: hidden; border: 1px solid rgba(0,0,0,0.1);">
                                    <thead>
                                        <tr style="background: rgba(0,0,0,0.04);">
                                            <th style="font-size: 12px; font-weight: 500; color: #1c1c1c; padding: 10px 12px; border: none;">Nom</th>
                                            <th style="font-size: 12px; font-weight: 500; color: #1c1c1c; padding: 10px 12px; border: none;">Prenom</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="font-size: 12px; color: rgba(0,0,0,0.6); padding: 10px 12px; border-bottom: 1px solid rgba(0,0,0,0.1);">Dupont</td>
                                            <td style="font-size: 12px; color: rgba(0,0,0,0.6); padding: 10px 12px; border-bottom: 1px solid rgba(0,0,0,0.1);">Jean</td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 12px; color: rgba(0,0,0,0.6); padding: 10px 12px; border: none;">Martin</td>
                                            <td style="font-size: 12px; color: rgba(0,0,0,0.6); padding: 10px 12px; border: none;">Marie</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <h6 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 12px; display: flex; align-items: center;">
                                <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                Conseils
                            </h6>
                            <ul class="list-unstyled" style="font-size: 12px; color: rgba(0,0,0,0.6);">
                                <li class="mb-2">• Assurez-vous que la première ligne contient les en-têtes</li>
                                <li class="mb-2">• Évitez les cellules vides dans les noms</li>
                                <li class="mb-2">• Les élèves seront ajoutés avec 0 points</li>
                                <li class="mb-0">• Un rapport d'importation sera affiché après traitement</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>