<?php
session_start();

// Database connection config
$config = [
  'db_host' => 'localhost',
  'db_user' => 'root',
  'db_password' => '',
  'db_name' => 'table_reservation'
];

$error_message = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Get username and password from form
  $username = isset($_POST['username']) ? trim($_POST['username']) : '';
  $password = isset($_POST['password']) ? trim($_POST['password']) : '';
  
  // Validate inputs
  if (empty($username) || empty($password)) {
    $error_message = 'Please enter both username and password';
  } else {
    try {
      // Connect to database
      $conn = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_password'],
        $config['db_name']
      );
      
      if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
      }
      
      // Prepare statement to prevent SQL injection
      $stmt = $conn->prepare("SELECT id, username, password_hash, name, role FROM staff WHERE username = ?");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        // In a real application, you would use password_verify() with proper hashing
        // For demo purposes, we're just comparing the values directly
        if ($password === $user['password_hash']) {
          // Password is correct, create session
          $_SESSION['user_id'] = $user['id'];
          $_SESSION['username'] = $user['username'];
          $_SESSION['name'] = $user['name'];
          $_SESSION['role'] = $user['role'];
          
          // Update last login time
          $update_stmt = $conn->prepare("UPDATE staff SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
          $update_stmt->bind_param("i", $user['id']);
          $update_stmt->execute();
          $update_stmt->close();
          
          // Redirect to admin page
          header('Location: admin.php');
          exit();
        } else {
          $error_message = 'Invalid username or password';
        }
      } else {
        $error_message = 'Invalid username or password';
      }
      
      $stmt->close();
      $conn->close();
      
    } catch (Exception $e) {
      $error_message = 'An error occurred: ' . $e->getMessage();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - Restaurant Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    :root {
      --primary-color: #8b4513;
      --primary-light: #a0522d;
      --secondary-color: #f5f5dc;
      --text-color: #333;
      --error-color: #d32f2f;
      --success-color: #2e7d32;
      --border-color: #ddd;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: var(--text-color);
      background-color: #f5f5f5;
      padding: 0;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    
    .login-container {
      width: 100%;
      max-width: 400px;
      padding: 30px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .login-header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .login-header h1 {
      color: var(--primary-color);
      margin-bottom: 10px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
    }
    
    input {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 16px;
      transition: border-color 0.3s;
    }
    
    input:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 2px rgba(139, 69, 19, 0.2);
    }
    
    .btn-login {
      width: 100%;
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 14px;
      border-radius: 4px;
      font-size: 16px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    
    .btn-login:hover {
      background-color: var(--primary-light);
    }
    
    .error-message {
      background-color: #ffebee;
      color: var(--error-color);
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 20px;
      border: 1px solid #ffcdd2;
    }
    
    .back-link {
      display: block;
      text-align: center;
      margin-top: 20px;
      color: var(--primary-color);
      text-decoration: none;
    }
    
    .back-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-header">
      <h1><i class="fas fa-utensils"></i> Restaurant Admin</h1>
      <p>Login to manage reservations</p>
    </div>
    
    <?php if (!empty($error_message)): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
      </div>
    <?php endif; ?>
    
    <form method="POST" action="">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
      </div>
      
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      
      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt"></i> Login
      </button>
    </form>
    
    <a href="index.html" class="back-link">
      <i class="fas fa-arrow-left"></i> Back to Reservation Page
    </a>
  </div>
</body>
</html>