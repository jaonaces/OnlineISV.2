<?php
/**
 * Login Page
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $db = Database::getInstance();
        
        $user = $db->fetchOne(
            "SELECT user_id, username, password, full_name, email, phone, role, branch_id, status 
             FROM users 
             WHERE username = ?",
            [$username]
        );
        
        if ($user && verifyPassword($password, $user['password'])) {
            if ($user['status'] !== 'Active') {
                $error = 'Your account is inactive. Please contact administrator.';
            } else {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                
                // Update last login
                $db->update(
                    'users',
                    ['last_login' => getCurrentDateTime()],
                    'user_id = ?',
                    [$user['user_id']]
                );
                
                setFlashMessage('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0f172a;
            --accent-color: #3b82f6;
            --bg-color: #f8fafc;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .background-collage {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(2, 1fr);
        }

        .background-collage .bg-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.6);
            transition: transform 0.5s ease;
        }

        .background-collage .bg-image:hover {
            transform: scale(1.05);
            filter: brightness(0.7);
        }

        .background-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(30, 41, 59, 0.75) 100%);
            z-index: -1;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            max-width: 800px;
            width: 90%;
            margin: 20px;
        }

        .login-image {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.9) 0%, rgba(139, 92, 246, 0.9) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-image::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 8s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
        }

        .login-image h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .company-name-cursive {
            font-family: 'Alex Brush', cursive;
            font-size: 3rem;
            font-weight: 400;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .login-image p {
            font-size: 1.1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .login-form {
            padding: 60px 50px;
            background: rgba(255, 255, 255, 0.98);
        }

        .login-form h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .login-form .subtitle {
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating input {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-floating input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            background: white;
        }

        .form-floating label {
            padding: 1rem 1rem 1rem 3rem;
            color: #64748b;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            z-index: 10;
        }

        .btn-login {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            border: none;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            backdrop-filter: blur(10px);
        }

        @media (max-width: 768px) {
            .login-image {
                display: none;
            }

            .login-form {
                padding: 40px 30px;
            }

            .background-collage {
                grid-template-columns: repeat(2, 1fr);
                grid-template-rows: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="background-collage">
        <img src="images/45421355fb91acd655242d112fb6578b.jpg" alt="Background 1" class="bg-image">
        <img src="images/79c3793b71a964337b7e527fd7e27292.jpg" alt="Background 2" class="bg-image">
        <img src="images/7e710e0ce9a96752f7359b1c80541b5a.jpg" alt="Background 3" class="bg-image">
        <img src="images/9d04672c7abeec4ce025f591bb8fd15d.jpg" alt="Background 4" class="bg-image">
        <img src="images/e293cd60ed704788f5bad4b29e767602.jpg" alt="Background 5" class="bg-image">
        <img src="images/edcedfa71956fc5600bf87cb4d681f27.jpg" alt="Background 6" class="bg-image">
    </div>
    <div class="background-overlay"></div>
    <div class="login-container">
        <div class="row g-0">
            <div class="col-lg-6">
                <div class="login-image">
                    <div class="text-center">
                        <i class="bi bi-calendar-check" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                        <h2><span class="company-name-cursive"><?php echo getCompanyName(); ?></span></h2>
                        <p>Multi-Branch Inventory & Booking Management System</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="login-form">
                    <h3>Welcome Back</h3>
                    <p class="subtitle">Sign in to your account</p>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php $flash = getFlashMessage(); ?>
                    <?php if ($flash): ?>
                        <div class="alert alert-<?php echo $flash['type']; ?>" role="alert">
                            <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo $flash['message']; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-floating">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                            <label for="username">Username</label>
                        </div>
                        
                        <div class="form-floating">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <label for="password">Password</label>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Sign In
                        </button>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
