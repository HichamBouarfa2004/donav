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
<body style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 212px; padding-top: 68px; min-height: 100vh;">
        <?php include 'views/partials/header.php'; ?>
        
        <main class="container-fluid" style="padding: 28px;">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Historique des Points</h1>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Consultez tous les points attribués à vos élèves</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Historique complet</h2>
                                <small style="font-size: 12px; color: rgba(0,0,0,0.4);">Dernières 200 entrées</small>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($points_log)): ?>
                                <div class="text-center py-5">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.2)" stroke-width="2" style="margin-bottom: 16px;">
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                    </svg>
                                    <p style="color: rgba(0,0,0,0.4); margin: 0;">Aucun point attribué pour le moment.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Élève</th>
                                                <th scope="col" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Classe</th>
                                                <th scope="col" class="text-center" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Points</th>
                                                <th scope="col" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Raison</th>
                                                <th scope="col" class="text-end" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($points_log as $log): ?>
                                                <tr>
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span style="font-size: 14px; font-weight: 500; color: #1c1c1c;"><?php echo htmlspecialchars($log['eleve_nom']); ?></span>
                                                    </td>
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span style="font-size: 14px; color: rgba(0,0,0,0.4);"><?php echo htmlspecialchars($log['classe_nom']); ?></span>
                                                    </td>
                                                    <td class="text-center" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; font-size: 12px; font-weight: 500; padding: 4px 12px; border-radius: 8px;">
                                                            +<?php echo $log['points_ajoutes']; ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1); font-size: 14px; color: #1c1c1c;">
                                                        <?php echo htmlspecialchars($log['raison']); ?>
                                                    </td>
                                                    <td class="text-end" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <small style="font-size: 12px; color: rgba(0,0,0,0.4);">
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