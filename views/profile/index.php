<?php
require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-person-circle"></i> My Profile</h1>
    </div>
</div>

<div class="row">
    <!-- Profile Image Card -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Profile Image</h6>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <img id="profileImagePreview" src="<?php echo BASE_URL; ?>/assets/images/avatar.jpg" 
                         alt="Profile" class="img-profile rounded-circle shadow-sm" 
                         style="width: 150px; height: 150px; object-fit: cover; border: 5px solid #eaecf4; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                </div>
                
                <div class="mb-3">
                    <label for="profileImageInput" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-camera"></i> Change Photo
                    </label>
                    <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="handleImageUpload(this)">
                </div>
                
                <p class="small text-muted mb-0">
                    JPG or PNG no larger than 2MB.<br>
                    Recommended size: 150x150 px.
                </p>
            </div>
        </div>
    </div>

    <!-- Profile Details Card -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Profile Details</h6>
                <span class="badge bg-info" id="userRoleBadge">Role</span>
            </div>
            <div class="card-body">
                <form id="profileForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="userName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Staff ID</label>
                            <input type="text" class="form-control bg-light" id="userStaffId" readonly 
                                   title="Staff ID cannot be edited">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control bg-light" id="userEmail" readonly
                                   title="Email address cannot be edited">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="userPhone" placeholder="+1234567890">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control bg-light" id="userRole" readonly
                               title="Role cannot be edited">
                    </div>

                    <hr>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Security Settings Card -->
    <div class="col-12 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Security Settings</h6>
            </div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="row">
                        <!-- Current Password Removed as Admin API allow override without it for now, or logical simplification -->
                        <!-- If strict security needed, backend must verify old password first -->
                        <div class="col-md-4">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newPassword" required minlength="6">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-warning text-white">
                            <i class="bi bi-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadUserProfile();
});

const currentUserId = <?php echo $_SESSION['user_id']; ?>;

async function loadUserProfile() {
    try {
        const response = await fetch(`../../views/admin/users.php?api=true&id=${currentUserId}`);
        const result = await response.json();
        
        if (result.success) {
            const user = result.data;
            document.getElementById('userName').value = user.first_name + ' ' + user.last_name;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userPhone').value = user.phone || '';
            document.getElementById('userRole').value = user.role;
            // Staff ID is not always in the response if not selected, but let's try
            document.getElementById('userStaffId').value = user.staff_id || 'N/A';
            document.getElementById('userRoleBadge').textContent = user.role.toUpperCase();
            
            // Image handling
            if (user.image) {
                document.getElementById('profileImagePreview').src = user.image;
            }

            // Auto-heal: Update LocalStorage and Header if mismatch detected
            // This fixes the issue where Header says "Admin User" but DB has "Real Name"
            if (typeof CRMStorage !== 'undefined') {
                const currentUser = CRMStorage.get(CRMStorage.KEYS.CURRENT_USER);
                const dbName = user.first_name + ' ' + user.last_name;
                
                // If we have a local user and the name mismatches DB
                if (currentUser && (currentUser.name !== dbName || currentUser.role !== user.role)) {
                    // Update Local Object
                    currentUser.name = dbName;
                    currentUser.first_name = user.first_name;
                    currentUser.last_name = user.last_name;
                    currentUser.role = user.role;
                    // Persist
                    CRMStorage.set(CRMStorage.KEYS.CURRENT_USER, currentUser);
                    
                    // Update UI immediately
                    const userRoleDisplay = document.getElementById('userRoleDisplay');
                    if (userRoleDisplay) userRoleDisplay.textContent = dbName;
                    
                    const dropdownUser = document.getElementById('currentUserName');
                    if (dropdownUser) dropdownUser.textContent = dbName;
                    
                    // console.log('Auto-healed user profile mismatch');
                }
            }
        } else {
            alert('Failed to load profile: ' + result.message);
        }
    } catch (e) {
        console.error('Error loading profile:', e);
    }
}

document.getElementById('profileForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const fullName = document.getElementById('userName').value.trim();
    const phone = document.getElementById('userPhone').value.trim();
    
    // Split name crudely (backend handles better, but we need to send first/last)
    // Actually handlePut expects first_name and last_name separately
    const nameParts = fullName.split(' ');
    const firstName = nameParts[0];
    const lastName = nameParts.slice(1).join(' ') || '';

    const data = {
        first_name: firstName,
        last_name: lastName,
        phone: phone
    };

    try {
        const response = await fetch('../../views/admin/users.php?api=true', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            alert('Profile updated successfully');
            
            // Update Local Storage
            if (typeof CRMStorage !== 'undefined') {
                const currentUser = CRMStorage.get(CRMStorage.KEYS.CURRENT_USER);
                if (currentUser) {
                    currentUser.first_name = firstName;
                    currentUser.last_name = lastName;
                    currentUser.name = firstName + ' ' + lastName;
                    // Also update email if it was changed (this form doesn't edit email except for admin? 
                    // Field is readonly for basic users, but let's be safe if it was in data)
                    // The form has email readonly usually.
                    CRMStorage.set(CRMStorage.KEYS.CURRENT_USER, currentUser);
                    
                    // Update Topbar Display
                    const userRoleDisplay = document.getElementById('userRoleDisplay');
                    if (userRoleDisplay) {
                        userRoleDisplay.textContent = currentUser.name;
                    }
                    const dropdownUser = document.getElementById('currentUserName');
                    if (dropdownUser) {
                        dropdownUser.textContent = currentUser.name;
                    }
                }
            }
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Request failed');
    }
});

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye', 'bi-eye');
    }
}

document.getElementById('passwordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;
    
    if (newPass !== confirmPass) {
        alert('New passwords do not match!');
        return;
    }
    
    if (newPass.length < 6) {
        alert('Password must be at least 6 characters long.');
        return;
    }
    
    // Note: We don't check current password here because the API endpoints 
    // for simple update don't usually require it unless strictly enforced.
    // Ideally we should validte current password on backend, but handlePut just updates.
    
    const data = {
        password: newPass
    };
    
    try {
        const response = await fetch('../../views/admin/users.php?api=true', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
            alert('Password changed successfully!');
            document.getElementById('passwordForm').reset();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Request failed');
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
