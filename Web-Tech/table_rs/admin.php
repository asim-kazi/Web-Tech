<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

// Database connection
$config = [
  'db_host' => 'localhost',
  'db_user' => 'root',
  'db_password' => '',
  'db_name' => 'table_reservation'
];

try {
  $conn = new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_password'],
    $config['db_name']
  );

  if ($conn->connect_error) {
    throw new Exception("Connection failed: " . $conn->connect_error);
  }

  // Get today's reservations
  $today = date('Y-m-d');
  
  // Determine which date to show based on query parameter
  $view_date = isset($_GET['date']) ? $_GET['date'] : $today;
  
  // Validate date format
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $view_date)) {
    $view_date = $today;
  }
  
  $stmt = $conn->prepare("
    SELECT r.*, 
      COALESCE(GROUP_CONCAT(t.table_number SEPARATOR ', '), 'Unassigned') as assigned_tables
    FROM reservations r
    LEFT JOIN table_assignments ta ON r.id = ta.reservation_id
    LEFT JOIN tables t ON ta.table_id = t.id
    WHERE r.date = ?
    GROUP BY r.id
    ORDER BY r.time ASC
  ");
  
  $stmt->bind_param("s", $view_date);
  $stmt->execute();
  $result = $stmt->get_result();
  $reservations = $result->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  
  // Get available tables
  $tables_query = "SELECT * FROM tables ORDER BY section, table_number";
  $tables_result = $conn->query($tables_query);
  $tables = $tables_result->fetch_all(MYSQLI_ASSOC);
  
} catch (Exception $e) {
  $error_message = $e->getMessage();
}

