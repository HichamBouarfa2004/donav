<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';
require_once 'classes/Controle.php';
require_once 'classes/Team.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);
$controle = new Controle($db);
$team = new Team($db);

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_classes = count($classes);
$total_students = 0;
$class_details = [];

foreach ($classes as $class) {
    $sc = $classroom->getStudentCount($class['id']);
    $total_students += $sc;
    $class_details[] = array_merge($class, ['student_count' => $sc]);
}

// Today's absences
$today = date('Y-m-d');
$today_absences = 0;
$today_late = 0;
$today_justified = 0;
$today_submitted_classes = 0;
foreach ($classes as $c) {
    $sub_q = $db->prepare("SELECT id FROM absence_submissions WHERE class_id = :cid AND submission_date = :d");
    $sub_q->execute([':cid' => $c['id'], ':d' => $today]);
    if ($sub_q->fetch()) {
        $today_submitted_classes++;
        $abs_q = $db->prepare("SELECT statut FROM student_absences WHERE class_id = :cid AND absence_date = :d");
        $abs_q->execute([':cid' => $c['id'], ':d' => $today]);
        while ($row = $abs_q->fetch(PDO::FETCH_ASSOC)) {
            if ($row['statut'] === 'absent') $today_absences++;
            elseif ($row['statut'] === 'late') $today_late++;
            elseif ($row['statut'] === 'justified') $today_justified++;
        }
    }
}

// Evaluations stats  
$instances = $controle->getInstancesByTeacher($_SESSION['teacher_id']);
$total_evaluations = count($instances);
$locked_evaluations = 0;
$recent_instances = array_slice($instances, 0, 5);
foreach ($instances as $inst) {
    if ($inst['is_locked']) $locked_evaluations++;
}

// Teams count
$total_teams = 0;
foreach ($classes as $c) {
    $team_q = $db->prepare("SELECT COUNT(*) FROM teams WHERE class_id = :cid");
    $team_q->execute([':cid' => $c['id']]);
    $total_teams += $team_q->fetchColumn();
}

