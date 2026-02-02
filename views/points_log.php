<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}
require_once 'classes/PointSystem.php';
$database = new Database();
$db = $database->getConnection();
$pointSystem = new PointSystem($db);
$points_log_stmt = $pointSystem->getByTeacher($_SESSION['teacher_id'], 200);
$points_log = $points_log_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Points - No9ati</title>
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
                    <h1 class="h3 mb-1">Historique des Points</h1>
                    <p class="text-muted mb-0">Consultez tous les points attribués à vos élèves</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="h5 mb-0">Historique complet</h2>
                                <small class="text-muted">Dernières 200 entrées</small>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($points_log)): ?>
                                <div class="text-center py-5">
                                    <p class="text-muted mb-0">Aucun point attribué pour le moment.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">Élève</th>
                                                <th scope="col">Classe</th>
                                                <th scope="col" class="text-center">Points</th>
                                                <th scope="col">Raison</th>
                                                <th scope="col" class="text-end">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($points_log as $log): ?>
                                                <tr>
                                                    <td>
                                                        <span class="fw-semibold"><?php echo htmlspecialchars($log['eleve_nom']); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted"><?php echo htmlspecialchars($log['classe_nom']); ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success-subtle text-success-emphasis px-3 py-2">
                                                            +<?php echo $log['points_ajoutes']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($log['raison']); ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($log['date_ajout'])); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
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