// Handle table assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_table'])) {
  $reservation_id = $_POST['reservation_id'];
  $table_ids = isset($_POST['table_ids']) ? $_POST['table_ids'] : [];
  
  try {
    // Start transaction
    $conn->begin_transaction();
    
    // Remove old assignments
    $stmt = $conn->prepare("DELETE FROM table_assignments WHERE reservation_id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $stmt->close();
    
    // Add new assignments
    if (!empty($table_ids)) {
      $stmt = $conn->prepare("INSERT INTO table_assignments (reservation_id, table_id, assigned_by) VALUES (?, ?, ?)");
      foreach ($table_ids as $table_id) {
        $stmt->bind_param("iii", $reservation_id, $table_id, $_SESSION['user_id']);
        $stmt->execute();
      }
      $stmt->close();
    }
    
    // Log the change
    $action = empty($table_ids) ? 'Table assignment removed' : 'Table assigned';
    $stmt = $conn->prepare("INSERT INTO reservation_logs (reservation_id, action, action_by, details) VALUES (?, 'updated', ?, ?)");
    $stmt->bind_param("iis", $reservation_id, $_SESSION['user_id'], $action);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $success_message = "Table assignment updated successfully!";
    
    // Refresh reservation data
    header("Location: admin.php?date=" . $view_date . "&success=assignment");
    exit();
    
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $error_message = $e->getMessage();
  }
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
  $reservation_id = $_POST['reservation_id'];
  $action = $_POST['action'];
  
  try {
    // Start transaction
    $conn->begin_transaction();
    
    // Log the action
    $stmt = $conn->prepare("INSERT INTO reservation_logs (reservation_id, action, action_by, details) VALUES (?, ?, ?, ?)");
    $details = "Status changed to: " . $action;
    $stmt->bind_param("iiss", $reservation_id, $action, $_SESSION['user_id'], $details);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $success_message = "Reservation status updated successfully!";
    
    // Refresh page
    header("Location: admin.php?date=" . $view_date . "&success=status");
    exit();
    
  } catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $error_message = $e->getMessage();
  }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reservation Management</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    :root {
      --primary-color: #8b4513;
      --primary-light: #a0522d;
      --secondary-color: #f5f5dc;
      --text-color: #333;
      --error-color: #d32f2f;
      --success-color: #2e7d32;
      --warning-color: #f57c00;
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
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }
    
    header {
      background-color: var(--primary-color);
      color: white;
      padding: 15px 0;
      margin-bottom: 30px;
    }
    
    header .container {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .nav-links a {
      color: white;
      text-decoration: none;
      margin-left: 20px;
      font-weight: 500;
    }
    
    .nav-links a:hover {
      text-decoration: underline;
    }
    
    h1, h2, h3 {
      color: var(--primary-color);
    }
    
    .alert {
      padding: 10px 15px;
      margin-bottom: 20px;
      border-radius: 4px;
    }
    
    .alert-success {
      background-color: #e8f5e9;
      color: var(--success-color);
      border: 1px solid #c8e6c9;
    }
    
    .alert-error {
      background-color: #ffebee;
      color: var(--error-color);
      border: 1px solid #ffcdd2;
    }
    
    .date-nav {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
      gap: 15px;
    }
    
    .date-nav input {
      padding: 8px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
    }
    
    .date-nav button {
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 8px 15px;
      border-radius: 4px;
      cursor: pointer;
    }
    
    .date-quick-links {
      margin-left: auto;
    }
    
    .date-quick-links a {
      margin-left: 10px;
      text-decoration: none;
      color: var(--primary-color);
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      margin-bottom: 30px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    th, td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--border-color);
    }
    
    th {
      background-color: var(--primary-color);
      color: white;
      font-weight: 500;
    }
    
    tr:hover {
      background-color: #f9f9f9;
    }
    
    .badge {
      display: inline-block;
      padding: 4px 8px;
      font-size: 12px;
      font-weight: bold;
      text-align: center;
      border-radius: 4px;
      margin-right: 5px;
    }
    
    .badge-indoor {
      background-color: #e3f2fd;
      color: #1565c0;
    }
    
    .badge-outdoor {
      background-color: #e8f5e9;
      color: #2e7d32;
    }
    
    .badge-private {
      background-color: #fce4ec;
      color: #c2185b;
    }
    
    .badge-bar {
      background-color: #fff3e0;
      color: #e65100;
    }
    
    .action-btn {
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 6px 10px;
      border-radius: 4px;
      cursor: pointer;
      margin-right: 5px;
      font-size: 14px;
    }
    
    .action-btn:hover {
      background-color: var(--primary-light);
    }
    
    .action-btn.btn-danger {
      background-color: var(--error-color);
    }
    
    .action-btn.btn-success {
      background-color: var(--success-color);
    }
    
    .action-btn.btn-warning {
      background-color: var(--warning-color);
    }
    
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
    }
    
    .modal-content {
      background-color: white;
      margin: 10% auto;
      padding: 20px;
      border-radius: 5px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
      width: 60%;
      max-width: 500px;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-color);
    }
    
    .close {
      color: #aaa;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    
    .table-options {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 10px;
      margin-top: 15px;
    }
    
    .table-checkbox {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
    }
    
    .table-checkbox input {
      margin-right: 8px;
    }
    
    .modal-footer {
      margin-top: 20px;
      text-align: right;
    }
    
    .modal-footer button {
      padding: 8px 15px;
      margin-left: 10px;
      border-radius: 4px;
      border: none;
      cursor: pointer;
    }
    
    .modal-footer .btn-cancel {
      background-color: #f5f5f5;
      color: #333;
    }
    
    .modal-footer .btn-save {
      background-color: var(--primary-color);
      color: white;
    }
    
    footer {
      text-align: center;
      padding: 20px 0;
      margin-top: 40px;
      color: #666;
      border-top: 1px solid var(--border-color);
    }
  </style>
</head>
<body>
  <header>
    <div class="container">
      <h1>Restaurant Admin</h1>
      <div class="nav-links">
        <a href="admin.php">Reservations</a>
        <a href="#">Tables</a>
        <a href="#">Settings</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>
  </header>
  
  <div class="container">
    <?php if (isset($success_message)): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
      </div>
    <?php endif; ?>
    
    <h2>Reservations</h2>
    
    <div class="date-nav">
      <form action="" method="GET">
        <input type="date" name="date" value="<?php echo $view_date; ?>" onchange="this.form.submit()">
      </form>
      
      <div class="date-quick-links">
        <a href="?date=<?php echo date('Y-m-d'); ?>">Today</a>
        <a href="?date=<?php echo date('Y-m-d', strtotime('+1 day')); ?>">Tomorrow</a>
        <a href="?date=<?php echo date('Y-m-d', strtotime('next saturday')); ?>">Next Saturday</a>
        <a href="?date=<?php echo date('Y-m-d', strtotime('next sunday')); ?>">Next Sunday</a>
      </div>
    </div>
    
    <?php if (empty($reservations)): ?>
      <p>No reservations found for <?php echo date('l, F j, Y', strtotime($view_date)); ?>.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Name</th>
            <th>Guests</th>
            <th>Contact</th>
            <th>Preference</th>
            <th>Tables</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $reservation): ?>
            <tr>
              <td><?php echo date('g:i A', strtotime($reservation['time'])); ?></td>
              <td><?php echo htmlspecialchars($reservation['name']); ?></td>
              <td><?php echo $reservation['guests']; ?></td>
              <td>
                <?php echo htmlspecialchars($reservation['email']); ?><br>
                <?php echo htmlspecialchars($reservation['phone']); ?>
              </td>
              <td>
                <?php 
                  $badge_class = '';
                  switch ($reservation['seating']) {
                    case 'indoor': $badge_class = 'badge-indoor'; break;
                    case 'outdoor': $badge_class = 'badge-outdoor'; break;
                    case 'private': $badge_class = 'badge-private'; break;
                    case 'bar': $badge_class = 'badge-bar'; break;
                  }
                ?>
                <span class="badge <?php echo $badge_class; ?>">
                  <?php echo ucfirst(htmlspecialchars($reservation['seating'])); ?>
                </span>
                <?php if (!empty($reservation['special_requests'])): ?>
                  <i class="fas fa-info-circle" title="<?php echo htmlspecialchars($reservation['special_requests']); ?>"></i>
                <?php endif; ?>            
              </td>
              <td><?php echo htmlspecialchars($reservation['assigned_tables']); ?></td>
              <td>
                <button class="action-btn" onclick="openTableModal(<?php echo $reservation['id']; ?>, '<?php echo htmlspecialchars($reservation['name']); ?>')">
                  <i class="fas fa-chair"></i> Tables
                </button>
                
                <form style="display: inline;" method="POST" onsubmit="return confirm('Are you sure you want to mark this reservation as checked in?');">
                  <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                  <input type="hidden" name="action" value="checked_in">
                  <button type="submit" name="change_status" class="action-btn btn-success">
                    <i class="fas fa-check"></i> Check-in
                  </button>
                </form>
                
                <form style="display: inline;" method="POST" onsubmit="return confirm('Are you sure you want to mark this reservation as cancelled?');">
                  <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                  <input type="hidden" name="action" value="cancelled">
                  <button type="submit" name="change_status" class="action-btn btn-danger">
                    <i class="fas fa-times"></i> Cancel
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  
  <!-- Table Assignment Modal -->
  <div id="tableModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Assign Tables - <span id="reservationName"></span></h3>
        <span class="close">&times;</span>
      </div>
      <form id="assignTableForm" method="POST">
        <input type="hidden" id="reservationId" name="reservation_id">
        
        <p>Select tables to assign to this reservation:</p>
        
        <div class="table-options">
          <?php foreach ($tables as $table): ?>
            <div class="table-checkbox">
              <input type="checkbox" id="table<?php echo $table['id']; ?>" name="table_ids[]" value="<?php echo $table['id']; ?>">
              <label for="table<?php echo $table['id']; ?>">
                <?php echo htmlspecialchars($table['table_number']); ?>
                <span class="badge badge-<?php echo $table['section']; ?>">
                  <?php echo ucfirst($table['section']); ?>
                </span>
                (<?php echo $table['capacity']; ?>)
              </label>
            </div>
          <?php endforeach; ?>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn-cancel close-modal">Cancel</button>
          <button type="submit" name="assign_table" class="btn-save">Save Assignment</button>
        </div>
      </form>
    </div>
  </div>
  
  <footer>
    <div class="container">
      <p>&copy; 2025 Restaurant Management System</p>
    </div>
  </footer>
  
  <script>
    // Modal functionality
    const modal = document.getElementById('tableModal');
    const closeButtons = document.getElementsByClassName('close');
    const cancelButtons = document.getElementsByClassName('close-modal');
    
    function openTableModal(reservationId, name) {
      document.getElementById('reservationId').value = reservationId;
      document.getElementById('reservationName').textContent = name;
      modal.style.display = 'block';
      
      // Reset checkboxes
      const checkboxes = document.querySelectorAll('#assignTableForm input[type="checkbox"]');
      checkboxes.forEach(checkbox => {
        checkbox.checked = false;
      });
      
      // TODO: Load current table assignments for this reservation
    }
    
    // Close modal when clicking the X
    for (let i = 0; i < closeButtons.length; i++) {
      closeButtons[i].addEventListener('click', function() {
        modal.style.display = 'none';
      });
    }
    
    // Close modal when clicking Cancel button
    for (let i = 0; i < cancelButtons.length; i++) {
      cancelButtons[i].addEventListener('click', function() {
        modal.style.display = 'none';
      });
    }
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    });
    
    // Success message auto-hide
    const alertSuccess = document.querySelector('.alert-success');
    if (alertSuccess) {
      setTimeout(function() {
        alertSuccess.style.display = 'none';
      }, 5000);
    }
  </script>
</body>
</html>