# Application Notification & Dialog Rules

Always follow the application's built-in alert, toast, and modal conventions. Do NOT use native browser `alert()`, `confirm()`, or `prompt()` calls.

---

### 1. Client-Side Toast Notifications (`window.showToast`)
Use `window.showToast(message, type)` for transient user feedback (form updates, copy-to-clipboard, quick alerts).
- **Supported Types**: `'success'`, `'error'`, `'warning'`, `'info'`
- **Examples**:
  ```javascript
  window.showToast('Password reset successfully!', 'success');
  window.showToast('Failed to connect to server.', 'error');
  window.showToast('Please fill in all required fields.', 'warning');
  ```

---

### 2. Action Confirmation Modals (`window.appConfirm`)
Always wrap destructive actions (deleting, deactivating, rejecting, cancelling) or critical confirmations in `async/await` using `window.appConfirm(options)`.

#### Options Schema:
- `title` (string): Header title of the modal.
- `message` (string): Explanatory text detailing the action.
- `confirmText` (string): Text on the primary action button.
- `cancelText` (string): Text on the cancel button (defaults to 'Cancel').
- `isDanger` (boolean): `true` for red danger buttons, `false` for primary teal buttons.

#### Example Usage:
```javascript
async function handleDelete(id, name) {
    const confirmed = await window.appConfirm({
        title: 'Delete Item',
        message: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
        confirmText: 'Yes, Delete Item',
        cancelText: 'Cancel',
        isDanger: true
    });
    if (confirmed) {
        document.getElementById('deleteForm_' + id).submit();
    }
}
```

---

### 3. Server-Side Flash Banners (`setFlash` / `getFlash`)
When handling form POST actions in PHP followed by a `redirect()`, store success or error messages using `setFlash()`:

#### PHP Usage:
```php
// In POST handler
setFlash('success', 'User account updated successfully.');
redirect(BASE_URL . '/users');

// In HTML View
$success_msg = getFlash('success');
$error_msg   = getFlash('error');
```

#### HTML Banner Syntax:
```html
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success mb-3" style="padding: 1rem; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; background: #e0f2f1; color: #00796b;">
        <i data-lucide="check-circle"></i> <span><?= htmlspecialchars($success_msg) ?></span>
    </div>
<?php endif; ?>
```
