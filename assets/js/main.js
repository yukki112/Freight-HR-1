// assets/js/main.js

// ========== SIDEBAR FUNCTIONS ==========
function toggleSidebar() {
    const sidebar = document.querySelector('.unique-sidebar');
    const main = document.querySelector('.unique-main');
    const isCollapsed = sidebar.classList.contains('collapsed');
    
    if (isCollapsed) {
        sidebar.classList.remove('collapsed');
        main.style.marginLeft = window.innerWidth > 768 ? '320px' : '0';
        document.cookie = 'sidebar=expanded; path=/';
    } else {
        sidebar.classList.add('collapsed');
        main.style.marginLeft = window.innerWidth > 768 ? '100px' : '0';
        document.cookie = 'sidebar=collapsed; path=/';
    }
}

// Mobile Menu Toggle
function toggleMobileMenu() {
    const sidebar = document.querySelector('.unique-sidebar');
    const icon = document.querySelector('.mobile-menu-btn i');
    
    sidebar.classList.toggle('mobile-visible');
    
    if (sidebar.classList.contains('mobile-visible')) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
        document.body.style.overflow = 'hidden';
    } else {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
        document.body.style.overflow = '';
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.unique-sidebar');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    
    if (window.innerWidth <= 768) {
        if (sidebar && menuBtn) {
            if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                sidebar.classList.remove('mobile-visible');
                const icon = document.querySelector('.mobile-menu-btn i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
                document.body.style.overflow = '';
            }
        }
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.unique-sidebar');
    const main = document.querySelector('.unique-main');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const icon = menuBtn ? menuBtn.querySelector('i') : null;
    
    if (window.innerWidth > 768) {
        if (sidebar) {
            sidebar.classList.remove('mobile-visible');
            const isCollapsed = sidebar.classList.contains('collapsed');
            main.style.marginLeft = isCollapsed ? '100px' : '320px';
        }
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
        document.body.style.overflow = '';
    } else {
        main.style.marginLeft = '0';
        if (sidebar) {
            sidebar.classList.remove('mobile-visible');
        }
    }
});

// ========== MODAL FUNCTIONS ==========
function showNotifications() {
    document.getElementById('notificationsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function hideNotifications() {
    document.getElementById('notificationsModal').classList.remove('active');
    document.body.style.overflow = '';
}

function showMessages() {
    alert('Messages feature coming soon!');
}

// ========== NOTIFICATION SYSTEM ==========
function showNotification(message, type = 'info') {
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notif => notif.remove());
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification-toast`;
    
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    
    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
    `;
    
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '90%';
    notification.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
    notification.style.animation = 'slideInRight 0.3s ease';
    notification.style.wordBreak = 'break-word';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ========== SEARCH FUNCTIONALITY ==========
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                searchShipments(this.value);
            }
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }
    });
    
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });
    
    if (window.innerWidth <= 768) {
        const main = document.querySelector('.unique-main');
        if (main) {
            main.style.marginLeft = '0';
        }
    }
});

function searchShipments(query) {
    if (query.length > 2) {
        showNotification('Searching...', 'info');
        window.location.href = `?page=shipments&search=${encodeURIComponent(query)}`;
    } else if (query.length > 0) {
        showNotification('Please enter at least 3 characters to search', 'warning');
    }
}

// ========== ACTIVITY FUNCTIONS ==========
function refreshActivity() {
    showNotification('Refreshing activity...', 'info');
    setTimeout(() => location.reload(), 500);
}

// ========== LOGOUT FUNCTION ==========
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}

// ========== DATE FORMATTING ==========
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function formatDateTime(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// ========== EXPORT FUNCTIONS ==========
function exportToCSV(data, filename = 'shipments.csv') {
    if (!data || !data.length) {
        showNotification('No data to export', 'warning');
        return;
    }
    
    const headers = Object.keys(data[0]);
    const csvContent = [
        headers.join(','),
        ...data.map(row => headers.map(key => JSON.stringify(row[key] || '')).join(','))
    ].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showNotification('Export completed!', 'success');
}

// ========== INITIALIZATION ==========
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.stat-card-unique').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

// Add to assets/js/main.js - around line where other toggle functions are

// Toggle submenu with animation
function toggleSubmenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    if (submenu) {
        if (submenu.style.display === 'block') {
            submenu.style.display = 'none';
        } else {
            submenu.style.display = 'block';
        }
    }
}

// Close all submenus when sidebar is collapsed
function handleSidebarCollapse() {
    const sidebar = document.querySelector('.unique-sidebar');
    if (sidebar.classList.contains('collapsed')) {
        document.querySelectorAll('.submenu').forEach(submenu => {
            submenu.style.display = 'none';
        });
    }
}

// Modify the existing toggleSidebar function
const originalToggleSidebar = toggleSidebar;
toggleSidebar = function() {
    originalToggleSidebar();
    setTimeout(handleSidebarCollapse, 400); // Wait for animation
};

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .notification-toast {
        animation: slideInRight 0.3s ease;
    }
    
    @media (max-width: 768px) {
        .notification-toast {
            top: 10px;
            right: 10px;
            left: 10px;
            max-width: none;
            width: auto;
        }
    }
`;
document.head.appendChild(style);