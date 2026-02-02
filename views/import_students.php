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
<body class="bg-light">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 250px; padding-top: 70px; min-height: 100vh;">
        <?php include 'views/partials/header.php'; ?>
        
        <main class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 mb-1">Importer des élèves</h1>
                    <p class="text-muted mb-0">Importez plusieurs élèves à la fois depuis un fichier Excel</p>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Import Form -->
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h2 class="h5 mb-0">Fichier d'importation</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="classe_id" class="form-label">Classe de destination</label>
                                    <select name="classe_id" id="classe_id" class="form-select" required>
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
                                    <label for="excel_file" class="form-label">Fichier Excel</label>
                                    <input type="file" name="excel_file" id="excel_file" class="form-control" 
                                           accept=".xlsx,.xls" required>
                                    <div class="form-text">
                                        Formats acceptés: .xlsx, .xls (maximum 5MB)
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        📤 Importer les élèves
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h2 class="h5 mb-0">Instructions</h2>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3">Format du fichier Excel</h6>
                            <p class="small text-muted mb-3">
                                Votre fichier Excel doit contenir les colonnes suivantes en première ligne :
                            </p>
                            
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small">Nom</th>
                                            <th class="small">Prenom</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="small">Dupont</td>
                                            <td class="small">Jean</td>
                                        </tr>
                                        <tr>
                                            <td class="small">Martin</td>
                                            <td class="small">Marie</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <h6 class="fw-semibold mb-3">💡 Conseils</h6>
                            <ul class="list-unstyled small text-muted">
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