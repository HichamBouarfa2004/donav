<?php
require_once 'config/database.php';
require_once 'classes/Teacher.php';

$error = '';
$success = '';

// Flash message from registration redirect
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $teacher = new Teacher($db);
    $teacher->email = $_POST['email'] ?? '';
    $teacher->mot_de_passe = $_POST['password'] ?? '';
    
    if (empty($teacher->email) || empty($teacher->mot_de_passe)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        if ($teacher->login()) {
            $_SESSION['teacher_id'] = $teacher->id;
            $_SESSION['teacher_name'] = $teacher->nom;
            $_SESSION['teacher_email'] = $teacher->email;
            
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1c1c1c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="No9ati">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Connexion - No9ati</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    <link rel="apple-touch-icon" href="assets/icons/icon-152x152.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    /* ===== Auth Page Styles ===== */
    .auth-wrapper {
        min-height: 100vh;
        display: flex;
        background: #f9f9fa;
    }

    /* Left branding panel */
    .auth-brand-panel {
        flex: 0 0 45%;
        background: #1c1c1c;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 60px;
        position: relative;
        overflow: hidden;
    }

    .auth-brand-panel::before {
        content: '';
        position: absolute;
        top: -80px;
        right: -80px;
        width: 300px;
        height: 300px;
        background: #edeefc;
        border-radius: 50%;
        opacity: 0.07;
        animation: floatShape 8s ease-in-out infinite;
    }

    .auth-brand-panel::after {
        content: '';
        position: absolute;
        bottom: -60px;
        left: -60px;
        width: 220px;
        height: 220px;
        background: #e8f4fd;
        border-radius: 50%;
        opacity: 0.07;
        animation: floatShape 10s ease-in-out infinite reverse;
    }

    @keyframes floatShape {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33% { transform: translate(20px, -30px) scale(1.05); }
        66% { transform: translate(-15px, 15px) scale(0.97); }
    }

    .brand-content {
        position: relative;
        z-index: 1;
        text-align: center;
        max-width: 360px;
    }

    .brand-logo-box {
        width: 72px;
        height: 72px;
        background: rgba(255,255,255,0.1);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 32px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.08);
    }

    .brand-title {
        font-family: 'Inter', sans-serif;
        font-size: 36px;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 12px;
        letter-spacing: -0.5px;
    }

    .brand-subtitle {
        font-size: 15px;
        color: rgba(255,255,255,0.5);
        line-height: 1.7;
        margin-bottom: 48px;
    }

    /* Feature cards on brand panel */
    .brand-features {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
    }

    .brand-feature {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 18px;
        background: rgba(255,255,255,0.05);
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.06);
        transition: all 0.3s ease;
    }

    .brand-feature:hover {
        background: rgba(255,255,255,0.08);
        transform: translateX(4px);
    }

    .brand-feature-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .brand-feature-icon.purple { background: rgba(237,238,252,0.15); }
    .brand-feature-icon.blue { background: rgba(232,244,253,0.15); }
    .brand-feature-icon.green { background: rgba(230,249,240,0.15); }

    .brand-feature span {
        font-size: 13px;
        color: rgba(255,255,255,0.7);
        font-weight: 500;
    }

    /* Right form panel */
    .auth-form-panel {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
    }

    .auth-form-container {
        width: 100%;
        max-width: 420px;
        animation: fadeSlideUp 0.5s ease-out;
    }

    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .auth-form-header {
        margin-bottom: 36px;
    }

    .auth-form-header h2 {
        font-family: 'Inter', sans-serif;
        font-size: 26px;
        font-weight: 700;
        color: #1c1c1c;
        margin-bottom: 8px;
    }

    .auth-form-header p {
        font-size: 14px;
        color: rgba(0,0,0,0.4);
        margin: 0;
    }

    /* Form controls */
    .auth-form .form-group {
        margin-bottom: 20px;
    }

    .auth-form .form-label {
        font-size: 13px;
        font-weight: 600;
        color: #1c1c1c;
        margin-bottom: 8px;
        display: block;
        letter-spacing: 0.01em;
    }

    .auth-form .input-wrapper {
        position: relative;
    }

    .auth-form .input-wrapper .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(0,0,0,0.3);
        pointer-events: none;
        transition: color 0.2s ease;
    }

    .auth-form .form-control {
        border: 1.5px solid rgba(0,0,0,0.08);
        border-radius: 12px;
        padding: 13px 16px 13px 44px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        background: #ffffff;
        transition: all 0.2s ease;
        color: #1c1c1c;
    }

    .auth-form .form-control:focus {
        border-color: #1c1c1c;
        box-shadow: 0 0 0 3px rgba(28,28,28,0.06);
        outline: none;
    }

    .auth-form .form-control:focus ~ .input-icon,
    .auth-form .form-control:focus + .input-icon {
        color: #1c1c1c;
    }

    .auth-form .form-control::placeholder {
        color: rgba(0,0,0,0.25);
    }

    .toggle-password {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: rgba(0,0,0,0.3);
        cursor: pointer;
        padding: 0;
        transition: color 0.2s;
    }

    .toggle-password:hover {
        color: #1c1c1c;
    }

    /* Submit button */
    .auth-btn {
        width: 100%;
        padding: 14px 24px;
        background: #1c1c1c;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .auth-btn:hover {
        background: #333333;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(28,28,28,0.2);
    }

    .auth-btn:active {
        transform: translateY(0);
        box-shadow: none;
    }

    .auth-divider {
        display: flex;
        align-items: center;
        gap: 16px;
        margin: 28px 0;
    }

    .auth-divider::before,
    .auth-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(0,0,0,0.08);
    }

    .auth-divider span {
        font-size: 12px;
        color: rgba(0,0,0,0.3);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .auth-footer {
        text-align: center;
        font-size: 14px;
        color: rgba(0,0,0,0.4);
    }

    .auth-footer a {
        color: #1c1c1c;
        font-weight: 600;
        text-decoration: none;
        transition: opacity 0.2s;
    }

    .auth-footer a:hover {
        opacity: 0.7;
    }

    /* Mobile: hide brand panel */
    @media (max-width: 991px) {
        .auth-brand-panel { display: none; }
        .auth-form-panel { padding: 24px; }
        .auth-form-container {
            max-width: 100%;
        }
        .auth-form-header { text-align: center; }
        .mobile-logo {
            display: flex !important;
            width: 56px;
            height: 56px;
            background: #1c1c1c;
            border-radius: 16px;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
    }

    @media (min-width: 992px) {
        .mobile-logo { display: none !important; }
    }

    /* Toast */
    .toast-stack{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px}
    .toast-msg{padding:12px 18px;border-radius:12px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;box-shadow:0 6px 20px rgba(0,0,0,0.15);transform:translateX(120%);opacity:0;transition:all 0.3s cubic-bezier(0.4,0,0.2,1);max-width:340px;color:#fff}
    .toast-msg.show{transform:translateX(0);opacity:1}
    .toast-msg.success{background:#22c55e}
    .toast-msg.error{background:#ef4444}
    .toast-msg.info{background:#1c1c1c}
    </style>
</head>
<body>
    <?php if ($error): ?>
        <script>var _toastError = <?= json_encode($error) ?>;</script>
    <?php endif; ?>
    <?php if ($success): ?>
        <script>var _toastSuccess = <?= json_encode($success) ?>;</script>
    <?php endif; ?>

    <div class="auth-wrapper">
        <!-- Left Brand Panel -->
        <div class="auth-brand-panel">
            <div class="brand-content">
                <div class="brand-logo-box">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                    </svg>
                </div>
                <h1 class="brand-title">No9ati</h1>
                <p class="brand-subtitle">Votre plateforme intelligente pour la gestion des notes, absences et classes.</p>

                <div class="brand-features">
                    <div class="brand-feature">
                        <div class="brand-feature-icon purple">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a5a6f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <span>Gestion des classes et des groupes</span>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon blue">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                        <span>Controles et baremes personnalisables</span>
                    </div>
                    <div class="brand-feature">
                        <div class="brand-feature-icon green">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                        <span>Suivi des absences au quotidien</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="auth-form-panel">
            <div class="auth-form-container">
                <!-- Mobile-only logo -->
                <div class="mobile-logo" style="display:none;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                    </svg>
                </div>

                <div class="auth-form-header">
                    <h2>Bon retour!</h2>
                    <p>Connectez-vous a votre espace enseignant</p>
                </div>

                <form method="POST" action="index.php?page=login" class="auth-form">
                    <div class="form-group">
                        <label class="form-label" for="email">Adresse email</label>
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="votre@email.com" required autocomplete="email">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Mot de passe</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Votre mot de passe" required autocomplete="current-password">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <button type="button" class="toggle-password" onclick="togglePwd(this)" aria-label="Afficher le mot de passe">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-open">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-closed" style="display:none">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="auth-btn">
                        Se connecter
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px;vertical-align:middle">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </button>
                </form>

                <div class="auth-divider"><span>ou</span></div>

                <div class="auth-footer">
                    Pas encore de compte ? <a href="index.php?page=register">Creer un compte</a>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-stack" id="toastStack"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/pwa.js"></script>
    <script>
    function togglePwd(btn) {
        var wrap = btn.closest('.input-wrapper');
        var inp = wrap.querySelector('input');
        var open = btn.querySelector('.eye-open');
        var closed = btn.querySelector('.eye-closed');
        if (inp.type === 'password') {
            inp.type = 'text';
            open.style.display = 'none';
            closed.style.display = 'block';
        } else {
            inp.type = 'password';
            open.style.display = 'block';
            closed.style.display = 'none';
        }
    }
    function _showToast(msg,type){
        var s=document.getElementById('toastStack');if(!s){s=document.createElement('div');s.id='toastStack';s.className='toast-stack';document.body.appendChild(s);}
        var e=document.createElement('div');e.className='toast-msg '+type;
        var icons={success:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',error:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',info:'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'};
        e.innerHTML=(icons[type]||icons.info)+'<span>'+msg+'</span>';
        s.appendChild(e);
        requestAnimationFrame(function(){e.classList.add('show')});
        setTimeout(function(){e.classList.remove('show');setTimeout(function(){e.remove()},300)},3500);
    }
    document.addEventListener('DOMContentLoaded',function(){
        if(typeof _toastError!=='undefined')_showToast(_toastError,'error');
        if(typeof _toastSuccess!=='undefined')_showToast(_toastSuccess,'success');
        if(typeof PWAManager!=='undefined') window.pwaManager=new PWAManager();
    });
    </script>
</body>
</html>