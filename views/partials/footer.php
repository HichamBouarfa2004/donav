    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- PWA Registration -->
    <script src="assets/js/pwa.js"></script>
    
    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Toast Notification System -->
    <style>
    .toast-stack{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px}
    .toast-msg{padding:12px 18px;border-radius:12px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;box-shadow:0 6px 20px rgba(0,0,0,0.15);transform:translateX(120%);opacity:0;transition:all 0.3s cubic-bezier(0.4,0,0.2,1);max-width:340px;color:#fff}
    .toast-msg.show{transform:translateX(0);opacity:1}
    .toast-msg.success{background:#22c55e}
    .toast-msg.error{background:#ef4444}
    .toast-msg.info{background:#1c1c1c}
    </style>
    <div class="toast-stack" id="toastStack"></div>
    <script>
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
