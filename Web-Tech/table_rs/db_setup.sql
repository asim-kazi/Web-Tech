-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS table_reservation;

-- Use the database
USE table_reservation;

-- Create the reservations table
CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100),
  phone VARCHAR(20),
  date DATE,
  time TIME,
  guests INT,
  seating VARCHAR(50),
  special_requests TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Create a table for reservation time slots to manage capacity
CREATE TABLE IF NOT EXISTS time_slots (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    time TIME NOT NULL,
    max_capacity INT(3) NOT NULL DEFAULT 20,
    UNIQUE KEY unique_time (time)
);

-- Insert common reservation times
INSERT INTO time_slots (time, max_capacity) VALUES
('11:00:00', 20),
('11:30:00', 20),
('12:00:00', 25),
('12:30:00', 25),
('13:00:00', 25),
('13:30:00', 20),
('14:00:00', 15),
('18:00:00', 20),
('18:30:00', 20),
('19:00:00', 25),
('19:30:00', 25),
('20:00:00', 25),
('20:30:00', 20),
('21:00:00', 15);

-- Create a table for staff members who can manage reservations
CREATE TABLE IF NOT EXISTS staff (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Create a table for tables in the restaurant
CREATE TABLE IF NOT EXISTS tables (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(10) NOT NULL UNIQUE,
    section ENUM('indoor', 'outdoor', 'bar', 'private') NOT NULL,
    capacity INT(2) NOT NULL,
    status ENUM('available', 'reserved', 'occupied', 'maintenance') NOT NULL DEFAULT 'available'
);

-- Insert sample tables
INSERT INTO tables (table_number, section, capacity, status) VALUES
('A1', 'indoor', 2, 'available'),
('A2', 'indoor', 2, 'available'),
('A3', 'indoor', 4, 'available'),
('A4', 'indoor', 4, 'available'),
('B1', 'indoor', 6, 'available'),
('B2', 'indoor', 6, 'available'),
('C1', 'outdoor', 2, 'available'),
('C2', 'outdoor', 2, 'available'),
('C3', 'outdoor', 4, 'available'),
('C4', 'outdoor', 4, 'available'),
('D1', 'bar', 2, 'available'),
('D2', 'bar', 2, 'available'),
('D3', 'bar', 4, 'available'),
('E1', 'private', 8, 'available'),
('E2', 'private', 10, 'available');

-- Create a table for table assignments
CREATE TABLE IF NOT EXISTS table_assignments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT(11) NOT NULL,
    table_id INT(11) NOT NULL,
    assigned_by INT(11) NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- Create a log table for reservation changes
CREATE TABLE IF NOT EXISTS reservation_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT(11) NOT NULL,
    action ENUM('created', 'updated', 'cancelled', 'checked_in', 'completed') NOT NULL,
    action_by INT(11) NULL,
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- Create admin user (default password: admin123)
INSERT INTO staff (username, password_hash, name, email, role) VALUES
('admin', '12345', 'Admin User', 'admin@restaurant.com', 'admin');