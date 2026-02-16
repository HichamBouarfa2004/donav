<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';
require_once 'classes/Team.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);
$team = new Team($db);

$message = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $class_id = intval($_POST['class_id'] ?? 0);
    
    // Verify class belongs to teacher
    if (!$class_id || !$classroom->getById($class_id) || $classroom->enseignant_id != $_SESSION['teacher_id']) {
        $response['message'] = 'Classe non autorisée';
        echo json_encode($response);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_member') {
        $team_id = intval($_POST['team_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        
        if ($team_id && $student_id && $team->getById($team_id) && $team->class_id == $class_id) {
            $result = $team->addMember($student_id);
            if ($result === true) {
                $response['success'] = true;
                $response['message'] = 'Élève assigné avec succès';
            } elseif ($result === 'already_in_team') {
                $response['message'] = 'Élève déjà dans une autre équipe';
            } else {
                $response['message'] = 'Erreur lors de l\'assignation';
            }
        } else {
            $response['message'] = 'Données invalides';
        }
    } elseif ($action === 'remove_member') {
        $team_id = intval($_POST['team_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        
        if ($team_id && $student_id && $team->getById($team_id) && $team->class_id == $class_id) {
            if ($team->removeMember($student_id)) {
                $response['success'] = true;
                $response['message'] = 'Élève retiré de l\'équipe';
            } else {
                $response['message'] = 'Erreur lors du retrait';
            }
        } else {
            $response['message'] = 'Données invalides';
        }
    } elseif ($action === 'move_member') {
        $from_team_id = intval($_POST['from_team_id'] ?? 0);
        $to_team_id = intval($_POST['to_team_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        
        if ($from_team_id && $to_team_id && $student_id && $from_team_id != $to_team_id) {
            if ($team->getById($from_team_id) && $team->class_id == $class_id) {
                if ($team->moveMember($student_id, $to_team_id)) {
                    $response['success'] = true;
                    $response['message'] = 'Élève déplacé avec succès';
                } else {
                    $response['message'] = 'Erreur lors du déplacement';
                }
            } else {
                $response['message'] = 'Équipe non trouvée';
            }
        } else {
            $response['message'] = 'Données invalides';
        }
    } elseif ($action === 'create_team') {
        $team_name = trim($_POST['team_name'] ?? '');
        if (empty($team_name)) {
            $response['message'] = 'Le nom est requis';
        } elseif ($team->nameExistsInClass($team_name, $class_id)) {
            $response['message'] = 'Ce nom existe déjà';
        } else {
            $team->name = $team_name;
            $team->class_id = $class_id;
            if ($team->create()) {
                $response['success'] = true;
                $response['team_id'] = $team->id;
                $response['team_name'] = htmlspecialchars($team_name);
                $response['message'] = 'Équipe créée';
            } else {
                $response['message'] = 'Erreur lors de la création';
            }
        }
    } elseif ($action === 'rename_team') {
        $team_id = intval($_POST['team_id'] ?? 0);
        $new_name = trim($_POST['new_name'] ?? '');
        if ($team_id && $team->getById($team_id) && $team->class_id == $class_id) {
            if (empty($new_name)) {
                $response['message'] = 'Le nom est requis';
            } elseif ($team->nameExistsInClass($new_name, $class_id, $team_id)) {
                $response['message'] = 'Ce nom existe déjà';
            } else {
                $team->name = $new_name;
                if ($team->update()) {
                    $response['success'] = true;
                    $response['message'] = 'Équipe renommée';
                } else {
                    $response['message'] = 'Erreur lors du renommage';
                }
            }
        } else {
            $response['message'] = 'Équipe non trouvée ou non autorisée';
        }
    } elseif ($action === 'delete_team') {
        $team_id = intval($_POST['team_id'] ?? 0);
        if ($team_id && $team->getById($team_id) && $team->class_id == $class_id) {
            if ($team->delete()) {
                $response['success'] = true;
                $response['message'] = 'Équipe supprimée';
            } else {
                $response['message'] = 'Erreur lors de la suppression';
            }
        } else {
            $response['message'] = 'Équipe non trouvée ou non autorisée';
        }
    } elseif ($action === 'clear_team') {
        $team_id = intval($_POST['team_id'] ?? 0);
        if ($team_id && $team->getById($team_id) && $team->class_id == $class_id) {
            if ($team->clearMembers()) {
                $response['success'] = true;
                $response['message'] = 'Membres retirés';
            } else {
                $response['message'] = 'Erreur';
            }
        } else {
            $response['message'] = 'Équipe non trouvée ou non autorisée';
        }
    } elseif ($action === 'batch_create') {
        $count = intval($_POST['count'] ?? 0);
        $prefix = trim($_POST['prefix'] ?? 'Équipe');
        if ($count < 1 || $count > 20) {
            $response['message'] = 'Nombre invalide (1-20)';
        } else {
            // Find highest existing number for this prefix
            $existing_teams = $team->getByClass($class_id)->fetchAll(PDO::FETCH_ASSOC);
            $max_num = 0;
            foreach ($existing_teams as $et) {
                if (preg_match('/^' . preg_quote($prefix, '/') . '\s+(\d+)$/i', $et['name'], $m)) {
                    $max_num = max($max_num, intval($m[1]));
                }
            }
            
            $names = [];
            for ($i = 1; $i <= $count; $i++) {
                $names[] = $prefix . ' ' . ($max_num + $i);
            }
            $created = $team->batchCreate($names, $class_id);
            if ($created && count($created) > 0) {
                $response['success'] = true;
                $response['created_count'] = count($created);
                $response['message'] = count($created) . ' équipes créées';
            } else {
                $response['message'] = 'Erreur lors de la création';
            }
        }
    } elseif ($action === 'auto_distribute') {
        $distributed = $team->autoDistribute($class_id);
        if ($distributed > 0) {
            $response['success'] = true;
            $response['distributed'] = $distributed;
            $response['message'] = $distributed . ' élèves distribués';
        } else {
            $response['message'] = 'Aucun élève à distribuer';
        }
    } elseif ($action === 'delete_all_teams') {
        if ($team->deleteAllByClass($class_id)) {
            $response['success'] = true;
            $response['message'] = 'Toutes les équipes supprimées';
        } else {
            $response['message'] = 'Erreur lors de la suppression';
        }
    }
    
    echo json_encode($response);
    exit;
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$teacher_classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get class ID from URL or default to first class
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if (!$class_id && !empty($teacher_classes)) {
    $class_id = $teacher_classes[0]['id'];
}

// Verify class belongs to teacher
$class_name = '';
$teams_data = [];
$unassigned_students = [];
$all_students = [];

if (!$class_id || !$classroom->getById($class_id) || $classroom->enseignant_id != $_SESSION['teacher_id']) {
    if (!empty($teacher_classes)) {
        header('Location: index.php?page=teams&class_id=' . $teacher_classes[0]['id']);
        exit;
    }
} else {
    $class_name = $classroom->nom;
    
    // Use optimized single-query fetch
    $teams_data = $team->getTeamsWithMembers($class_id);
    
    // Get unassigned students
    $unassigned_stmt = $team->getUnassignedStudents($class_id);
    $unassigned_students = $unassigned_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all students
    $all_students_stmt = $student->getByClass($class_id);
    $all_students = $all_students_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$total_students = count($all_students);
$assigned_count = $total_students - count($unassigned_students);
$progress_pct = $total_students > 0 ? round(($assigned_count / $total_students) * 100) : 0;
?>

<?php include 'views/partials/header.php'; ?>

<?php include 'views/partials/sidebar.php'; ?>

<div class="main-content" style="margin-left: 212px; padding-top: 68px;">
    <main class="container-fluid" style="padding: 28px;">
        
        <?php if (empty($teacher_classes)): ?>
        <!-- No Classes State -->
        <div style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); padding: 64px 32px; text-align: center; max-width: 480px; margin: 60px auto;">
            <div style="width: 64px; height: 64px; background: rgba(0,0,0,0.03); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.25)" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Aucune classe</h3>
            <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin-bottom: 24px; line-height: 1.5;">Créez d'abord une classe pour gérer les équipes.</p>
            <a href="index.php?page=manage_classes" style="display: inline-flex; align-items: center; gap: 8px; background: #1c1c1c; color: #fff; border-radius: 12px; padding: 12px 24px; font-size: 14px; font-weight: 500; text-decoration: none;">
                Créer une classe
            </a>
        </div>

        <?php else: ?>

        <!-- Top Bar: Class Selector + Stats + Actions -->
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 20px;">
            <!-- Class Selector (searchable dropdown) -->
            <div class="sd-wrap" id="sdClassSelector">
                <button type="button" class="sd-toggle" onclick="sdToggle('sdClassSelector')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.35)" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                    <span class="sd-label"><?= htmlspecialchars($class_name ?: 'Sélectionner une classe') ?></span>
                    <span class="sd-icon">&#9662;</span>
                </button>
                <div class="sd-panel">
                    <input type="text" class="sd-search" placeholder="Rechercher une classe..." oninput="sdFilter(this)">
                    <div class="sd-list">
                        <?php foreach ($teacher_classes as $tc): ?>
                        <a href="?page=teams&class_id=<?= $tc['id'] ?>" class="sd-opt <?= $tc['id'] == $class_id ? 'active' : '' ?>"><?= htmlspecialchars($tc['nom']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div style="flex: 1; min-width: 200px; max-width: 320px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="flex: 1; height: 6px; background: rgba(0,0,0,0.06); border-radius: 3px; overflow: hidden;">
                        <div id="progress-bar" style="height: 100%; background: <?= $progress_pct == 100 ? '#22c55e' : '#6366f1' ?>; border-radius: 3px; width: <?= $progress_pct ?>%; transition: width 0.4s ease, background 0.3s;"></div>
                    </div>
                    <span id="progress-text" style="font-size: 12px; font-weight: 600; color: <?= $progress_pct == 100 ? '#22c55e' : '#6366f1' ?>; white-space: nowrap;"><?= $assigned_count ?>/<?= $total_students ?></span>
                </div>
            </div>

            <!-- Stats -->
            <div style="display: flex; align-items: center; gap: 16px; font-size: 13px; color: rgba(0,0,0,0.5);">
                <span><strong id="stat-teams" style="color: #1c1c1c;"><?= count($teams_data) ?></strong> équipes</span>
                <span><strong id="stat-free" style="color: #f59e0b;"><?= count($unassigned_students) ?></strong> libres</span>
            </div>

            <!-- Spacer -->
            <div style="flex: 1;"></div>

            <!-- Actions -->
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php if (count($unassigned_students) > 0 && count($teams_data) > 0): ?>
                <button onclick="autoDistribute()" class="teams-action-btn" style="background: rgba(34,197,94,0.08); color: #16a34a; border: 1px solid rgba(34,197,94,0.2);" title="Distribuer automatiquement">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 3h5v5"></path><path d="M8 3H3v5"></path><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"></path><path d="m15 9 6-6"></path>
                    </svg>
                    <span class="d-none d-md-inline">Auto-distribuer</span>
                </button>
                <?php endif; ?>

                <?php if (count($teams_data) > 0): ?>
                <button onclick="deleteAllTeams()" class="teams-action-btn" style="background: rgba(239,68,68,0.08); color: #ef4444; border: 1px solid rgba(239,68,68,0.2);" title="Supprimer toutes les équipes">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span class="d-none d-md-inline">Tout supprimer</span>
                </button>
                <?php endif; ?>
                
                <button onclick="showBatchCreate()" class="teams-action-btn" style="background: rgba(99,102,241,0.08); color: #6366f1; border: 1px solid rgba(99,102,241,0.2);" title="Créer plusieurs équipes">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                    </svg>
                    <span class="d-none d-md-inline">Lot</span>
                </button>

                <button onclick="showCreateTeam()" class="teams-action-btn" style="background: #1c1c1c; color: #fff; border: 1px solid #1c1c1c;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Nouvelle</span>
                </button>
            </div>
        </div>

        <!-- Drag hint + selection info -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; min-height: 32px;">
            <div style="display: flex; align-items: center; gap: 6px; color: rgba(0,0,0,0.35); font-size: 12px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 9l-3 3 3 3"></path><path d="M9 5l3-3 3 3"></path><path d="M15 19l-3 3-3-3"></path><path d="M19 9l3 3-3 3"></path><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                Glissez-déposez pour organiser &bull; Ctrl+Clic pour multi-sélection
            </div>
            <div class="selection-bar" id="selectionBar">
                <span><strong id="selection-count">0</strong> sélectionnés</span>
                <div class="send-to-wrap">
                    <button class="send-to-toggle" onclick="toggleSendTo()">Envoyer à <span class="sd-icon">&#9662;</span></button>
                    <div class="send-to-menu" id="sendToMenu"></div>
                </div>
                <button onclick="clearSelection()" class="selection-clear-btn">Annuler</button>
            </div>
        </div>

        <!-- Kanban Board -->
        <div class="kanban-container" id="kanbanBoard">
            <!-- Unassigned Column -->
            <div class="kanban-col unassigned" data-team-id="0" data-team-name="Non assignés">
                <div class="kanban-col-header">
                    <div class="kanban-col-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Non assignés
                        <span class="kanban-badge unassigned-badge" id="count-0"><?= count($unassigned_students) ?></span>
                    </div>
                </div>
                <div class="kanban-col-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.3)" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Rechercher..." oninput="filterStudents(this, 0)">
                </div>
                <div class="kanban-col-body" id="body-0" data-team-id="0">
                    <?php if (empty($unassigned_students)): ?>
                    <div class="kanban-empty" id="empty-0">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <p>Tous assignés !</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($unassigned_students as $s): ?>
                        <?php if (trim($s['nom'])): ?>
                        <div class="student-card" draggable="true" data-student-id="<?= $s['id'] ?>" data-student-name="<?= htmlspecialchars($s['nom']) ?>" data-team="0">
                            <div class="card-checkbox" onclick="toggleSelect(this.parentElement, event)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </div>
                            <div class="card-avatar" style="background: linear-gradient(135deg, #f59e0b, #f97316);"><?= strtoupper(mb_substr($s['nom'], 0, 1)) ?></div>
                            <div class="card-name"><?= htmlspecialchars($s['nom']) ?></div>
                            <div class="card-grip">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1"></circle><circle cx="9" cy="12" r="1"></circle><circle cx="9" cy="19" r="1"></circle><circle cx="15" cy="5" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="15" cy="19" r="1"></circle></svg>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Team Columns -->
            <?php foreach ($teams_data as $t): ?>
            <div class="kanban-col" data-team-id="<?= $t['id'] ?>" data-team-name="<?= htmlspecialchars($t['name']) ?>">
                <div class="kanban-col-header">
                    <div class="kanban-col-title">
                        <span class="team-name-text" id="teamname-<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></span>
                        <span class="kanban-badge" id="count-<?= $t['id'] ?>"><?= count($t['members']) ?></span>
                    </div>
                    <div class="kanban-col-actions">
                        <button class="col-action-btn" onclick="startRename(<?= $t['id'] ?>)" title="Renommer">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <div class="dropdown" style="display: inline-block;">
                            <button class="col-action-btn" data-bs-toggle="dropdown">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 8px 24px rgba(0,0,0,0.12); padding: 6px; min-width: 180px;">
                                <li><button class="dropdown-item" onclick="clearTeamMembers(<?= $t['id'] ?>)" style="font-size: 13px; border-radius: 8px; padding: 8px 12px;">
                                    <svg class="me-2" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path></svg>
                                    Vider l'équipe
                                </button></li>
                                <li><hr class="dropdown-divider" style="margin: 4px 0;"></li>
                                <li><button class="dropdown-item text-danger" onclick="deleteTeam(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['name'])) ?>')" style="font-size: 13px; border-radius: 8px; padding: 8px 12px;">
                                    <svg class="me-2" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    Supprimer
                                </button></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="kanban-col-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.3)" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Rechercher..." oninput="filterStudents(this, <?= $t['id'] ?>)">
                </div>
                <div class="kanban-col-body" id="body-<?= $t['id'] ?>" data-team-id="<?= $t['id'] ?>">
                    <?php if (empty($t['members'])): ?>
                    <div class="kanban-empty" id="empty-<?= $t['id'] ?>">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                        <p>Glissez des élèves ici</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($t['members'] as $m): ?>
                        <div class="student-card" draggable="true" data-student-id="<?= $m['id'] ?>" data-student-name="<?= htmlspecialchars($m['nom']) ?>" data-team="<?= $t['id'] ?>">
                            <div class="card-checkbox" onclick="toggleSelect(this.parentElement, event)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </div>
                            <div class="card-avatar"><?= strtoupper(mb_substr($m['nom'], 0, 1)) ?></div>
                            <div class="card-name"><?= htmlspecialchars($m['nom']) ?></div>
                            <button class="card-remove" onclick="removeMember(<?= $t['id'] ?>, <?= $m['id'] ?>, this)" title="Retirer">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                            <div class="card-grip">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1"></circle><circle cx="9" cy="12" r="1"></circle><circle cx="9" cy="19" r="1"></circle><circle cx="15" cy="5" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="15" cy="19" r="1"></circle></svg>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Add Column Placeholder (when no teams exist) -->
            <?php if (empty($teams_data)): ?>
            <div class="kanban-col" style="background: transparent; border: 2px dashed rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="showCreateTeam()">
                <div style="text-align: center; padding: 40px;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.15)" stroke-width="1.5" style="margin-bottom: 12px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    <p style="color: rgba(0,0,0,0.35); font-size: 14px; margin: 0;">Créer une équipe</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </main>
</div>

<!-- Inline Create Team Panel (slides down) -->
<div class="inline-panel" id="createPanel">
    <div class="inline-panel-content">
        <div style="display: flex; align-items: center; gap: 12px;">
            <input type="text" id="newTeamName" placeholder="Nom de l'équipe..." class="inline-input" onkeydown="if(event.key==='Enter')createTeam()" autofocus>
            <button onclick="createTeam()" class="inline-btn primary">Créer</button>
            <button onclick="hideCreateTeam()" class="inline-btn cancel">Annuler</button>
        </div>
    </div>
</div>

<!-- Batch Create Panel -->
<div class="inline-panel" id="batchPanel">
    <div class="inline-panel-content">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <label style="font-size: 13px; font-weight: 500; color: #1c1c1c; white-space: nowrap;">Préfixe:</label>
            <input type="text" id="batchPrefix" value="Équipe" class="inline-input" style="max-width: 140px;">
            <label style="font-size: 13px; font-weight: 500; color: #1c1c1c; white-space: nowrap;">Nombre:</label>
            <input type="number" id="batchCount" value="4" min="1" max="20" class="inline-input" style="max-width: 70px;">
            <button onclick="batchCreate()" class="inline-btn primary">Créer</button>
            <button onclick="hideBatchCreate()" class="inline-btn cancel">Annuler</button>
        </div>
    </div>
</div>

<!-- Confirm Dialog -->
<div class="confirm-overlay" id="confirmOverlay" onclick="if(event.target===this)hideConfirm()">
    <div class="confirm-dialog">
        <h4 id="confirmTitle">Confirmer</h4>
        <p id="confirmMessage">Êtes-vous sûr ?</p>
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
            <button onclick="hideConfirm()" class="inline-btn cancel">Annuler</button>
            <button id="confirmBtn" class="inline-btn danger">Supprimer</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-stack" id="toastStack"></div>

<style>
/* ── Searchable Dropdown ── */
.sd-wrap { position: relative; min-width: 200px; max-width: 320px; }
.sd-toggle {
    display: flex; align-items: center; gap: 8px; width: 100%;
    padding: 8px 14px; border: 1px solid rgba(0,0,0,0.12); border-radius: 10px;
    background: #fff; cursor: pointer; font-size: 13px; font-weight: 600; color: #1c1c1c;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.sd-toggle:hover { border-color: rgba(0,0,0,0.25); }
.sd-toggle:focus, .sd-wrap.open .sd-toggle {
    border-color: #1c1c1c; box-shadow: 0 0 0 3px rgba(0,0,0,0.06); outline: none;
}
.sd-toggle .sd-icon { margin-left: auto; color: rgba(0,0,0,0.3); transition: transform 0.2s; font-size: 11px; }
.sd-wrap.open .sd-toggle .sd-icon { transform: rotate(180deg); }
.sd-panel {
    display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: #fff; border: 1px solid rgba(0,0,0,0.1); border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1); z-index: 100; overflow: hidden;
    max-height: 260px;
}
.sd-wrap.open .sd-panel { display: block; }
.sd-search {
    width: 100%; padding: 10px 14px; border: none; border-bottom: 1px solid rgba(0,0,0,0.06);
    font-size: 13px; outline: none; background: transparent; color: #1c1c1c;
}
.sd-search::placeholder { color: rgba(0,0,0,0.3); }
.sd-list { max-height: 200px; overflow-y: auto; padding: 4px 0; }
.sd-list::-webkit-scrollbar { width: 4px; }
.sd-list::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 4px; }
.sd-opt {
    display: flex; align-items: center; gap: 8px; padding: 8px 14px;
    font-size: 13px; color: #555; cursor: pointer; transition: background 0.1s;
    text-decoration: none;
}
.sd-opt:hover { background: rgba(0,0,0,0.04); color: #1c1c1c; }
.sd-opt.active { background: rgba(0,0,0,0.06); color: #1c1c1c; font-weight: 600; }
.sd-opt.active::before { content: '\2713'; font-size: 11px; color: #22c55e; font-weight: 700; }
.sd-opt.hidden { display: none; }
.sd-empty { padding: 16px 14px; text-align: center; font-size: 12px; color: rgba(0,0,0,0.3); }

/* ── Teams-specific styles ── */

.teams-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.teams-action-btn:hover { filter: brightness(0.95); transform: translateY(-1px); }
.teams-action-btn:active { transform: translateY(0); }

/* ── Kanban ── */
.kanban-container {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    padding-bottom: 20px;
    min-height: 200px;
}

.kanban-col {
    min-width: 272px;
    max-width: 272px;
    min-height: 420px;
    background: #fff;
    border-radius: 16px;
    border: 1px solid rgba(0,0,0,0.07);
    display: flex;
    flex-direction: column;
    transition: border-color 0.2s, box-shadow 0.2s;
    flex-shrink: 0;
}
.kanban-col.unassigned {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 2px dashed #fbbf24;
}
.kanban-col.drag-over {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.kanban-col.unassigned.drag-over {
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245,158,11,0.15);
}

.kanban-col-header {
    padding: 14px 14px 12px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.kanban-col-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #1c1c1c;
    min-width: 0;
}
.kanban-col-title .team-name-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 140px;
}
.kanban-badge {
    background: #1c1c1c;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    flex-shrink: 0;
    transition: all 0.2s;
}
.kanban-badge.unassigned-badge { background: #f59e0b; }

.kanban-col-actions {
    display: flex;
    gap: 2px;
    flex-shrink: 0;
}
.col-action-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: rgba(0,0,0,0.35);
    cursor: pointer;
    transition: all 0.15s;
    padding: 0;
}
.col-action-btn:hover {
    background: rgba(0,0,0,0.06);
    color: #1c1c1c;
}

.kanban-col-search {
    padding: 8px 12px;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    display: flex;
    align-items: center;
    gap: 8px;
}
.kanban-col-search input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 13px;
    outline: none;
    padding: 4px 0;
    color: #1c1c1c;
}
.kanban-col-search input::placeholder { color: rgba(0,0,0,0.3); }

.kanban-col-body {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    min-height: 120px;
    max-height: 420px;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.kanban-col-body::-webkit-scrollbar { display: none; }

.kanban-empty {
    text-align: center;
    padding: 36px 12px;
    color: rgba(0,0,0,0.25);
}
.kanban-empty svg { opacity: 0.3; margin-bottom: 8px; }
.kanban-empty p { font-size: 13px; margin: 0; }

/* ── Student Card ── */
.student-card {
    background: #fff;
    border: 1px solid rgba(0,0,0,0.07);
    border-radius: 10px;
    padding: 8px 10px;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: grab;
    transition: all 0.15s ease;
    user-select: none;
    position: relative;
}
.student-card:hover {
    border-color: rgba(99,102,241,0.4);
    box-shadow: 0 2px 8px rgba(99,102,241,0.1);
}
.student-card:active { cursor: grabbing; }
.student-card.dragging {
    opacity: 0.4;
    transform: scale(0.98);
}
.student-card.selected {
    border-color: #6366f1;
    background: rgba(99,102,241,0.06);
    box-shadow: 0 0 0 2px rgba(99,102,241,0.2);
}
.student-card.hidden { display: none !important; }

.card-checkbox {
    width: 18px;
    height: 18px;
    min-width: 18px;
    border: 2px solid rgba(0,0,0,0.15);
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.15s;
    cursor: pointer;
    flex-shrink: 0;
}
.student-card:hover .card-checkbox,
.student-card.selected .card-checkbox { opacity: 1; }
.student-card.selected .card-checkbox {
    background: #6366f1;
    border-color: #6366f1;
}
.card-checkbox svg {
    width: 12px;
    height: 12px;
    color: #fff;
    display: none;
}
.student-card.selected .card-checkbox svg { display: block; }

.card-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}
.card-name {
    flex: 1;
    font-size: 13px;
    font-weight: 500;
    color: #1c1c1c;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.card-remove {
    opacity: 0;
    width: 22px;
    height: 22px;
    border: none;
    border-radius: 6px;
    background: rgba(239,68,68,0.08);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
    padding: 0;
}
.student-card:hover .card-remove { opacity: 1; }
.card-remove:hover { background: #ef4444; color: #fff; }
.card-remove.processing { opacity: 0.4; pointer-events: none; }

.card-grip {
    color: rgba(0,0,0,0.15);
    flex-shrink: 0;
    transition: color 0.15s;
}
.student-card:hover .card-grip { color: rgba(0,0,0,0.35); }

/* ── Inline Rename ── */
.inline-rename-input {
    border: 1px solid #6366f1;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 14px;
    font-weight: 600;
    outline: none;
    width: 130px;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}

/* ── Selection Bar ── */
.selection-bar {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 6px 14px;
    background: #6366f1;
    color: #fff;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    animation: slideIn 0.2s ease;
}
.selection-bar.active { display: flex; }
.selection-clear-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    padding: 3px 10px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    transition: background 0.15s;
}
.selection-clear-btn:hover { background: rgba(255,255,255,0.3); }

/* ── Send to (selection bar dropdown) ── */
.send-to-wrap { position: relative; }
.send-to-toggle {
    background: rgba(255,255,255,0.2); border: none; color: #fff;
    padding: 3px 10px; border-radius: 5px; cursor: pointer;
    font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 4px;
    transition: background 0.15s;
}
.send-to-toggle:hover { background: rgba(255,255,255,0.3); }
.send-to-toggle .sd-icon { font-size: 9px; transition: transform 0.2s; }
.send-to-wrap.open .send-to-toggle .sd-icon { transform: rotate(180deg); }
.send-to-menu {
    display: none; position: absolute; top: calc(100% + 6px); left: 50%; transform: translateX(-50%);
    background: #fff; border: 1px solid rgba(0,0,0,0.1); border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15); z-index: 200; min-width: 200px;
    max-height: 260px; overflow-y: auto; padding: 4px 0;
}
.send-to-wrap.open .send-to-menu { display: block; }
.send-to-menu::-webkit-scrollbar { width: 4px; }
.send-to-menu::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 4px; }
.send-to-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 14px;
    font-size: 13px; color: #555; cursor: pointer; transition: background 0.1s;
    border: none; background: none; width: 100%; text-align: left;
}
.send-to-item:hover { background: rgba(99,102,241,0.06); color: #1c1c1c; }
.send-to-item .sti-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.send-to-item .sti-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.send-to-item .sti-count { font-size: 11px; color: rgba(0,0,0,0.3); flex-shrink: 0; }

/* ── Inline Panel (Create / Batch) ── */
.inline-panel {
    position: fixed;
    top: 68px;
    left: 212px;
    right: 0;
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,0.08);
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    z-index: 1010;
    transform: translateY(-100%);
    opacity: 0;
    transition: transform 0.25s ease, opacity 0.2s ease;
    pointer-events: none;
}
.inline-panel.show {
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}
.inline-panel-content {
    max-width: 600px;
    margin: 0 auto;
    padding: 16px 28px;
}
.inline-input {
    flex: 1;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    min-width: 0;
}
.inline-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.inline-btn {
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.inline-btn.primary { background: #1c1c1c; color: #fff; }
.inline-btn.primary:hover { background: #333; }
.inline-btn.cancel { background: rgba(0,0,0,0.04); color: #1c1c1c; }
.inline-btn.cancel:hover { background: rgba(0,0,0,0.08); }
.inline-btn.danger { background: #ef4444; color: #fff; }
.inline-btn.danger:hover { background: #dc2626; }

/* ── Confirm Dialog ── */
.confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 9998;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}
.confirm-overlay.show { display: flex; }
.confirm-dialog {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    max-width: 380px;
    width: 90%;
    animation: slideIn 0.2s ease;
}
.confirm-dialog h4 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 8px;
}
.confirm-dialog p {
    font-size: 14px;
    color: rgba(0,0,0,0.6);
    margin: 0 0 20px;
    line-height: 1.5;
}

/* ── Toast ── */
.toast-stack {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.toast-msg {
    padding: 12px 18px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    transform: translateX(120%);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    max-width: 300px;
    color: #fff;
}
.toast-msg.show { transform: translateX(0); opacity: 1; }
.toast-msg.success { background: #22c55e; }
.toast-msg.error { background: #ef4444; }
.toast-msg.info { background: #1c1c1c; }

/* ── Animations ── */
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Responsive ── */
@media (max-width: 991px) {
    .inline-panel { left: 0; }
}
@media (max-width: 768px) {
    .kanban-col { min-width: 256px; max-width: 256px; }
    .kanban-col-body { max-height: 350px; }
    .inline-panel { left: 0; }
}
</style>

<script>
const classId = <?= $class_id ?: 0 ?>;
const totalStudents = <?= $total_students ?>;
let selectedStudents = new Set();
let dragState = { el: null, studentId: null, fromTeam: null };

// ── AJAX Helper ──
async function api(action, data = {}) {
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', action);
    fd.append('class_id', classId);
    for (const k in data) fd.append(k, data[k]);
    
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        return await res.json();
    } catch {
        return { success: false, message: 'Erreur de connexion' };
    }
}

// ── Toast ──
function toast(msg, type = 'success') {
    const stack = document.getElementById('toastStack');
    const el = document.createElement('div');
    el.className = `toast-msg ${type}`;
    const icons = {
        success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };
    el.innerHTML = (icons[type] || '') + '<span>' + msg + '</span>';
    stack.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 300);
    }, 2500);
}

// ── Create Team (inline) ──
function showCreateTeam() {
    hideBatchCreate();
    const panel = document.getElementById('createPanel');
    panel.classList.add('show');
    setTimeout(() => document.getElementById('newTeamName').focus(), 200);
}
function hideCreateTeam() {
    document.getElementById('createPanel').classList.remove('show');
    document.getElementById('newTeamName').value = '';
}
async function createTeam() {
    const name = document.getElementById('newTeamName').value.trim();
    if (!name) return;
    
    const result = await api('create_team', { team_name: name });
    if (result.success) {
        toast('Équipe "' + result.team_name + '" créée', 'success');
        hideCreateTeam();
        setTimeout(() => location.reload(), 400);
    } else {
        toast(result.message, 'error');
    }
}

// ── Batch Create ──
function showBatchCreate() {
    hideCreateTeam();
    const panel = document.getElementById('batchPanel');
    panel.classList.add('show');
    setTimeout(() => document.getElementById('batchCount').focus(), 200);
}
function hideBatchCreate() {
    document.getElementById('batchPanel').classList.remove('show');
}
async function batchCreate() {
    const prefix = document.getElementById('batchPrefix').value.trim() || 'Équipe';
    const count = parseInt(document.getElementById('batchCount').value) || 4;
    
    const result = await api('batch_create', { prefix, count });
    if (result.success) {
        toast(result.message, 'success');
        hideBatchCreate();
        setTimeout(() => location.reload(), 400);
    } else {
        toast(result.message, 'error');
    }
}

// ── Inline Rename ──
function startRename(teamId) {
    const nameEl = document.getElementById('teamname-' + teamId);
    const currentName = nameEl.textContent.trim();
    
    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentName;
    input.className = 'inline-rename-input';
    
    nameEl.replaceWith(input);
    input.focus();
    input.select();
    
    const finish = async (save) => {
        input.removeEventListener('blur', blurHandler);
        input.removeEventListener('keydown', keyHandler);
        
        const newName = input.value.trim();
        const span = document.createElement('span');
        span.className = 'team-name-text';
        span.id = 'teamname-' + teamId;
        
        if (save && newName && newName !== currentName) {
            const result = await api('rename_team', { team_id: teamId, new_name: newName });
            if (result.success) {
                span.textContent = newName;
                // Update column data attribute
                input.closest('.kanban-col').dataset.teamName = newName;
                toast('Équipe renommée', 'info');
            } else {
                span.textContent = currentName;
                toast(result.message, 'error');
            }
        } else {
            span.textContent = currentName;
        }
        
        input.replaceWith(span);
    };
    
    const blurHandler = () => finish(true);
    const keyHandler = (e) => {
        if (e.key === 'Enter') { e.preventDefault(); finish(true); }
        if (e.key === 'Escape') { e.preventDefault(); finish(false); }
    };
    
    input.addEventListener('blur', blurHandler);
    input.addEventListener('keydown', keyHandler);
}

// ── Delete Team ──
function deleteTeam(teamId, teamName) {
    showConfirm(
        'Supprimer l\'équipe ?',
        'L\'équipe "' + teamName + '" sera supprimée. Les élèves resteront dans la classe.',
        async () => {
            const result = await api('delete_team', { team_id: teamId });
            if (result.success) {
                const col = document.querySelector(`.kanban-col[data-team-id="${teamId}"]`);
                if (col) {
                    // Move members back to unassigned before removing
                    const cards = col.querySelectorAll('.student-card');
                    cards.forEach(card => moveCardDOM(card, 0));
                    col.style.opacity = '0';
                    col.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        col.remove();
                        updateStats();
                    }, 200);
                } else {
                    updateStats();
                }
                toast('Équipe supprimée', 'info');
            } else {
                toast(result.message || 'Erreur lors de la suppression', 'error');
            }
        }
    );
}

// ── Clear Team Members ──
function clearTeamMembers(teamId) {
    showConfirm(
        'Vider l\'équipe ?',
        'Tous les membres seront retirés mais resteront dans la classe.',
        async () => {
            const result = await api('clear_team', { team_id: teamId });
            if (result.success) {
                const body = document.getElementById('body-' + teamId);
                const cards = body.querySelectorAll('.student-card');
                cards.forEach(card => moveCardDOM(card, 0));
                showEmptyState(teamId);
                updateStats();
                toast('Membres retirés', 'info');
            } else {
                toast(result.message, 'error');
            }
        }
    );
}

// ── Delete All Teams ──
function deleteAllTeams() {
    const teamCount = document.querySelectorAll('.kanban-col:not(.unassigned)').length;
    if (!teamCount) return;
    showConfirm(
        'Supprimer toutes les équipes ?',
        'Les ' + teamCount + ' équipes seront supprimées. Les élèves resteront dans la classe.',
        async () => {
            const result = await api('delete_all_teams');
            if (result.success) {
                toast(result.message, 'info');
                setTimeout(() => location.reload(), 400);
            } else {
                toast(result.message || 'Erreur', 'error');
            }
        }
    );
}

// ── Auto Distribute ──
async function autoDistribute() {
    const result = await api('auto_distribute');
    if (result.success) {
        toast(result.message, 'success');
        setTimeout(() => location.reload(), 500);
    } else {
        toast(result.message, 'error');
    }
}

// ── Remove Member ──
async function removeMember(teamId, studentId, btn) {
    if (btn.classList.contains('processing')) return;
    btn.classList.add('processing');
    
    const result = await api('remove_member', { team_id: teamId, student_id: studentId });
    btn.classList.remove('processing');
    
    if (result.success) {
        const card = btn.closest('.student-card');
        moveCardDOM(card, 0);
        selectedStudents.delete(String(studentId));
        updateSelectionUI();
        updateStats();
    } else {
        toast(result.message || 'Erreur', 'error');
    }
}

// ── Confirm Dialog ──
let confirmCallback = null;
function showConfirm(title, message, callback) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    confirmCallback = callback;
    
    const btn = document.getElementById('confirmBtn');
    btn.onclick = async () => {
        const cb = confirmCallback;
        hideConfirm();
        if (cb) await cb();
    };
    
    document.getElementById('confirmOverlay').classList.add('show');
}
function hideConfirm() {
    document.getElementById('confirmOverlay').classList.remove('show');
    confirmCallback = null;
}

// ── DOM Helpers ──
function moveCardDOM(card, targetTeamId) {
    const targetBody = document.getElementById('body-' + targetTeamId);
    if (!targetBody) return;
    
    // Hide target empty state
    hideEmptyState(targetTeamId);
    
    // Update card data
    card.dataset.team = String(targetTeamId);
    
    // Remove the remove-button if moving to unassigned, or add if moving to team
    const existingRemove = card.querySelector('.card-remove');
    if (targetTeamId == 0) {
        if (existingRemove) existingRemove.remove();
        // Update avatar color for unassigned
        const avatar = card.querySelector('.card-avatar');
        if (avatar) avatar.style.background = 'linear-gradient(135deg, #f59e0b, #f97316)';
    } else {
        if (!existingRemove) {
            const grip = card.querySelector('.card-grip');
            const btn = document.createElement('button');
            btn.className = 'card-remove';
            btn.title = 'Retirer';
            btn.onclick = function() { removeMember(targetTeamId, card.dataset.studentId, this); };
            btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            card.insertBefore(btn, grip);
        } else {
            // Update onclick with new team id
            existingRemove.onclick = function() { removeMember(targetTeamId, card.dataset.studentId, this); };
        }
        // Reset avatar color
        const avatar = card.querySelector('.card-avatar');
        if (avatar) avatar.style.background = '';
    }
    
    // Animate
    card.style.opacity = '0';
    card.style.transform = 'translateY(-4px)';
    targetBody.appendChild(card);
    
    requestAnimationFrame(() => {
        card.style.transition = 'all 0.2s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
        setTimeout(() => card.style.transition = '', 200);
    });
    
    // Check source for empty
    checkAllEmptyStates();
    updateCounts();
}

function updateCounts() {
    document.querySelectorAll('.kanban-col').forEach(col => {
        const tid = col.dataset.teamId;
        const body = document.getElementById('body-' + tid);
        if (!body) return;
        const count = body.querySelectorAll('.student-card').length;
        const badge = document.getElementById('count-' + tid);
        if (badge) badge.textContent = count;
    });
}

function updateStats() {
    const unassignedBody = document.getElementById('body-0');
    const freeCount = unassignedBody ? unassignedBody.querySelectorAll('.student-card').length : 0;
    const assignedCount = totalStudents - freeCount;
    const pct = totalStudents > 0 ? Math.round((assignedCount / totalStudents) * 100) : 0;
    
    const statFree = document.getElementById('stat-free');
    if (statFree) statFree.textContent = freeCount;
    
    const statTeams = document.getElementById('stat-teams');
    if (statTeams) statTeams.textContent = document.querySelectorAll('.kanban-col:not(.unassigned)').length;
    
    const bar = document.getElementById('progress-bar');
    const text = document.getElementById('progress-text');
    if (bar) {
        bar.style.width = pct + '%';
        bar.style.background = pct === 100 ? '#22c55e' : '#6366f1';
    }
    if (text) {
        text.textContent = assignedCount + '/' + totalStudents;
        text.style.color = pct === 100 ? '#22c55e' : '#6366f1';
    }
    
    updateCounts();
}

function hideEmptyState(teamId) {
    const empty = document.getElementById('empty-' + teamId);
    if (empty) empty.style.display = 'none';
}
function showEmptyState(teamId) {
    const body = document.getElementById('body-' + teamId);
    if (!body) return;
    let empty = document.getElementById('empty-' + teamId);
    if (!empty) {
        empty = document.createElement('div');
        empty.className = 'kanban-empty';
        empty.id = 'empty-' + teamId;
        empty.innerHTML = teamId == 0
            ? '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><p>Tous assignés !</p>'
            : '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><p>Glissez des élèves ici</p>';
        body.appendChild(empty);
    }
    empty.style.display = 'block';
}
function checkAllEmptyStates() {
    document.querySelectorAll('.kanban-col-body').forEach(body => {
        const tid = body.dataset.teamId;
        const cards = body.querySelectorAll('.student-card').length;
        if (cards === 0) showEmptyState(tid);
        else hideEmptyState(tid);
    });
}

// ── Selection ──
function toggleSelect(card, e) {
    if (e) { e.stopPropagation(); e.preventDefault(); }
    const sid = card.dataset.studentId;
    if (selectedStudents.has(sid)) {
        selectedStudents.delete(sid);
        card.classList.remove('selected');
    } else {
        selectedStudents.add(sid);
        card.classList.add('selected');
    }
    updateSelectionUI();
}
function clearSelection() {
    selectedStudents.clear();
    document.querySelectorAll('.student-card.selected').forEach(c => c.classList.remove('selected'));
    updateSelectionUI();
}
function updateSelectionUI() {
    const bar = document.getElementById('selectionBar');
    const count = document.getElementById('selection-count');
    if (selectedStudents.size > 0) {
        bar.classList.add('active');
        count.textContent = selectedStudents.size;
    } else {
        bar.classList.remove('active');
    }
}

// Click handler for selection: Ctrl+click always works; normal click works when selection mode is active
document.addEventListener('click', (e) => {
    const cb = e.target.closest('.card-checkbox');
    if (cb) return; // handled by onclick
    
    const card = e.target.closest('.student-card');
    if (!card || e.target.closest('.card-remove')) return;
    
    // If Ctrl/Cmd held, or if already in selection mode (1+ selected), toggle
    if (e.ctrlKey || e.metaKey || selectedStudents.size > 0) {
        e.preventDefault();
        toggleSelect(card, e);
    }
});

// ── Search Filter ──
function filterStudents(input, teamId) {
    const term = input.value.toLowerCase();
    const body = document.getElementById('body-' + teamId);
    if (!body) return;
    
    body.querySelectorAll('.student-card').forEach(card => {
        const name = card.dataset.studentName.toLowerCase();
        card.classList.toggle('hidden', !name.includes(term));
    });
}

// ── Drag & Drop (Desktop) ──
document.addEventListener('dragstart', (e) => {
    const card = e.target.closest('.student-card');
    if (!card) return;
    
    dragState.el = card;
    dragState.studentId = card.dataset.studentId;
    dragState.fromTeam = card.dataset.team;
    
    card.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', dragState.studentId);
    
    // Drag selected cards visual
    if (selectedStudents.has(dragState.studentId)) {
        selectedStudents.forEach(id => {
            const c = document.querySelector(`[data-student-id="${id}"]`);
            if (c && c !== card) c.classList.add('dragging');
        });
    }
});

document.addEventListener('dragend', () => {
    document.querySelectorAll('.student-card.dragging').forEach(c => c.classList.remove('dragging'));
    document.querySelectorAll('.kanban-col.drag-over').forEach(c => c.classList.remove('drag-over'));
    dragState = { el: null, studentId: null, fromTeam: null };
});

// Attach dragover/dragleave/drop to all kanban bodies
document.addEventListener('dragover', (e) => {
    const col = e.target.closest('.kanban-col');
    if (!col || !dragState.el) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    if (col.dataset.teamId !== dragState.fromTeam) col.classList.add('drag-over');
});

document.addEventListener('dragleave', (e) => {
    const col = e.target.closest('.kanban-col');
    if (col && !col.contains(e.relatedTarget)) col.classList.remove('drag-over');
});

document.addEventListener('drop', async (e) => {
    e.preventDefault();
    const col = e.target.closest('.kanban-col');
    if (!col) return;
    col.classList.remove('drag-over');
    
    const targetTeamId = col.dataset.teamId;
    
    // Gather students to move
    let toMove = [];
    if (selectedStudents.size > 0 && selectedStudents.has(dragState.studentId)) {
        toMove = Array.from(selectedStudents);
    } else if (dragState.studentId) {
        toMove = [dragState.studentId];
    }
    
    if (!toMove.length) return;
    
    let ok = 0, lastErr = null;
    
    for (const sid of toMove) {
        const card = document.querySelector(`[data-student-id="${sid}"]`);
        if (!card) continue;
        const fromTeam = card.dataset.team;
        if (fromTeam === targetTeamId) continue;
        
        let result;
        if (fromTeam === '0' && targetTeamId !== '0') {
            result = await api('add_member', { team_id: targetTeamId, student_id: sid });
        } else if (fromTeam !== '0' && targetTeamId === '0') {
            result = await api('remove_member', { team_id: fromTeam, student_id: sid });
        } else {
            result = await api('move_member', { from_team_id: fromTeam, to_team_id: targetTeamId, student_id: sid });
        }
        
        if (result.success) {
            moveCardDOM(card, targetTeamId);
            card.classList.remove('selected');
            ok++;
        } else {
            lastErr = result.message;
        }
    }
    
    selectedStudents.clear();
    updateSelectionUI();
    updateStats();
    
    if (lastErr && ok === 0) toast(lastErr, 'error');
});

// ── Touch Drag & Drop (Mobile) ──
let touch = { el: null, clone: null, startX: 0, startY: 0 };

document.addEventListener('touchstart', (e) => {
    const card = e.target.closest('.student-card');
    if (card && !e.target.closest('.card-remove') && !e.target.closest('.card-checkbox')) {
        touch.startX = e.touches[0].clientX;
        touch.startY = e.touches[0].clientY;
        touch.el = card;
    }
}, { passive: true });

document.addEventListener('touchmove', (e) => {
    if (!touch.el) return;
    const x = e.touches[0].clientX, y = e.touches[0].clientY;
    
    if (!touch.clone && (Math.abs(x - touch.startX) > 10 || Math.abs(y - touch.startY) > 10)) {
        touch.clone = touch.el.cloneNode(true);
        Object.assign(touch.clone.style, {
            position: 'fixed', pointerEvents: 'none', zIndex: '9999',
            opacity: '0.85', transform: 'rotate(2deg) scale(1.04)',
            boxShadow: '0 8px 24px rgba(0,0,0,0.18)',
            width: touch.el.offsetWidth + 'px', transition: 'none'
        });
        document.body.appendChild(touch.clone);
        touch.el.style.opacity = '0.3';
        
        dragState.el = touch.el;
        dragState.studentId = touch.el.dataset.studentId;
        dragState.fromTeam = touch.el.dataset.team;
    }
    
    if (touch.clone) {
        touch.clone.style.left = (x - 130) + 'px';
        touch.clone.style.top = (y - 25) + 'px';
        
        const below = document.elementFromPoint(x, y);
        const col = below?.closest('.kanban-col');
        document.querySelectorAll('.kanban-col.drag-over').forEach(c => c.classList.remove('drag-over'));
        if (col && col.dataset.teamId !== dragState.fromTeam) col.classList.add('drag-over');
    }
}, { passive: true });

document.addEventListener('touchend', async (e) => {
    if (touch.clone) {
        const x = e.changedTouches[0].clientX, y = e.changedTouches[0].clientY;
        touch.clone.remove();
        touch.el.style.opacity = '';
        
        const below = document.elementFromPoint(x, y);
        const col = below?.closest('.kanban-col');
        
        if (col && col.dataset.teamId !== dragState.fromTeam) {
            // Simulate drop
            const targetTeamId = col.dataset.teamId;
            let toMove = selectedStudents.size > 0 && selectedStudents.has(dragState.studentId)
                ? Array.from(selectedStudents) : [dragState.studentId];
            
            for (const sid of toMove) {
                const card = document.querySelector(`[data-student-id="${sid}"]`);
                if (!card) continue;
                const fromTeam = card.dataset.team;
                if (fromTeam === targetTeamId) continue;
                
                let result;
                if (fromTeam === '0' && targetTeamId !== '0') {
                    result = await api('add_member', { team_id: targetTeamId, student_id: sid });
                } else if (fromTeam !== '0' && targetTeamId === '0') {
                    result = await api('remove_member', { team_id: fromTeam, student_id: sid });
                } else {
                    result = await api('move_member', { from_team_id: fromTeam, to_team_id: targetTeamId, student_id: sid });
                }
                
                if (result.success) {
                    moveCardDOM(card, targetTeamId);
                    card.classList.remove('selected');
                }
            }
            
            selectedStudents.clear();
            updateSelectionUI();
            updateStats();
        }
        
        document.querySelectorAll('.kanban-col.drag-over').forEach(c => c.classList.remove('drag-over'));
    }
    
    touch = { el: null, clone: null, startX: 0, startY: 0 };
    dragState = { el: null, studentId: null, fromTeam: null };
});

// ── Searchable Dropdown ──
function sdToggle(id) {
    const wrap = document.getElementById(id);
    const wasOpen = wrap.classList.contains('open');
    document.querySelectorAll('.sd-wrap.open').forEach(w => w.classList.remove('open'));
    if (!wasOpen) {
        wrap.classList.add('open');
        const input = wrap.querySelector('.sd-search');
        if (input) { input.value = ''; sdFilter(input); input.focus(); }
    }
}
function sdFilter(input) {
    const query = input.value.toLowerCase().trim();
    const list = input.closest('.sd-panel').querySelector('.sd-list');
    const opts = list.querySelectorAll('.sd-opt');
    let visible = 0;
    opts.forEach(opt => {
        const match = !query || opt.textContent.toLowerCase().includes(query);
        opt.classList.toggle('hidden', !match);
        if (match) visible++;
    });
    let emptyEl = list.querySelector('.sd-empty');
    if (visible === 0) {
        if (!emptyEl) {
            emptyEl = document.createElement('div');
            emptyEl.className = 'sd-empty';
            emptyEl.textContent = 'Aucun résultat';
            list.appendChild(emptyEl);
        }
        emptyEl.style.display = '';
    } else if (emptyEl) {
        emptyEl.style.display = 'none';
    }
}
document.addEventListener('click', (e) => {
    if (!e.target.closest('.sd-wrap')) {
        document.querySelectorAll('.sd-wrap.open').forEach(w => w.classList.remove('open'));
    }
});

// ── Send-To Dropdown ──
function toggleSendTo() {
    const wrap = document.querySelector('.send-to-wrap');
    if (wrap.classList.toggle('open')) buildSendToMenu();
}
function closeSendTo() {
    document.querySelector('.send-to-wrap')?.classList.remove('open');
}
function buildSendToMenu() {
    const menu = document.getElementById('sendToMenu');
    menu.innerHTML = '';

    // Determine which teams the selected students are NOT currently in
    const selectedTeams = new Set();
    selectedStudents.forEach(sid => {
        const card = document.querySelector(`[data-student-id="${sid}"]`);
        if (card) selectedTeams.add(card.dataset.team);
    });

    // Add "Non assignés" option
    if (!selectedTeams.has('0') || selectedTeams.size > 1) {
        const unBody = document.getElementById('body-0');
        const unCount = unBody ? unBody.querySelectorAll('.student-card').length : 0;
        const item = document.createElement('button');
        item.className = 'send-to-item';
        item.innerHTML = '<span class="sti-dot" style="background:#f59e0b"></span>' +
            '<span class="sti-name">Non assignés</span>' +
            '<span class="sti-count">' + unCount + '</span>';
        item.onclick = () => sendSelectedTo('0');
        menu.appendChild(item);
    }

    // Add team columns
    document.querySelectorAll('.kanban-col:not(.unassigned)').forEach(col => {
        const tid = col.dataset.teamId;
        const tname = col.dataset.teamName;
        const body = document.getElementById('body-' + tid);
        const cnt = body ? body.querySelectorAll('.student-card').length : 0;
        const item = document.createElement('button');
        item.className = 'send-to-item';
        item.innerHTML = '<span class="sti-dot" style="background:#6366f1"></span>' +
            '<span class="sti-name">' + tname + '</span>' +
            '<span class="sti-count">' + cnt + '</span>';
        item.onclick = () => sendSelectedTo(tid);
        menu.appendChild(item);
    });

    if (!menu.children.length) {
        menu.innerHTML = '<div style="padding:12px 14px;text-align:center;font-size:12px;color:rgba(0,0,0,0.3)">Aucune équipe</div>';
    }
}

async function sendSelectedTo(targetTeamId) {
    closeSendTo();
    if (!selectedStudents.size) return;

    const toMove = Array.from(selectedStudents);
    let ok = 0, lastErr = null;

    for (const sid of toMove) {
        const card = document.querySelector(`[data-student-id="${sid}"]`);
        if (!card) continue;
        const fromTeam = card.dataset.team;
        if (fromTeam === targetTeamId) continue;

        let result;
        if (fromTeam === '0' && targetTeamId !== '0') {
            result = await api('add_member', { team_id: targetTeamId, student_id: sid });
        } else if (fromTeam !== '0' && targetTeamId === '0') {
            result = await api('remove_member', { team_id: fromTeam, student_id: sid });
        } else {
            result = await api('move_member', { from_team_id: fromTeam, to_team_id: targetTeamId, student_id: sid });
        }

        if (result.success) {
            moveCardDOM(card, targetTeamId);
            card.classList.remove('selected');
            ok++;
        } else {
            lastErr = result.message;
        }
    }

    selectedStudents.clear();
    updateSelectionUI();
    updateStats();

    if (ok > 0) {
        const tname = targetTeamId === '0' ? 'Non assignés'
            : (document.querySelector(`.kanban-col[data-team-id="${targetTeamId}"]`)?.dataset.teamName || 'équipe');
        toast(ok + ' élève(s) envoyé(s) à ' + tname, 'success');
    }
    if (lastErr && ok === 0) toast(lastErr, 'error');
}

// Close send-to on outside click
document.addEventListener('click', (e) => {
    if (!e.target.closest('.send-to-wrap')) closeSendTo();
});

// Close panels on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        hideCreateTeam();
        hideBatchCreate();
        hideConfirm();
        closeSendTo();
        document.querySelectorAll('.sd-wrap.open').forEach(w => w.classList.remove('open'));
    }
});
</script>

<?php include 'views/partials/footer.php'; ?>
