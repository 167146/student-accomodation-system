CREATE DATABASE IF NOT EXISTS student_accommodation;

USE student_accommodation;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('student', 'landlord', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    address VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    price DECIMAL(10,2),
    availability_status ENUM(
        'available',
        'unavailable',
        'under_review'
    ) DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (landlord_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE property_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL UNIQUE,

    verification_status ENUM(
        'unverified',
        'under_review',
        'verified',
        'suspended'
    ) DEFAULT 'unverified',

    trust_score DECIMAL(5,2) DEFAULT 0,

    last_verified_at DATETIME NULL,
    last_activity_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (property_id)
        REFERENCES properties(id)
        ON DELETE CASCADE
);