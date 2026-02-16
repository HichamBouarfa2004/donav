<?php
/**
 * Controle Instance Results - Redesigned
 * Clean results view with stats, rankings, and CSV export
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/Controle.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$controle = new Controle($db);
$student = new Student($db);

$instance_id = intval($_GET['id'] ?? 0);

if ($instance_id <= 0) {
    header('Location: index.php?page=controles&tab=evaluations');
    exit;
}

$instance = $controle->getInstanceById($instance_id);

if (!$instance || $instance['created_by'] != $_SESSION['teacher_id']) {
    header('Location: index.php?page=controles&tab=evaluations');
    exit;
}

// Recompute all results
$controle->computeAllResults($instance_id);

// Get results and stats
$results = $controle->getResults($instance_id);
$stats = $controle->getStatistics($instance_id);
$phases = $controle->getPhases($instance['template_id']);

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="resultats_' . date('Ymd') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    $headers = ['Rang', 'Élève', 'Note Finale', 'Statut'];
    foreach ($phases as $p) {
        $headers[] = $p['title'] . ' (' . $p['points'] . ' pts)';
    }
    fputcsv($output, $headers);
    
    $rank = 0;
    $prev_note = null;
    $graded_index = 0;
    foreach ($results as $r) {
        $is_ungraded = $r['final_note'] < 0;
        if (!$is_ungraded) {
            if ($prev_note !== $r['final_note']) $rank = $graded_index + 1;
            $prev_note = $r['final_note'];
            $graded_index++;
        }
        $status = $is_ungraded ? 'Incomplet' : ($r['final_note'] >= ($instance['total_points'] / 2) ? 'Admis' : 'Non admis');
        $row = [$is_ungraded ? '—' : $rank, $r['student_name'], $is_ungraded ? 'N/A' : $r['final_note'], $status];
        $breakdown = json_decode($r['breakdown_json'], true);
        foreach ($breakdown as $b) {
            if (isset($b['skipped']) && $b['skipped']) {
                $row[] = 'N/A';
            } else {
                $row[] = $b['raw_note'] !== null ? $b['raw_note'] : 'Non noté';
            }
        }
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

$pass_rate = $stats && $stats['graded_count'] > 0 ? round(($stats['passed'] / $stats['graded_count']) * 100) : 0;

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<style>
.res-page .res-card { border: 1px solid rgba(0,0,0,0.06); border-radius: 14px; background: #fff; }
.res-page .stat-block { text-align: center; padding: 16px; border-radius: 12px; background: rgba(0,0,0,0.02); min-width: 100px; }
.res-page .stat-val { font-size: 24px; font-weight: 700; line-height: 1.1; }
.res-page .stat-label { font-size: 11px; color: rgba(0,0,0,0.4); margin-top: 2px; }
.res-page .rate-bar { display: flex; height: 10px; border-radius: 5px; overflow: hidden; }
.res-page .rate-bar .bar-pass { background: #22c55e; }
.res-page .rate-bar .bar-fail { background: #ef4444; }
.res-page .rank-badge { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
.res-page .rank-1 { background: #fef3c7; color: #92400e; }
.res-page .rank-2 { background: rgba(0,0,0,0.06); color: rgba(0,0,0,0.55); }
.res-page .rank-3 { background: rgba(161,98,7,0.1); color: #92400e; }
.res-page .rank-n { background: transparent; color: rgba(0,0,0,0.3); font-weight: 500; }
.res-page .result-row { display: flex; align-items: center; gap: 12px; padding: 10px 16px; font-size: 13px; border-bottom: 1px solid rgba(0,0,0,0.03); }
.res-page .result-row:last-child { border-bottom: none; }
.res-page .result-row:hover { background: rgba(0,0,0,0.01); }
.res-page .note-final { padding: 4px 14px; border-radius: 8px; font-weight: 700; font-size: 14px; }
.res-page .note-pass { background: rgba(34,197,94,0.1); color: #16a34a; }
.res-page .note-fail { background: rgba(239,68,68,0.08); color: #dc2626; }
.res-page .note-na   { background: rgba(0,0,0,0.04); color: rgba(0,0,0,0.3); }
.res-page .phase-note { font-size: 13px; text-align: center; min-width: 60px; }
.res-page .phase-note small { font-size: 10px; color: rgba(0,0,0,0.3); }
</style>

<main class="main-content" style="padding-top: 88px;">
<div class="container-fluid res-page" style="padding: 24px 28px; max-width: 1200px;">

    <!-- Breadcrumb -->
    <nav class="mb-3" style="font-size: 13px;">
        <a href="index.php?page=controles&tab=evaluations" class="text-decoration-none text-muted">Contrôles</a>
        <span class="text-muted mx-1">/</span>
        <span style="color: #1c1c1c; font-weight: 500;">Résultats</span>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h4 class="mb-1" style="font-weight: 700; font-size: 20px;"><?php echo htmlspecialchars($instance['template_title']); ?></h4>
            <div class="d-flex flex-wrap gap-2 align-items-center" style="font-size: 13px; color: rgba(0,0,0,0.5);">
                <span class="badge rounded-pill" style="background: rgba(0,0,0,0.06); color: rgba(0,0,0,0.6); font-weight: 500;"><?php echo htmlspecialchars($instance['class_name']); ?></span>
                <?php if (!empty($instance['year_level_name'])): ?>
                    <span>·</span>
                    <span><?php echo htmlspecialchars($instance['year_level_name']); ?></span>
                <?php endif; ?>
                <span>·</span>
                <span>Sur <?php echo $instance['total_points']; ?> pts</span>
                <?php if ($instance['session_name']): ?>
                    <span>·</span>
                    <span><?php echo htmlspecialchars($instance['session_name']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?page=controle_fill_notes&id=<?php echo $instance_id; ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3" style="font-size: 13px;">
                <i class="bi bi-pencil me-1"></i> Modifier notes
            </a>
            <a href="index.php?page=controle_instance_results&id=<?php echo $instance_id; ?>&export=csv" class="btn btn-dark btn-sm rounded-pill px-3" style="font-size: 13px;">
                <i class="bi bi-download me-1"></i> CSV
            </a>
        </div>
    </div>

    <?php if ($stats): ?>
    <!-- Stats -->
    <div class="d-flex gap-3 mb-4 flex-wrap">
        <div class="stat-block">
            <div class="stat-val"><?php echo $stats['graded_count']; ?><span style="font-size: 13px; font-weight: 400; color: rgba(0,0,0,0.3);">/ <?php echo $stats['total_students']; ?></span></div>
            <div class="stat-label">Notés</div>
        </div>
        <div class="stat-block">
            <div class="stat-val"><?php echo $stats['graded_count'] > 0 ? $stats['average'] : '—'; ?></div>
            <div class="stat-label">Moyenne</div>
        </div>
        <div class="stat-block">
            <div class="stat-val" style="color: #dc2626;"><?php echo $stats['graded_count'] > 0 ? $stats['min'] : '—'; ?></div>
            <div class="stat-label">Min</div>
        </div>
        <div class="stat-block">
            <div class="stat-val" style="color: #16a34a;"><?php echo $stats['graded_count'] > 0 ? $stats['max'] : '—'; ?></div>
            <div class="stat-label">Max</div>
        </div>
        <div class="stat-block">
            <div class="stat-val" style="color: #16a34a;"><?php echo $stats['passed']; ?></div>
            <div class="stat-label">Admis</div>
        </div>
        <div class="stat-block">
            <div class="stat-val" style="color: #dc2626;"><?php echo $stats['failed']; ?></div>
            <div class="stat-label">Non admis</div>
        </div>
    </div>

    <!-- Pass rate bar -->
    <div class="res-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span style="font-size: 13px; font-weight: 600;">Taux de réussite</span>
            <span style="font-size: 13px; font-weight: 700;"><?php echo $pass_rate; ?>%</span>
        </div>
        <div class="rate-bar">
            <div class="bar-pass" style="width: <?php echo $pass_rate; ?>%;"></div>
            <div class="bar-fail" style="width: <?php echo 100 - $pass_rate; ?>%;"></div>
        </div>
        <div class="d-flex justify-content-between mt-2" style="font-size: 11px; color: rgba(0,0,0,0.4);">
            <span><?php echo $stats['passed']; ?> admis (≥ <?php echo $instance['total_points'] / 2; ?>)</span>
            <span><?php echo $stats['failed']; ?> non admis</span>
        </div>
        <?php if ($stats['ungraded_count'] > 0): ?>
            <div class="mt-2 d-flex align-items-center gap-2" style="font-size: 12px; color: #92400e;">
                <i class="bi bi-exclamation-circle"></i>
                <?php echo $stats['ungraded_count']; ?> élève(s) n'ont pas encore été notés dans toutes les phases.
                <a href="index.php?page=controle_fill_notes&id=<?php echo $instance_id; ?>" style="font-size: 12px;">Compléter</a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Results table -->
    <div class="res-card overflow-hidden">
        <div class="d-flex justify-content-between align-items-center px-4 py-3" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
            <h6 class="mb-0" style="font-weight: 600; font-size: 14px;">Détail des notes
                <span style="font-size: 12px; font-weight: 400; color: rgba(0,0,0,0.35); margin-left: 6px;"><?php echo count($results); ?> élève(s)</span>
            </h6>
        </div>

        <?php if (empty($results)): ?>
            <div class="text-center py-5" style="color: rgba(0,0,0,0.35);">
                <i class="bi bi-clipboard-data" style="font-size: 32px;"></i>
                <p class="mb-0 mt-2" style="font-size: 13px;">Aucun résultat. <a href="index.php?page=controle_fill_notes&id=<?php echo $instance_id; ?>">Saisir des notes</a></p>
            </div>
        <?php else: ?>
            <!-- Column headers -->
            <div class="result-row" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(0,0,0,0.3); border-bottom: 1px solid rgba(0,0,0,0.05); background: rgba(0,0,0,0.015); padding: 10px 16px;">
                <div style="width: 28px; flex-shrink: 0;">Rg</div>
                <div style="flex: 2; min-width: 140px;">Élève</div>
                <?php foreach ($phases as $p): ?>
                    <div class="phase-note" style="flex: 1;">
                        <?php echo htmlspecialchars($p['title']); ?>
                        <br><small>/<?php echo $p['points']; ?><?php if($p['grading_mode']==='team') echo ' <i class="bi bi-people"></i>'; ?></small>
                    </div>
                <?php endforeach; ?>
                <div style="width: 80px; flex-shrink: 0; text-align: center;">Final /<?php echo $instance['total_points']; ?></div>
            </div>

            <?php
            $rank = 0;
            $prev_note = null;
            $graded_index = 0;
            foreach ($results as $r):
                $breakdown = json_decode($r['breakdown_json'], true);
                $is_ungraded = $r['final_note'] < 0;
                
                if (!$is_ungraded) {
                    if ($prev_note !== $r['final_note']) $rank = $graded_index + 1;
                    $prev_note = $r['final_note'];
                    $graded_index++;
                }
                
                $passed = !$is_ungraded && $r['final_note'] >= ($instance['total_points'] / 2);
            ?>
                <div class="result-row" style="<?php echo $is_ungraded ? 'background: rgba(245,158,11,0.03);' : ''; ?>">
                    <div style="width: 28px; flex-shrink: 0;">
                        <?php if ($is_ungraded): ?>
                            <div class="rank-badge rank-n">—</div>
                        <?php elseif ($rank <= 3): ?>
                            <div class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></div>
                        <?php else: ?>
                            <div class="rank-badge rank-n"><?php echo $rank; ?></div>
                        <?php endif; ?>
                    </div>

                    <div style="flex: 2; min-width: 140px;">
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($r['student_name']); ?></span>
                        <?php if ($is_ungraded): ?>
                            <span style="padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; background: rgba(245,158,11,0.1); color: #92400e; margin-left: 6px;">Incomplet</span>
                        <?php endif; ?>
                    </div>

                    <?php foreach ($breakdown as $b): ?>
                        <div class="phase-note" style="flex: 1;">
                            <?php if (isset($b['skipped']) && $b['skipped']): ?>
                                <span style="color: rgba(0,0,0,0.2); font-size: 10px;" title="Phase équipe — élève non assigné">N/A</span>
                            <?php elseif ($b['raw_note'] !== null): ?>
                                <strong><?php echo $b['raw_note']; ?></strong>
                            <?php else: ?>
                                <span style="color: rgba(0,0,0,0.2);">—</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div style="width: 80px; flex-shrink: 0; text-align: center;">
                        <?php if ($is_ungraded): ?>
                            <span class="note-final note-na">—</span>
                        <?php else: ?>
                            <span class="note-final <?php echo $passed ? 'note-pass' : 'note-fail'; ?>"><?php echo $r['final_note']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Legend -->
    <div class="d-flex flex-wrap gap-4 mt-3 px-1" style="font-size: 11px; color: rgba(0,0,0,0.4);">
        <span><i class="bi bi-people me-1"></i> Note d'équipe partagée</span>
        <span><span class="note-final note-pass" style="font-size: 10px; padding: 1px 6px;">Vert</span> Réussite ≥ <?php echo $instance['total_points'] / 2; ?></span>
        <span><span class="note-final note-fail" style="font-size: 10px; padding: 1px 6px;">Rouge</span> Échec</span>
        <span style="background: rgba(245,158,11,0.1); color: #92400e; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 600;">Incomplet</span> Notes manquantes
    </div>

</div>
</main>

<?php include 'views/partials/footer.php'; ?>
