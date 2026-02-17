<?php
require_once 'config/database.php';
require_once 'classes/Teacher.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $teacher = new Teacher($db);
    $teacher->nom = $_POST['nom'] ?? '';
    $teacher->email = $_POST['email'] ?? '';
    $teacher->mot_de_passe = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($teacher->nom) || empty($teacher->email) || empty($teacher->mot_de_passe) || empty($confirm_password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif ($teacher->mot_de_passe !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($teacher->mot_de_passe) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        if ($teacher->register()) {
            $_SESSION['success'] = 'Compte créé avec succès! Vous pouvez maintenant vous connecter.';
            header('Location: index.php?page=login');
            exit;
        } else {
            $error = 'Erreur lors de la création du compte. L\'email existe peut-être déjà.';
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
    <title>Inscription - No9ati</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
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
        background: #e6f9f0;
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
        background: #fef4e6;
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

    /* Steps on brand panel */
    .brand-steps {
        display: flex;
        flex-direction: column;
        gap: 16px;
        width: 100%;
    }

    .brand-step {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 18px;
        background: rgba(255,255,255,0.05);
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.06);
        transition: all 0.3s ease;
    }

    .brand-step:hover {
        background: rgba(255,255,255,0.08);
        transform: translateX(4px);
    }

    .brand-step-num {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .brand-step-num.s1 { background: rgba(237,238,252,0.15); color: #a5a6f6; }
    .brand-step-num.s2 { background: rgba(232,244,253,0.15); color: #60a5fa; }
    .brand-step-num.s3 { background: rgba(230,249,240,0.15); color: #4ade80; }

    .brand-step-text {
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
        max-width: 440px;
        animation: fadeSlideUp 0.5s ease-out;
    }

    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .auth-form-header {
        margin-bottom: 32px;
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
        margin-bottom: 18px;
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

    /* Password strength */
    .pwd-strength {
        display: flex;
        gap: 4px;
        margin-top: 8px;
    }

    .pwd-strength-bar {
        flex: 1;
        height: 3px;
        border-radius: 2px;
        background: rgba(0,0,0,0.08);
        transition: background 0.3s ease;
    }

    .pwd-strength-bar.active.weak { background: #ef4444; }
    .pwd-strength-bar.active.medium { background: #f59e0b; }
    .pwd-strength-bar.active.strong { background: #22c55e; }

    .pwd-strength-label {
        font-size: 11px;
        margin-top: 4px;
        color: rgba(0,0,0,0.4);
        font-weight: 500;
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
        margin-top: 6px;
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
        margin: 24px 0;
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

    /* Two-column row */
    .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    /* Mobile: hide brand panel */
    @media (max-width: 991px) {
        .auth-brand-panel { display: none; }
        .auth-form-panel { padding: 24px; }
        .auth-form-container { max-width: 100%; }
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
        .form-row-2 {
            grid-template-columns: 1fr;
            gap: 0;
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
                <p class="brand-subtitle">Rejoignez des enseignants qui simplifient leur quotidien.</p>

                <div class="brand-steps">
                    <div class="brand-step">
                        <div class="brand-step-num s1">1</div>
                        <span class="brand-step-text">Creez votre compte en quelques secondes</span>
                    </div>
                    <div class="brand-step">
                        <div class="brand-step-num s2">2</div>
                        <span class="brand-step-text">Ajoutez vos classes et vos eleves</span>
                    </div>
                    <div class="brand-step">
                        <div class="brand-step-num s3">3</div>
                        <span class="brand-step-text">Gerez notes et absences facilement</span>
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
                    <h2>Creer un compte</h2>
                    <p>Commencez a gerer vos classes en toute simplicite</p>
                </div>

                <form method="POST" action="index.php?page=register" class="auth-form">
                    <div class="form-group">
                        <label class="form-label" for="nom">Nom complet</label>
                        <div class="input-wrapper">
                            <input type="text" id="nom" name="nom" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                   placeholder="Votre nom complet" required autocomplete="name">
                            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                    </div>

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

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="password">Mot de passe</label>
                            <div class="input-wrapper">
                                <input type="password" id="password" name="password" class="form-control"
                                       placeholder="Min. 6 caracteres" required autocomplete="new-password"
                                       oninput="checkStrength(this.value)">
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

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirmer</label>
                            <div class="input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                       placeholder="Confirmer" required autocomplete="new-password"
                                       style="padding-left:16px;">
                            </div>
                        </div>
                    </div>

                    <div class="pwd-strength" id="pwdStrength">
                        <div class="pwd-strength-bar" id="bar1"></div>
                        <div class="pwd-strength-bar" id="bar2"></div>
                        <div class="pwd-strength-bar" id="bar3"></div>
                        <div class="pwd-strength-bar" id="bar4"></div>
                    </div>
                    <div class="pwd-strength-label" id="pwdLabel"></div>

                    <button type="submit" class="auth-btn">
                        Creer mon compte
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:6px;vertical-align:middle">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                    </button>
                </form>

                <div class="auth-divider"><span>ou</span></div>

                <div class="auth-footer">
                    Deja un compte ? <a href="index.php?page=login">Se connecter</a>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-stack" id="toastStack"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
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
    function checkStrength(val) {
        var score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        var level = val.length === 0 ? 0 : score <= 1 ? 1 : score <= 3 ? 2 : 3;
        var labels = ['', 'Faible', 'Moyen', 'Fort'];
        var classes = ['', 'weak', 'medium', 'strong'];
        for (var i = 1; i <= 4; i++) {
            var bar = document.getElementById('bar' + i);
            bar.className = 'pwd-strength-bar';
            if (i <= level + 1 && level > 0) bar.classList.add('active', classes[level]);
        }
        document.getElementById('pwdLabel').textContent = labels[level];
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
    });
    </script>
</body>
</html>