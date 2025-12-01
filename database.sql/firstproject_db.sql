CREATE DATABASE IF NOT EXISTS firstproject_db;
USE firstproject_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  reset_otp VARCHAR(6) NULL,
  otp_expire INT(11) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('income','expense') NOT NULL
);

CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_id INT,
  description VARCHAR(255),
  amount DECIMAL(10,2),
  type ENUM('income','expense'),
  date DATE,
  notes VARCHAR(255) DEFAULT '',
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE todos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, 
  task VARCHAR(255),
  due_date DATE NULL,
  category VARCHAR(255),
  is_done TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO categories (name, type)
VALUES
    ('Food and Dining', 'expense'),
    ('Transportation', 'expense'),
    ('Shopping', 'expense'),
    ('Entertainment', 'expense'),
    ('Utilities', 'expense'),
    ('Healthcare', 'expense'),
    ('Salary', 'income'),
    ('Business', 'income'),
    ('Gift', 'income');