<?php
/**
 * Edit Controle Template - Redesigned
 * Clean inline phase management with visual feedback
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/Controle.php';

$database = new Database();
$db = $database->getConnection();
$controle = new Controle($db);

$template_id = intval($_GET['id'] ?? 0);
if ($template_id <= 0) { header('Location: index.php?page=controles'); exit; }

$template = $controle->getTemplateById($template_id);
if (!$template || $template['created_by'] != $_SESSION['teacher_id']) {
    header('Location: index.php?page=controles');
    exit;
}

$year_levels = $controle->getYearLevels($_SESSION['teacher_id']);
$message = '';
$error = '';

// Handle duplicate
if (isset($_GET['duplicate']) && $_GET['duplicate'] == 1) {
    $new_id = $controle->createTemplate($template['title'] . ' (copie)', $template['year_level_id'], $template['total_points'], $template['description'], $_SESSION['teacher_id']);
    if ($new_id) {
        $phases = $controle->getPhases($template_id);
        foreach ($phases as $phase) {
            $controle->addPhase($new_id, $phase['title'], $phase['points'], $phase['grading_mode']);
        }
        header("Location: index.php?page=controle_template_edit&id=" . $new_id);
        exit;
    }
}

// Handle add phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_phase'])) {
    $title = trim($_POST['phase_title'] ?? '');
    $points = floatval($_POST['points'] ?? 0);
    $grading_mode = $_POST['grading_mode'] ?? 'individual';
    if (!empty($title) && $points > 0) {
        if ($controle->addPhase($template_id, $title, $points, $grading_mode)) {
            $message = "Phase ajoutée.";
        } else { $error = "Erreur lors de l'ajout."; }
    } else { $error = "Titre et points requis."; }
}

// Handle update phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_phase'])) {
    $phase_id = intval($_POST['phase_id'] ?? 0);
    $title = trim($_POST['phase_title'] ?? '');
    $points = floatval($_POST['points'] ?? 0);
    $grading_mode = $_POST['grading_mode'] ?? 'individual';
    if ($controle->updatePhase($phase_id, $title, $points, $grading_mode)) {
        $message = "Phase mise à jour.";
    } else { $error = "Erreur lors de la mise à jour."; }
}

// Handle delete phase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_phase'])) {
    $phase_id = intval($_POST['phase_id'] ?? 0);
    if ($controle->deletePhase($phase_id)) {
        $message = "Phase supprimée.";
    } else { $error = "Erreur lors de la suppression."; }
}

// Handle update template details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_template'])) {
    $title = trim($_POST['title'] ?? $template['title']);
    $year_level_id = intval($_POST['year_level_id'] ?? $template['year_level_id']);
    $total_points = floatval($_POST['total_points'] ?? $template['total_points']);
    $description = trim($_POST['description'] ?? '');
    if ($controle->updateTemplate($template_id, $title, $year_level_id, $total_points, $description)) {
        $message = "Modèle mis à jour.";
        $template = $controle->getTemplateById($template_id);
    } else { $error = "Erreur lors de la mise à jour."; }
}

// Refresh data
$phases = $controle->getPhases($template_id);
$total_phase_points = array_sum(array_column($phases, 'points'));
$is_valid = abs($total_phase_points - $template['total_points']) < 0.01;
$remaining = $template['total_points'] - $total_phase_points;

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<style>
.tpl-edit .phase-row {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: background 0.15s;
}
.tpl-edit .phase-row:hover { background: rgba(0,0,0,0.015); }
.tpl-edit .phase-row:last-child { border-bottom: none; }
.tpl-edit .phase-num {
    width: 28px; height: 28px; border-radius: 8px;
    background: rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 600; color: rgba(0,0,0,0.4); flex-shrink: 0;
}
.tpl-edit .phase-mode {
    padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
}
.tpl-edit .mode-ind { background: rgba(0,0,0,0.05); color: rgba(0,0,0,0.55); }
.tpl-edit .mode-team { background: rgba(59,130,246,0.1); color: #3b82f6; }
.tpl-edit .pts-badge {
    padding: 4px 12px; border-radius: 8px; font-size: 13px; font-weight: 600;
    background: rgba(0,0,0,0.04); color: rgba(0,0,0,0.7); white-space: nowrap;
}
.tpl-edit .add-phase-form {
    border: 2px dashed rgba(0,0,0,0.08); border-radius: 14px;
    padding: 20px; background: rgba(0,0,0,0.01); transition: border-color 0.2s;
}
.tpl-edit .add-phase-form:focus-within { border-color: rgba(0,0,0,0.15); }
.tpl-edit .summary-card {
    border: 1px solid rgba(0,0,0,0.06); border-radius: 14px;
    padding: 20px; background: #fff;
}
.tpl-edit .visual-bar {
    display: flex; height: 8px; border-radius: 4px; overflow: hidden; gap: 2px;
}
.tpl-edit .visual-bar-seg { height: 100%; border-radius: 3px; min-width: 4px; }
</style>

<main class="main-content d-flex flex-column" style="padding-top: 88px;">
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
                <h1 style="font-size: 15px; font-weight: 600; color: #1c1c1c; margin: 0;">Modifier le modèle</h1>
            </div>
            <div class="ms-auto"></div>
        </div>
    </nav>
<div class="container-fluid tpl-edit" style="padding: 24px 28px; max-width: 960px;">

    <!-- Breadcrumb -->
    <nav class="mb-3" style="font-size: 13px;">
        <a href="index.php?page=controles" class="text-decoration-none text-muted">Contrôles</a>
        <span class="text-muted mx-1">/</span>
        <span style="color: #1c1c1c; font-weight: 500;"><?php echo htmlspecialchars($template['title']); ?></span>
    </nav>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h4 class="mb-1" style="font-weight: 700; font-size: 20px;"><?php echo htmlspecialchars($template['title']); ?></h4>
            <div class="d-flex flex-wrap gap-2 align-items-center" style="font-size: 13px; color: rgba(0,0,0,0.5);">
                <span class="badge rounded-pill" style="background: rgba(0,0,0,0.06); color: rgba(0,0,0,0.6); font-weight: 500;">
                    <?php echo htmlspecialchars($template['year_level_name'] ?? 'Non défini'); ?>
                </span>
                <span>·</span>
                <span>Note sur <?php echo $template['total_points']; ?></span>
                <?php if ($template['description']): ?>
                    <span>·</span>
                    <span><?php echo htmlspecialchars($template['description']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editTemplateModal" style="font-size: 13px;">
            <i class="bi bi-gear me-1"></i> Paramètres
        </button>
    </div>

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3" style="font-size: 14px;" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="font-size: 10px;"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3" style="font-size: 14px;" role="alert">
            <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="font-size: 10px;"></button>
        </div>
    <?php endif; ?>

    <!-- Points Status -->
    <div class="summary-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-<?php echo $is_valid ? 'check-circle-fill text-success' : 'exclamation-circle-fill text-warning'; ?>" style="font-size: 18px;"></i>
                    <span style="font-weight: 600; font-size: 15px;">
                        <?php echo $total_phase_points; ?> / <?php echo $template['total_points']; ?> points alloués
                    </span>
                </div>
                <p class="mb-0 mt-1" style="font-size: 12px; color: rgba(0,0,0,0.45);">
                    <?php if ($is_valid): ?>
                        Ce modèle est prêt à être utilisé pour la notation.
                    <?php elseif ($remaining > 0): ?>
                        Il reste <strong><?php echo $remaining; ?> pts</strong> à répartir entre les phases.
                    <?php else: ?>
                        Les phases dépassent le total de <strong><?php echo abs($remaining); ?> pts</strong>.
                    <?php endif; ?>
                </p>
            </div>
            <div style="font-size: 24px; font-weight: 700; color: <?php echo $is_valid ? '#22c55e' : '#f59e0b'; ?>;">
                <?php echo $template['total_points'] > 0 ? round(($total_phase_points / $template['total_points']) * 100) : 0; ?>%
            </div>
        </div>

        <!-- Visual breakdown bar -->
        <?php if (!empty($phases)): ?>
        <div class="visual-bar mb-2">
            <?php 
            $colors = ['#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#ec4899'];
            foreach ($phases as $i => $p): 
                $w = $template['total_points'] > 0 ? ($p['points'] / $template['total_points']) * 100 : 0;
            ?>
                <div class="visual-bar-seg" style="width: <?php echo $w; ?>%; background: <?php echo $colors[$i % count($colors)]; ?>;" 
                     title="<?php echo htmlspecialchars($p['title']); ?>: <?php echo $p['points']; ?> pts"></div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-3" style="font-size: 11px;">
            <?php foreach ($phases as $i => $p): ?>
                <span class="d-flex align-items-center gap-1">
                    <span style="width: 8px; height: 8px; border-radius: 2px; background: <?php echo $colors[$i % count($colors)]; ?>;"></span>
                    <?php echo htmlspecialchars($p['title']); ?> (<?php echo $p['points']; ?>)
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Phases List -->
    <div class="summary-card mb-4 p-0 overflow-hidden">
        <div class="p-3 px-4 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid rgba(0,0,0,0.06);">
            <h6 class="mb-0" style="font-weight: 600; font-size: 14px;">
                Phases
                <span style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.35); margin-left: 6px;"><?php echo count($phases); ?></span>
            </h6>
        </div>

        <?php if (empty($phases)): ?>
            <div class="text-center py-5" style="color: rgba(0,0,0,0.35);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-2">
                    <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"></path>
                </svg>
                <p class="mb-0" style="font-size: 13px;">Ajoutez des phases ci-dessous pour structurer ce contrôle.</p>
            </div>
        <?php else: ?>
            <?php foreach ($phases as $index => $phase): ?>
                <div class="phase-row">
                    <div class="phase-num"><?php echo $index + 1; ?></div>
                    <div style="flex: 1; min-width: 0;">
                        <div class="d-flex align-items-center gap-2">
                            <strong style="font-size: 14px;"><?php echo htmlspecialchars($phase['title']); ?></strong>
                            <span class="phase-mode <?php echo $phase['grading_mode'] === 'team' ? 'mode-team' : 'mode-ind'; ?>">
                                <i class="bi bi-<?php echo $phase['grading_mode'] === 'team' ? 'people' : 'person'; ?>"></i>
                                <?php echo $phase['grading_mode'] === 'team' ? 'Équipe' : 'Individuel'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="pts-badge"><?php echo $phase['points']; ?> pts</div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm border-0 p-1" data-bs-toggle="modal" data-bs-target="#editPhase<?php echo $phase['id']; ?>" 
                                style="color: rgba(0,0,0,0.3);"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette phase ?');">
                            <input type="hidden" name="phase_id" value="<?php echo $phase['id']; ?>">
                            <button type="submit" name="delete_phase" class="btn btn-sm border-0 p-1" style="color: rgba(0,0,0,0.3);"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>

                <!-- Edit Phase Modal -->
                <div class="modal fade" id="editPhase<?php echo $phase['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content" style="border-radius: 14px; border: none;">
                            <div class="modal-header border-0 pb-0">
                                <h6 class="modal-title" style="font-weight: 600;">Modifier la phase</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 10px;"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="phase_id" value="<?php echo $phase['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label" style="font-size: 12px; font-weight: 500;">Titre</label>
                                        <input type="text" name="phase_title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($phase['title']); ?>" required style="border-radius: 8px;">
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label" style="font-size: 12px; font-weight: 500;">Points</label>
                                            <input type="number" name="points" class="form-control form-control-sm" value="<?php echo $phase['points']; ?>" min="0.5" max="<?php echo $template['total_points']; ?>" step="0.5" required style="border-radius: 8px;">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label" style="font-size: 12px; font-weight: 500;">Mode</label>
                                            <select name="grading_mode" class="form-select form-select-sm" required style="border-radius: 8px;">
                                                <option value="individual" <?php echo $phase['grading_mode'] === 'individual' ? 'selected' : ''; ?>>Individuel</option>
                                                <option value="team" <?php echo $phase['grading_mode'] === 'team' ? 'selected' : ''; ?>>Équipe</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 pt-0">
                                    <button type="submit" name="update_phase" class="btn btn-dark btn-sm rounded-pill px-4 w-100" style="font-size: 13px;">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Total row -->
            <div class="phase-row" style="background: rgba(0,0,0,0.02);">
                <div class="phase-num" style="visibility: hidden;"></div>
                <div style="flex: 1;"><strong style="font-size: 13px; color: rgba(0,0,0,0.5);">Total</strong></div>
                <div class="pts-badge" style="background: <?php echo $is_valid ? 'rgba(34,197,94,0.1)' : 'rgba(245,158,11,0.1)'; ?>; color: <?php echo $is_valid ? '#16a34a' : '#d97706'; ?>;">
                    <?php echo $total_phase_points; ?> / <?php echo $template['total_points']; ?>
                </div>
                <div style="width: 54px;"></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Phase Form -->
    <div class="add-phase-form">
        <form method="POST">
            <div class="d-flex align-items-end gap-3 flex-wrap">
                <div style="flex: 2; min-width: 180px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.5);">Nouvelle phase</label>
                    <input type="text" name="phase_title" class="form-control form-control-sm" placeholder="Ex: Rapport écrit, Présentation…" required style="border-radius: 8px;">
                </div>
                <div style="width: 100px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.5);">Points</label>
                    <input type="number" name="points" class="form-control form-control-sm" value="<?php echo $remaining > 0 ? min($remaining, 5) : 5; ?>" min="0.5" max="<?php echo $template['total_points']; ?>" step="0.5" required style="border-radius: 8px;">
                </div>
                <div style="width: 130px;">
                    <label class="form-label" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.5);">Mode</label>
                    <select name="grading_mode" class="form-select form-select-sm" required style="border-radius: 8px;">
                        <option value="individual">Individuel</option>
                        <option value="team">Équipe</option>
                    </select>
                </div>
                <button type="submit" name="add_phase" class="btn btn-dark btn-sm rounded-pill px-4" style="font-size: 13px; height: 31px;">
                    <i class="bi bi-plus-lg me-1"></i> Ajouter
                </button>
            </div>
        </form>
    </div>

</div>
</main>

<!-- Edit Template Settings Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius: 14px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title" style="font-weight: 600;">Paramètres du modèle</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 10px;"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 12px; font-weight: 500;">Titre</label>
                        <input type="text" name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($template['title']); ?>" required style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 12px; font-weight: 500;">Niveau</label>
                        <select name="year_level_id" class="form-select form-select-sm" required style="border-radius: 8px;">
                            <?php foreach ($year_levels as $yl): ?>
                                <option value="<?php echo $yl['id']; ?>" <?php echo $template['year_level_id'] == $yl['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($yl['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 12px; font-weight: 500;">Note maximale</label>
                        <input type="number" name="total_points" class="form-control form-control-sm" value="<?php echo $template['total_points']; ?>" min="1" max="100" step="0.5" required style="border-radius: 8px;">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" style="font-size: 12px; font-weight: 500;">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" style="border-radius: 8px;"><?php echo htmlspecialchars($template['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" name="update_template" class="btn btn-dark btn-sm rounded-pill px-4 w-100" style="font-size: 13px;">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'views/partials/footer.php'; ?>
