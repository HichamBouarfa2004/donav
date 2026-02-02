// Main JavaScript functionality for No9ati platform

class No9atiApp {
    constructor() {
        this.initializeEventListeners();
        this.initializeModals();
        this.initializeTables();
        this.initializeAlerts();
        this.initializePWA();
    }

    initializePWA() {
        // Load PWA functionality if available
        if (typeof PWAManager !== 'undefined') {
            this.pwaManager = new PWAManager();
        }
        
        // Register service worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/no9ati/sw.js')
                    .then(registration => {
                        console.log('SW registered: ', registration);
                    })
                    .catch(registrationError => {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
    }

    initializeEventListeners() {
        // Mobile menu toggle
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });
        }

        // Form submissions
        this.initializeFormHandlers();
        
        // Points management
        this.initializePointsHandlers();
        
        // File uploads
        this.initializeFileUploadHandlers();
    }

    initializeFormHandlers() {
        // Generic form handler with loading states
        const forms = document.querySelectorAll('form[data-ajax]');
        
        forms.forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleFormSubmission(form);
            });
        });
    }

    async handleFormSubmission(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Show loading state
        submitBtn.textContent = 'Chargement...';
        submitBtn.disabled = true;
        form.classList.add('loading');
        
        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', result.message || 'Opération réussie!');
                
                // Handle different success actions
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1500);
                } else if (result.reload) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else if (result.reset) {
                    form.reset();
                }
            } else {
                this.showAlert('danger', result.message || 'Une erreur est survenue.');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showAlert('danger', 'Erreur de connexion. Veuillez réessayer.');
        } finally {
            // Restore button state
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            form.classList.remove('loading');
        }
    }

    initializePointsHandlers() {
        // Add points buttons
        const addPointsBtns = document.querySelectorAll('.add-points-btn');
        
        addPointsBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const studentId = btn.dataset.studentId;
                const studentName = btn.dataset.studentName;
                this.showAddPointsModal(studentId, studentName);
            });
        });

        // Certificate generation
        const generateCertBtns = document.querySelectorAll('.generate-cert-btn');
        
        generateCertBtns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const studentId = btn.dataset.studentId;
                await this.generateCertificate(studentId);
            });
        });
    }

    showAddPointsModal(studentId, studentName) {
        const modal = document.getElementById('addPointsModal');
        const form = modal.querySelector('form');
        const studentNameSpan = modal.querySelector('.student-name');
        
        studentNameSpan.textContent = studentName;
        form.querySelector('input[name="student_id"]').value = studentId;
        
        this.showModal('addPointsModal');
    }

    async generateCertificate(studentId) {
        try {
            this.showAlert('info', 'Génération du certificat en cours...');
            
            const response = await fetch('index.php?page=generate_certificate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `student_id=${studentId}`
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `certificat_${studentId}.pdf`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                this.showAlert('success', 'Certificat généré et téléchargé!');
            } else {
                throw new Error('Erreur lors de la génération');
            }
        } catch (error) {
            console.error('Certificate generation error:', error);
            this.showAlert('danger', 'Erreur lors de la génération du certificat.');
        }
    }

    initializeFileUploadHandlers() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.validateFileUpload(file, input);
                }
            });
        });
    }

    validateFileUpload(file, input) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        
        if (file.size > maxSize) {
            this.showAlert('danger', 'Le fichier est trop volumineux (max 5MB).');
            input.value = '';
            return false;
        }
        
        if (!allowedTypes.includes(file.type)) {
            this.showAlert('danger', 'Format de fichier non supporté. Utilisez Excel (.xlsx, .xls).');
            input.value = '';
            return false;
        }
        
        // Show file info
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        this.showAlert('info', `Fichier sélectionné: ${fileName} (${fileSize} MB)`);
        
        return true;
    }

    initializeModals() {
        // Modal close handlers
        const modalCloses = document.querySelectorAll('.modal-close, [data-modal-close]');
        
        modalCloses.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const modal = btn.closest('.modal');
                if (modal) {
                    this.hideModal(modal.id);
                }
            });
        });

        const usesBootstrapModal = typeof window !== 'undefined' && window.bootstrap && window.bootstrap.Modal;
        // Click outside to close
        const modals = document.querySelectorAll('.modal');
        
        modals.forEach(modal => {
            if (!usesBootstrapModal) {
                modal.style.display = 'none';

                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        this.hideModal(modal.id);
                    }
                });
            }
        });
    }

    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            if (typeof window !== 'undefined' && window.bootstrap && window.bootstrap.Modal) {
                const modalInstance = window.bootstrap.Modal.getOrCreateInstance(modal);
                modalInstance.show();
                return;
            }

            modal.classList.add('show');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            if (typeof window !== 'undefined' && window.bootstrap && window.bootstrap.Modal) {
                const modalInstance = window.bootstrap.Modal.getInstance(modal) || window.bootstrap.Modal.getOrCreateInstance(modal);
                modalInstance.hide();
                return;
            }

            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    initializeTables() {
        // Sortable tables
        const tables = document.querySelectorAll('.sortable');
        
        tables.forEach(table => {
            this.makeSortable(table);
        });

        // Search functionality
        const searchInputs = document.querySelectorAll('.table-search');
        
        searchInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const tableId = input.dataset.table;
                const searchTerm = e.target.value.toLowerCase();
                this.filterTable(tableId, searchTerm);
            });
        });
    }

    makeSortable(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const column = header.dataset.sort;
                const currentSort = header.dataset.sortDir || 'asc';
                const newSort = currentSort === 'asc' ? 'desc' : 'asc';
                
                // Reset all headers
                headers.forEach(h => {
                    h.dataset.sortDir = '';
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                
                // Set current header
                header.dataset.sortDir = newSort;
                header.classList.add(`sort-${newSort}`);
                
                this.sortTable(table, column, newSort);
            });
        });
    }

    sortTable(table, column, direction) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const aVal = a.querySelector(`[data-sort-value="${column}"]`)?.textContent.trim() || 
                        a.cells[this.getColumnIndex(table, column)]?.textContent.trim() || '';
            const bVal = b.querySelector(`[data-sort-value="${column}"]`)?.textContent.trim() || 
                        b.cells[this.getColumnIndex(table, column)]?.textContent.trim() || '';
            
            // Check if values are numeric
            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            } else {
                return direction === 'asc' ? 
                    aVal.localeCompare(bVal) : 
                    bVal.localeCompare(aVal);
            }
        });
        
        // Reinsert sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }

    getColumnIndex(table, column) {
        const headers = table.querySelectorAll('th');
        for (let i = 0; i < headers.length; i++) {
            if (headers[i].dataset.sort === column) {
                return i;
            }
        }
        return 0;
    }

    filterTable(tableId, searchTerm) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    initializeAlerts() {
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert:not(.alert-persistent)');
        
        alerts.forEach(alert => {
            setTimeout(() => {
                this.hideAlert(alert);
            }, 5000);
        });

        // Close button functionality
        const alertCloses = document.querySelectorAll('.alert-close');
        
        alertCloses.forEach(btn => {
            btn.addEventListener('click', () => {
                const alert = btn.closest('.alert');
                this.hideAlert(alert);
            });
        });
    }

    showAlert(type, message, persistent = false) {
        const alertContainer = document.getElementById('alertContainer') || this.createAlertContainer();
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} ${persistent ? 'alert-persistent' : ''} fade-in`;
        alert.innerHTML = `
            <span>${message}</span>
            <button type="button" class="alert-close" aria-label="Fermer">×</button>
        `;
        
        alertContainer.appendChild(alert);
        
        // Add close functionality
        const closeBtn = alert.querySelector('.alert-close');
        closeBtn.addEventListener('click', () => {
            this.hideAlert(alert);
        });
        
        // Auto-hide if not persistent
        if (!persistent) {
            setTimeout(() => {
                this.hideAlert(alert);
            }, 5000);
        }
    }

    hideAlert(alert) {
        if (alert) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        }
    }

    createAlertContainer() {
        const container = document.createElement('div');
        container.id = 'alertContainer';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
        return container;
    }

    // Utility functions
    formatDate(date) {
        return new Intl.DateTimeFormat('fr-FR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }

    formatNumber(number) {
        return new Intl.NumberFormat('fr-FR').format(number);
    }

    calculateProgress(current, target = 100) {
        return Math.min((current / target) * 100, 100);
    }

    updateProgressBar(progressBar, value, animated = true) {
        if (!progressBar) return;
        
        const percentage = Math.min(Math.max(value, 0), 100);
        
        if (animated) {
            progressBar.style.transition = 'width 0.6s ease';
        }
        
        progressBar.style.width = `${percentage}%`;
        
        // Update color based on progress
        progressBar.classList.remove('success', 'warning');
        if (percentage >= 80) {
            progressBar.classList.add('success');
        } else if (percentage >= 50) {
            progressBar.classList.add('warning');
        }
    }

    // Real-time updates
    startRealTimeUpdates() {
        if (window.location.pathname.includes('dashboard')) {
            setInterval(() => {
                this.updateDashboardStats();
            }, 30000); // Update every 30 seconds
        }
    }

    async updateDashboardStats() {
        try {
            const response = await fetch('api/dashboard-stats.php');
            const stats = await response.json();
            
            if (stats.success) {
                this.updateStatCards(stats.data);
            }
        } catch (error) {
            console.error('Error updating dashboard stats:', error);
        }
    }

    updateStatCards(data) {
        // Update stat numbers with animation
        Object.keys(data).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                this.animateNumber(element, parseInt(element.textContent), data[key]);
            }
        });
    }

    animateNumber(element, from, to, duration = 1000) {
        const startTime = performance.now();
        const difference = to - from;
        
        const step = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const current = Math.floor(from + (difference * progress));
            element.textContent = this.formatNumber(current);
            
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };
        
        requestAnimationFrame(step);
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.no9atiApp = new No9atiApp();
    
    // Start real-time updates if enabled
    if (document.body.dataset.realTimeUpdates === 'true') {
        window.no9atiApp.startRealTimeUpdates();
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = No9atiApp;
}