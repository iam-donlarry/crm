<?php
require_once 'includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="icon" type="image/webp" href="<?php echo BASE_URL; ?>/assets/images/bbsllogo.webp">
    
    <!-- Google Font: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 & Lucide Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        :root {
            --primary-color: #12b1b0;
            --primary-hover: #0e8c8b;
            --primary-light: #e0f2f1;
            --border-color: #e5e7eb;
            --hover-bg: #f9fafb;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --body-bg: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            --card-bg: #ffffff;
            --input-bg: #ffffff;
            --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            min-height: 100vh;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 1.25rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            max-width: 960px;
            width: 100%;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
        }

        .login-main {
            padding: 3rem 2.75rem;
        }

        .login-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2.5rem;
        }

        .login-logo-text {
            font-weight: 800;
            letter-spacing: -0.04em;
            font-size: 2rem;
            color: var(--primary-color);
            line-height: 1;
        }

        .login-title {
            font-weight: 700;
            font-size: 1.875rem;
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .error-alert {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .login-form-group {
            margin-bottom: 1.25rem;
        }

        .login-label-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.35rem;
            align-items: center;
        }

        .login-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .login-input {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .login-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(18, 177, 176, 0.12);
        }

        .login-checkbox-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .login-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .login-button {
            width: 100%;
            border-radius: 0.75rem;
            border: none;
            padding: 0.875rem 1.25rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            background: var(--primary-color);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 20px rgba(18, 177, 176, 0.3);
            background: var(--primary-hover);
        }

        .login-side {
            padding: 3rem 2.75rem;
            border-left: 1px solid var(--border-color);
            background: 
                radial-gradient(circle at top left, var(--primary-light), transparent 70%), 
                radial-gradient(circle at bottom right, #e2e8f0, transparent 70%),
                #fafaf7;
            position: relative;
        }

        .login-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: rgba(15,23,42,0.06);
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .login-side-title {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 0.75rem;
        }

        .login-side-text {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            margin-bottom: 1.75rem;
            font-weight: 400;
        }

        .login-side-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .login-side-metric {
            padding: 0.85rem 0.9rem;
            border-radius: 0.9rem;
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(148,163,184,0.3);
            backdrop-filter: blur(8px);
        }

        .login-side-metric-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.1rem;
            font-weight: 600;
        }

        .login-side-metric-value {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .login-side-badge {
            position: absolute;
            bottom: 2.25rem;
            right: 2.5rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: rgba(15,23,42,0.04);
            font-weight: 500;
        }

        @media (max-width: 900px) {
            .login-card {
                grid-template-columns: 1fr;
            }
            .login-side {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-main">
                <div class="login-logo">
                    <img src="<?php echo BASE_URL; ?>/assets/images/bbsllogo.webp" alt="BBSL Logo" style="height: 48px; width: auto; object-fit: contain;">
                    <span class="login-logo-text">CRM</span>
                </div>
                <h1 class="login-title">Sign in to your account</h1>
                <p class="login-subtitle">Use your credentials to access the staff portal dashboard.</p>

                <div id="errorAlert" class="error-alert d-none">
                    <i data-lucide="alert-circle" style="width: 16px; height: 16px; margin-right: 6px; vertical-align: middle;"></i>
                    <span id="errorMsg">Invalid email or password</span>
                </div>

                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="login-form-group">
                        <div class="login-label-row">
                            <label class="login-label" for="email">Username or Email Address</label>
                        </div>
                        <input type="text" class="login-input" id="email" name="email" placeholder="admin@example.com" required autofocus>
                    </div>
                    <div class="login-form-group">
                        <div class="login-label-row">
                            <label class="login-label" for="password">Password</label>
                        </div>
                        <input id="password" type="password" class="login-input" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="login-checkbox-row">
                        <label class="login-checkbox">
                            <input type="checkbox" name="remember" style="accent-color: var(--primary-color);">
                            <span>Remember me</span>
                        </label>
                    </div>

                    <button type="submit" id="submitBtn" class="login-button">
                        <i data-lucide="log-in" style="width: 18px; height: 18px; stroke-width: 2;"></i>
                        Sign in
                    </button>
                </form>
            </div>
            <div class="login-side">
                <div class="login-tag">
                    <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i>
                    Enterprise-ready security
                </div>
                <h2 class="login-side-title">BBSL CRM System</h2>
                <p class="login-side-text">
                    Streamline customer relationships, lead tracking, deals pipeline, and sales team performance with real-time analytics.
                </p>
                <div class="login-side-metrics">
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Leads</div>
                        <div class="login-side-metric-value">10k+</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Deals Won</div>
                        <div class="login-side-metric-value">5M+</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Uptime</div>
                        <div class="login-side-metric-value">99.9%</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Satisfaction</div>
                        <div class="login-side-metric-value">4.9/5</div>
                    </div>
                </div>
                <div class="login-side-badge">
                    <span>Sales Operations & Analytics</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) {
                lucide.createIcons();
            }
        });

        function handleLogin(e) {
            e.preventDefault();
            const alertBox = document.getElementById('errorAlert');
            const alertMsg = document.getElementById('errorMsg');
            const submitBtn = document.getElementById('submitBtn');
            alertBox.classList.add('d-none');
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Signing in...';

            fetch('<?php echo BASE_URL; ?>/login_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, password: password })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '<?php echo BASE_URL; ?>/dashboard';
                } else {
                    alertMsg.textContent = data.message || 'Invalid email or password';
                    alertBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i data-lucide="log-in" style="width: 18px; height: 18px; stroke-width: 2;"></i> Sign in';
                    if (window.lucide) lucide.createIcons();
                }
            })
            .catch(err => {
                alertMsg.textContent = 'Server connection error. Please try again.';
                alertBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="log-in" style="width: 18px; height: 18px; stroke-width: 2;"></i> Sign in';
                if (window.lucide) lucide.createIcons();
            });
        }
    </script>
</body>
</html>