// French day/month
$days_fr = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$months_fr = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$today_label = $days_fr[date('w')] . ' ' . date('j') . ' ' . $months_fr[intval(date('n'))] . ' ' . date('Y');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - No9ati</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background: #f8f9fb; }
        .dash-card { border-radius: 16px; border: 1px solid rgba(0,0,0,0.06); background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.02); transition: box-shadow 0.2s; }
        .dash-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .stat-card { padding: 14px 12px; border-radius: 14px; border: 1px solid rgba(0,0,0,0.06); background: #fff; text-align: center; transition: transform 0.15s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
        .stat-card .stat-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-size: 18px; }
        .stat-card .stat-num { font-size: 24px; font-weight: 700; line-height: 1.1; }
        .stat-card .stat-label { font-size: 12px; color: rgba(0,0,0,0.4); margin-top: 4px; font-weight: 500; }
        .quick-link { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; text-decoration: none; color: #1c1c1c; border: 1px solid rgba(0,0,0,0.06); transition: all 0.15s; background: #fff; }
        .quick-link:hover { border-color: #1c1c1c; color: #1c1c1c; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .quick-link .ql-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .eval-item { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid rgba(0,0,0,0.04); }
        .eval-item:last-child { border-bottom: none; }
        .eval-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .class-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 10px; transition: background 0.1s; }
        .class-row:hover { background: rgba(0,0,0,0.02); }
        .class-avatar { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
    </style>
</head>
<body data-real-time-updates="true" style="background: #f8f9fb;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="padding-top: 78px;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.08);">
            <div class="container-fluid px-4">
                <button class="btn d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" style="background: rgba(0,0,0,0.04); border: none; border-radius: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1c1c1c" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div>
                    <h1 style="font-size: 15px; font-weight: 600; color: #1c1c1c; margin: 0; line-height: 1.2;">Tableau de bord</h1>
                    <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;"><?php echo $today_label; ?></p>
                </div>
                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>
        
        <main class="container-fluid" style="padding: 16px;">
            <!-- Welcome -->
            <div class="mb-3">
                <h2 style="font-size: 20px; font-weight: 700; color: #1c1c1c; margin-bottom: 2px;">Bonjour, <?php echo htmlspecialchars($_SESSION['teacher_name'] ?? 'Utilisateur'); ?> 👋</h2>
                <p style="font-size: 13px; color: rgba(0,0,0,0.45); margin: 0;">Voici un aperçu de votre activité.</p>
            </div>

            <!-- Stat Cards Row -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(99,102,241,0.1); color: #6366f1;"><i class="bi bi-folder2-open"></i></div>
                        <div class="stat-num" style="color: #1c1c1c;"><?php echo $total_classes; ?></div>
                        <div class="stat-label">Classes</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34,197,94,0.1); color: #22c55e;"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-num" style="color: #1c1c1c;"><?php echo $total_students; ?></div>
                        <div class="stat-label">Élèves</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: #f59e0b;"><i class="bi bi-journal-check"></i></div>
                        <div class="stat-num" style="color: #1c1c1c;"><?php echo $total_evaluations; ?></div>
                        <div class="stat-label">Évaluations</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;"><i class="bi bi-people"></i></div>
                        <div class="stat-num" style="color: #1c1c1c;"><?php echo $total_teams; ?></div>
                        <div class="stat-label">Équipes</div>
                    </div>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <!-- Today's Attendance Summary -->
                <div class="col-md-6">
                    <div class="dash-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 style="font-weight: 600; font-size: 14px; margin: 0;"><i class="bi bi-clock-history me-2 opacity-50"></i>Absences aujourd'hui</h6>
                            <a href="index.php?page=absences" style="font-size: 12px; text-decoration: none; color: rgba(0,0,0,0.4);">Voir tout →</a>
                        </div>
                        <?php if ($today_submitted_classes > 0): ?>
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <div style="padding: 8px 6px; border-radius: 10px; background: rgba(239,68,68,0.06); text-align: center;">
                                        <div style="font-size: 20px; font-weight: 700; color: #ef4444;"><?php echo $today_absences; ?></div>
                                        <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Absents</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div style="padding: 8px 6px; border-radius: 10px; background: rgba(245,158,11,0.06); text-align: center;">
                                        <div style="font-size: 20px; font-weight: 700; color: #f59e0b;"><?php echo $today_late; ?></div>
                                        <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Retards</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div style="padding: 8px 6px; border-radius: 10px; background: rgba(99,102,241,0.06); text-align: center;">
                                        <div style="font-size: 20px; font-weight: 700; color: #6366f1;"><?php echo $today_justified; ?></div>
                                        <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Justifiés</div>
                                    </div>
                                </div>
                            </div>
                            <div style="font-size: 12px; color: rgba(0,0,0,0.35);">
                                <i class="bi bi-check-circle me-1" style="color: #22c55e;"></i>
                                <?php echo $today_submitted_classes; ?>/<?php echo $total_classes; ?> classe(s) pointée(s) aujourd'hui
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-clipboard-check" style="font-size: 32px; color: rgba(0,0,0,0.1);"></i>
                                <p style="font-size: 13px; color: rgba(0,0,0,0.35); margin: 8px 0 0;">Aucune classe pointée aujourd'hui.</p>
                                <?php if ($total_classes > 0): ?>
                                    <a href="index.php?page=absences&class_id=<?php echo $classes[0]['id']; ?>" class="btn btn-sm mt-2" style="background: #1c1c1c; color: #fff; border-radius: 8px; font-size: 12px;">Pointer maintenant</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-md-6">
                    <div class="dash-card p-3 h-100">
                        <h6 style="font-weight: 600; font-size: 14px; margin: 0 0 10px;"><i class="bi bi-lightning-charge me-2 opacity-50"></i>Actions rapides</h6>
                        <div class="d-flex flex-column gap-2">
                            <a href="index.php?page=absences" class="quick-link">
                                <div class="ql-icon" style="background: rgba(239,68,68,0.08); color: #ef4444;"><i class="bi bi-clipboard-check"></i></div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600;">Pointer les absences</div>
                                    <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Marquer les présences du jour</div>
                                </div>
                            </a>
                            <a href="index.php?page=controles&tab=evaluations" class="quick-link">
                                <div class="ql-icon" style="background: rgba(245,158,11,0.08); color: #f59e0b;"><i class="bi bi-journal-check"></i></div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600;">Saisir des notes</div>
                                    <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Gérer les évaluations et notes</div>
                                </div>
                            </a>
                            <a href="index.php?page=certificates" class="quick-link">
                                <div class="ql-icon" style="background: rgba(34,197,94,0.08); color: #22c55e;"><i class="bi bi-award"></i></div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600;">Générer des certificats</div>
                                    <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Télécharger les attestations</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-2">
                <!-- Recent Evaluations -->
                <div class="col-md-7">
                    <div class="dash-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 style="font-weight: 600; font-size: 14px; margin: 0;"><i class="bi bi-journal-text me-2 opacity-50"></i>Évaluations récentes</h6>
                            <a href="index.php?page=controles&tab=evaluations" style="font-size: 12px; text-decoration: none; color: rgba(0,0,0,0.4);">Voir tout →</a>
                        </div>
                        <?php if (empty($recent_instances)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-journal-x" style="font-size: 32px; color: rgba(0,0,0,0.1);"></i>
                                <p style="font-size: 13px; color: rgba(0,0,0,0.35); margin: 8px 0 0;">Aucune évaluation créée.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_instances as $inst):
                                $inst_stats = $controle->getStatistics($inst['id']);
                                $is_complete = $inst_stats && $inst_stats['ungraded_count'] == 0 && $inst_stats['graded_count'] > 0;
                                $pass_pct = $inst_stats && $inst_stats['graded_count'] > 0 ? round(($inst_stats['passed'] / $inst_stats['graded_count']) * 100) : null;
                            ?>
                                <div class="eval-item">
                                    <div class="eval-dot" style="background: <?php echo $inst['is_locked'] ? '#6366f1' : ($is_complete ? '#22c55e' : '#f59e0b'); ?>;"></div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($inst['template_title']); ?></div>
                                        <div style="font-size: 11px; color: rgba(0,0,0,0.4);">
                                            <?php echo htmlspecialchars($inst['class_name']); ?>
                                            <?php if (!empty($inst['session_name'])): ?> · <?php echo htmlspecialchars($inst['session_name']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($pass_pct !== null): ?>
                                            <span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: <?php echo $pass_pct >= 50 ? 'rgba(34,197,94,0.1)' : 'rgba(239,68,68,0.08)'; ?>; color: <?php echo $pass_pct >= 50 ? '#16a34a' : '#dc2626'; ?>;">
                                                <?php echo $pass_pct; ?>%
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($inst['is_locked']): ?>
                                            <i class="bi bi-lock-fill" style="font-size: 12px; color: rgba(0,0,0,0.2);"></i>
                                        <?php endif; ?>
                                        <a href="index.php?page=controle_instance_results&id=<?php echo $inst['id']; ?>" style="font-size: 11px; text-decoration: none; color: #6366f1;">Résultats</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="d-flex gap-3 mt-3 pt-2" style="border-top: 1px solid rgba(0,0,0,0.04); font-size: 12px; color: rgba(0,0,0,0.4);">
                                <span><strong style="color: #1c1c1c;"><?php echo $total_evaluations; ?></strong> totales</span>
                                <span><strong style="color: #6366f1;"><?php echo $locked_evaluations; ?></strong> verrouillées</span>
                                <span><strong style="color: #22c55e;"><?php echo $total_evaluations - $locked_evaluations; ?></strong> en cours</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Classes -->
                <div class="col-md-5">
                    <div class="dash-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 style="font-weight: 600; font-size: 14px; margin: 0;"><i class="bi bi-folder2 me-2 opacity-50"></i>Mes classes</h6>
                            <a href="index.php?page=manage_classes" style="font-size: 12px; text-decoration: none; color: rgba(0,0,0,0.4);">Gérer →</a>
                        </div>
                        <?php if (empty($class_details)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-folder-plus" style="font-size: 32px; color: rgba(0,0,0,0.1);"></i>
                                <p style="font-size: 13px; color: rgba(0,0,0,0.35); margin: 8px 0 12px;">Aucune classe.</p>
                                <a href="index.php?page=manage_classes" class="btn btn-sm" style="background: #1c1c1c; color: #fff; border-radius: 8px; font-size: 12px;">Créer une classe</a>
                            </div>
                        <?php else: ?>
                            <?php 
                            $cl_colors = ['#6366f1','#3b82f6','#8b5cf6','#ec4899','#f59e0b','#10b981','#ef4444','#14b8a6'];
                            foreach ($class_details as $ci => $cd): 
                                $cc = $cl_colors[$ci % count($cl_colors)];
                            ?>
                                <a href="index.php?page=class_view&id=<?php echo $cd['id']; ?>" class="class-row text-decoration-none" style="color: #1c1c1c;">
                                    <div class="class-avatar" style="background: <?php echo $cc; ?>15; color: <?php echo $cc; ?>;">
                                        <?php echo strtoupper(mb_substr($cd['nom'], 0, 2)); ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($cd['nom']); ?></div>
                                    </div>
                                    <span style="font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 8px; background: rgba(99,102,241,0.08); color: #6366f1;"><?php echo $cd['student_count']; ?> élèves</span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/pwa.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.no9atiApp = new No9atiApp();
        });
    </script>
</body>
</html>