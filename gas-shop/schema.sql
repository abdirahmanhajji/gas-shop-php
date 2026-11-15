CREATE DATABASE IF NOT EXISTS gas_shop CHARACTER SET utf8mb4;
USE gas_shop;
CREATE TABLE cylinders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  price INT NOT NULL DEFAULT 0,
  full_qty INT NOT NULL DEFAULT 0,
  empty_qty INT NOT NULL DEFAULT 0,
  low_level INT NOT NULL DEFAULT 5
);
CREATE TABLE sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cylinder_id INT NOT NULL,
  customer VARCHAR(100) NOT NULL,
  qty INT NOT NULL,
  price INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cylinder_id) REFERENCES cylinders(id)
);
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  amount INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
);
INSERT INTO cylinders (name,price,full_qty,empty_qty,low_level) VALUES
('Small cylinder (6 kg)',50000,20,0,5),
('Big cylinder (13 kg)',110000,12,0,4);
