-- Disaster Relief Camp & Volunteer Coordination System
-- Database Creation Script (database.sql)
-- Recommended Tool: phpMyAdmin, MySQL CLI, or Laragon

-- 1. Create the Database if it does not exist
CREATE DATABASE IF NOT EXISTS disaster_relief_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE disaster_relief_db;

-- 2. Drop tables in correct order of dependency
DROP TABLE IF EXISTS help_requests;
DROP TABLE IF EXISTS affected_families;
DROP TABLE IF EXISTS camps;
DROP TABLE IF EXISTS upazilas;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS disaster_events;

-- 2.1 Create the 'upazilas' Table (Administrative regions)
CREATE TABLE upazilas (
    upazila_id INT PRIMARY KEY AUTO_INCREMENT,
    upazila_name VARCHAR(100) NOT NULL,
    district_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2.2 Create the 'users' Table (For managers/volunteers)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role VARCHAR(50) DEFAULT 'volunteer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2.3 Create the 'disaster_events' Table (Disaster context)
CREATE TABLE disaster_events (
    event_id INT PRIMARY KEY AUTO_INCREMENT,
    event_name VARCHAR(100) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Create the 'camps' Table
-- This table lists active shelters and camp details
CREATE TABLE camps (
    camp_id INT PRIMARY KEY AUTO_INCREMENT,
    camp_name VARCHAR(100) NOT NULL,
    upazila_id INT,
    address TEXT NOT NULL,
    capacity INT DEFAULT 0,
    manager_id INT,
    event_id INT,
    FOREIGN KEY (upazila_id) REFERENCES upazilas(upazila_id),
    FOREIGN KEY (manager_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (event_id) REFERENCES disaster_events(event_id)
) ENGINE=InnoDB;


-- 4. Create the 'affected_families' Table (Merged with help_requests)
-- This table stores assistance requests and details of affected families.
CREATE TABLE affected_families (
    -- Unique Request/Family ID, automatically incremented
    family_id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Name of the Head of the Family
    head_name VARCHAR(100) NOT NULL,
    
    -- Contact Mobile Number
    mobile VARCHAR(15) NOT NULL,
    
    -- National ID Card Number (Optional, Unique index to prevent duplicate records)
    nid_no VARCHAR(20) UNIQUE DEFAULT NULL,
    
    -- Count of members in the family
    member_count INT NOT NULL DEFAULT 1,
    
    -- Detailed physical location or address
    address TEXT NOT NULL,
    
    -- Type of disaster (e.g. Flood, Cyclone, Fire, Earthquake)
    disaster_type VARCHAR(50) NOT NULL,
    
    -- Primary type of assistance required (e.g. Food, Medicine, Shelter, Rescue, Water)
    help_needed VARCHAR(50) NOT NULL,
    
    -- Explanation or details of their situation
    description TEXT NOT NULL,
    
    -- Status of the request (Initially 'Pending')
    -- Possible values: 'Pending', 'In Progress', 'Assigned', 'Resolved'
    status VARCHAR(20) DEFAULT 'Pending',
    
    -- Foreign Key referencing camps table
    camp_id INT DEFAULT NULL,
    
    -- Information/updates regarding the support action taken
    support_info TEXT DEFAULT NULL,
    
    -- Record creation time
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Automatically updates whenever status or camp assignments are modified
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Set Relation: Link camp_id to active camps. 
    -- If a camp is deleted, set this reference to NULL instead of deleting requests.
    FOREIGN KEY (camp_id) REFERENCES camps(camp_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Seed Initial Data for Administrative & Coordination Tables
INSERT INTO upazilas (upazila_id, upazila_name, district_name) VALUES
(1, 'Sylhet Sadar', 'Sylhet'),
(2, 'Cox\'s Bazar Sadar', 'Cox\'s Bazar'),
(3, 'Shamnagar', 'Satkhira');

INSERT INTO users (user_id, username, full_name, email, role) VALUES
(1, 'manager1', 'John Doe', 'john@disasterrelief.gov.bd', 'manager'),
(2, 'manager2', 'Jane Smith', 'jane@disasterrelief.gov.bd', 'manager'),
(3, 'manager3', 'Ali Ahmed', 'ali@disasterrelief.gov.bd', 'manager');

INSERT INTO disaster_events (event_id, event_name, event_type, event_date) VALUES
(1, 'Sylhet Flash Flood 2026', 'Flood', '2026-05-15'),
(2, 'Cyclone Remal 2026', 'Cyclone', '2026-05-20'),
(3, 'Satkhira Drought 2026', 'Drought', '2026-05-25');

-- 6. Seed Initial Data for active Camps
INSERT INTO camps (camp_id, camp_name, upazila_id, address, capacity, manager_id, event_id) VALUES
(1, 'Sylhet Model High School Camp', 1, 'Sadar Bazar, Sylhet District', 500, 1, 1),
(2, 'Cox\'s Bazar College Shelter', 2, 'College Road, Cox\'s Bazar', 400, 2, 2),
(3, 'Satkhira Primary Camp', 3, 'Shamnagar Upazila, Satkhira', 300, 3, 3);

-- 6. Seed Sample Families for Status Track Testing
INSERT INTO affected_families (head_name, mobile, nid_no, member_count, address, disaster_type, help_needed, description, status, camp_id, support_info) 
VALUES 
('Rahim Uddin', '01712345678', '1995123456789', 5, 'Sadar Bazar, Sylhet', 'Flood', 'Shelter', 'Water surrounding our house, need urgent shelter and drinking water.', 'Assigned', 1, 'Emergency rescue team has been notified. Relief food packets will be delivered at 10:00 AM.'),
('Karim Mia', '01812345678', NULL, 3, 'Chokoria, Coxs Bazar', 'Cyclone', 'Food', 'House roof damaged by high winds. Shortage of dry food items.', 'Pending', NULL, NULL);
