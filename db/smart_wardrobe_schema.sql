DROP DATABASE IF EXISTS smart_wardrobe;

CREATE DATABASE smart_wardrobe
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE smart_wardrobe;

-- 1. USERS TABLE
-- - Registration
-- - Authentication
-- - Admin/User roles


CREATE TABLE users (

    user_id INT AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(50) NOT NULL UNIQUE,

    email VARCHAR(100) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    role ENUM('user','admin') DEFAULT 'user',

    account_status ENUM('active','disabled') DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB;




-- 2. CATEGORIES TABLE
-- 3NF


CREATE TABLE categories (

    category_id INT AUTO_INCREMENT PRIMARY KEY,

    category_name VARCHAR(50) NOT NULL UNIQUE

) ENGINE=InnoDB;

-- 3. CLOTHING ITEMS TABLE
-- Stores uploaded wardrobe items


CREATE TABLE clothing_items (

    item_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    category_id INT NOT NULL,

    item_name VARCHAR(100) NOT NULL,

    image_path VARCHAR(255) NOT NULL,

    color VARCHAR(50),

    season ENUM(
        'summer',
        'winter',
        'spring',
        'autumn',
        'all-season'
    ) DEFAULT 'all-season',

    status ENUM('clean','dirty') DEFAULT 'clean',

    last_worn DATE NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
        ON UPDATE CASCADE

) ENGINE=InnoDB;

-- 4. OUTFITS TABLE
-- Stores outfit collections

CREATE TABLE outfits (

    outfit_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    outfit_name VARCHAR(100) NOT NULL,

    occasion VARCHAR(100),

    description TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE

) ENGINE=InnoDB;

-- 5. OUTFIT ITEMS TABLE
-- Many-to-many junction table

CREATE TABLE outfit_items (

    outfit_id INT NOT NULL,

    item_id INT NOT NULL,

    PRIMARY KEY (outfit_id, item_id),

    FOREIGN KEY (outfit_id)
        REFERENCES outfits(outfit_id)
        ON DELETE CASCADE,

    FOREIGN KEY (item_id)
        REFERENCES clothing_items(item_id)
        ON DELETE CASCADE

) ENGINE=InnoDB;

-- 6. ACTIVITY LOGS TABLE
-- - login
-- - uploads
-- - outfit creation
-- - admin actions

CREATE TABLE activity_logs (

    log_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NULL,

    activity_type VARCHAR(100),

    activity_description TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL

) ENGINE=InnoDB;

-- INITIAL CATEGORY DATA

INSERT INTO categories (category_name)
VALUES
('Tops'),
('Bottoms'),
('Shoes'),
('Outerwear'),
('Accessories');

ALTER TABLE clothing_items
ADD COLUMN expected_return_date DATE NULL DEFAULT NULL;

ALTER TABLE users
ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL;

CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    type ENUM('email_change', 'password_reset') NOT NULL,
    new_data VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- SAMPLE ADMIN ACCOUNT

INSERT INTO users
(
    username,
    email,
    password_hash,
    role
)

VALUES
(
    'admin',
    'admin@swardrobe.com',
    '1234test123',
    'admin'
);

UPDATE users
SET
  password_hash = '$2y$10$rkNV9nBbsVphEc.cw6bs7O1EJJ0ZVJ3BWI2h1SzHSeJKEhMF30Cki',
  role = 'admin'
WHERE username = 'admin'
   OR email = 'admin@swardrobe.com';

