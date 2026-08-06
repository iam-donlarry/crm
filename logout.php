<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
<script>
// Centralized localStorage management for CRM
const CRMStorage = {
    // Storage keys
    KEYS: {
        USERS: 'crm_users',
        LEADS: 'crm_leads',
        DEALS: 'crm_deals',
        TASKS: 'crm_tasks',
        PRODUCTS: 'crm_products',
        SETTINGS: 'crm_settings',
        CURRENT_USER: 'crm_current_user',
        COUNTERS: 'crm_counters'
    },

    // Initialize storage with default data
    init() {
        // Initialize counters if not exists
        if (!this.get(this.KEYS.COUNTERS)) {
            this.set(this.KEYS.COUNTERS, {
                users: 1,
                leads: 1,
                deals: 1,
                tasks: 1,
                products: 1
            });
        }

        // Initialize default admin user if no users exist
        if (!this.get(this.KEYS.USERS)) {
            const defaultAdmin = {
                id: 1,
                name: 'Admin User',
                email: 'admin@crm.com',
                password: 'admin123', // In production, this should be hashed
                role: 'admin',
                created_at: new Date().toISOString()
            };
            this.set(this.KEYS.USERS, [defaultAdmin]);
        }

        // Initialize empty arrays for other entities if they don't exist
        if (!this.get(this.KEYS.LEADS)) this.set(this.KEYS.LEADS, []);
        if (!this.get(this.KEYS.DEALS)) this.set(this.KEYS.DEALS, []);
        if (!this.get(this.KEYS.TASKS)) this.set(this.KEYS.TASKS, []);
        if (!this.get(this.KEYS.PRODUCTS)) this.set(this.KEYS.PRODUCTS, []);

        // Initialize default settings
        if (!this.get(this.KEYS.SETTINGS)) {
            this.set(this.KEYS.SETTINGS, {
                app_name: 'Advanced CRM',
                currency: 'USD',
                timezone: 'UTC',
                date_format: 'Y-m-d',
                items_per_page: 10
            });
        }
    },

    // Get data from localStorage
    get(key) {
        try {
            const data = localStorage.getItem(key);
            return data ? JSON.parse(data) : null;
        } catch (error) {
            console.error('Error reading from localStorage:', error);
            return null;
        }
    },

    // Set data in localStorage
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.error('Error writing to localStorage:', error);
            return false;
        }
    },

    // Remove data from localStorage
    remove(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (error) {
            console.error('Error removing from localStorage:', error);
            return false;
        }
    },

    // Clear all CRM data
    clearAll() {
        Object.values(this.KEYS).forEach(key => {
            this.remove(key);
        });
    },

    // Generate next ID for an entity
    getNextId(entityType) {
        const counters = this.get(this.KEYS.COUNTERS) || {};
        const currentId = counters[entityType] || 1;
        counters[entityType] = currentId + 1;
        this.set(this.KEYS.COUNTERS, counters);
        return currentId;
    },

    // Generic CRUD operations
    create(entityKey, data) {
        const items = this.get(entityKey) || [];
        const entityType = entityKey.replace('crm_', '');
        const newItem = {
            ...data,
            id: data.id || this.getNextId(entityType),
            created_at: data.created_at || new Date().toISOString(),
            updated_at: new Date().toISOString()
        };
        items.push(newItem);
        this.set(entityKey, items);
        return newItem;
    },

    read(entityKey, id = null) {
        const items = this.get(entityKey) || [];
        if (id === null) {
            return items;
        }
        return items.find(item => item.id == id) || null;
    },

    update(entityKey, id, data) {
        const items = this.get(entityKey) || [];
        const index = items.findIndex(item => item.id == id);
        if (index !== -1) {
            items[index] = {
                ...items[index],
                ...data,
                id: items[index].id, // Preserve original ID
                updated_at: new Date().toISOString()
            };
            this.set(entityKey, items);
            return items[index];
        }
        return null;
    },

    delete(entityKey, id) {
        const items = this.get(entityKey) || [];
        const filteredItems = items.filter(item => item.id != id);
        this.set(entityKey, filteredItems);
        return filteredItems.length < items.length;
    },

    // Search/filter operations
    search(entityKey, filterFn) {
        const items = this.get(entityKey) || [];
        return items.filter(filterFn);
    },

    // Count operations
    count(entityKey, filterFn = null) {
        const items = this.get(entityKey) || [];
        if (filterFn) {
            return items.filter(filterFn).length;
        }
        return items.length;
    }
};

// Initialize storage on load
document.addEventListener('DOMContentLoaded', () => {
    CRMStorage.init();
});

// Authentication module for frontend-only CRM
const CRMAuth = {
    // Check if user is logged in
    isLoggedIn() {
        const currentUser = CRMStorage.get(CRMStorage.KEYS.CURRENT_USER);
        return currentUser !== null;
    },

    // Get current user
    getCurrentUser() {
        return CRMStorage.get(CRMStorage.KEYS.CURRENT_USER);
    },

    // Get current user role
    getUserRole() {
        const user = this.getCurrentUser();
        return user ? user.role : null;
    },

    // Get current user ID
    getUserId() {
        const user = this.getCurrentUser();
        return user ? user.id : null;
    },

    // Get current user name
    getUserName() {
        const user = this.getCurrentUser();
        return user ? user.name : 'User';
    },

    // Login function
    login(email, password) {
        const users = CRMStorage.get(CRMStorage.KEYS.USERS) || [];
        const user = users.find(u => u.email === email && u.password === password);

        if (user) {
            // Store current user (without password)
            const userSession = {
                id: user.id,
                name: user.name,
                email: user.email,
                role: user.role,
                login_time: new Date().toISOString()
            };
            CRMStorage.set(CRMStorage.KEYS.CURRENT_USER, userSession);
            return { success: true, user: userSession };
        }

        return { success: false, message: 'Invalid email or password' };
    },

    // Logout function
    logout() {
        CRMStorage.remove(CRMStorage.KEYS.CURRENT_USER);
        return true;
    },

    // Check if user has specific role
    hasRole(role) {
        const userRole = this.getUserRole();
        return userRole === role;
    },

    // Check if user has one of multiple roles
    hasAnyRole(roles) {
        const userRole = this.getUserRole();
        return roles.includes(userRole);
    },

    // Require login - redirect to login page if not logged in
    requireLogin() {
        if (!this.isLoggedIn()) {
            // Get base URL from current location
            const baseUrl = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/');
            const loginUrl = baseUrl.includes('/views/')
                ? baseUrl.replace(/\/views\/.*$/, '/login.php')
                : window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/login.php');
            window.location.href = loginUrl;
            return false;
        }
        return true;
    },

    // Require specific role
    requireRole(roles) {
        if (!this.requireLogin()) {
            return false;
        }

        const rolesArray = Array.isArray(roles) ? roles : [roles];
        if (!this.hasAnyRole(rolesArray)) {
            alert('Access Denied: You do not have permission to access this page.');
            return false;
        }
        return true;
    },

    // Initialize auth check on page load
    init() {
        // This will be called on each page to check authentication
        const currentPath = window.location.pathname;

        // Skip auth check for login page
        if (currentPath.includes('login.php')) {
            // If already logged in, redirect to dashboard
            if (this.isLoggedIn()) {
                const baseUrl = window.location.origin + currentPath.replace(/\/[^\/]*$/, '');
                window.location.href = baseUrl + '/index.php';
            }
            return;
        }

        // For all other pages, require login
        this.requireLogin();
    }
};

// Auto-initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    // Storage must be initialized first
    if (typeof CRMStorage !== 'undefined') {
        CRMStorage.init();
    }
    // Then check authentication
    CRMAuth.init();
});
</script>
</head>
<body>
    <script>
        // Clear localStorage session
        CRMAuth.logout();
        // Redirect to login
        window.location.href = 'login.php';
    </script>
</body>
</html>
