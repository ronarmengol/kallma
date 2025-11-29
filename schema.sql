CREATE DATABASE IF NOT EXISTS kallma;
USE kallma;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    duration_minutes INT NOT NULL,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS masseuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    bio TEXT,
    specialties TEXT, -- Comma separated list of service IDs or text
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    service_id INT NOT NULL,
    masseuse_id INT NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (masseuse_id) REFERENCES masseuses(id)
);

CREATE TABLE IF NOT EXISTS availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    masseuse_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (masseuse_id) REFERENCES masseuses(id) ON DELETE CASCADE
);

-- Insert default admin user (password: password123)
INSERT INTO users (name, mobile, password, role) VALUES ('Admin', '0977777777', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert some sample services
INSERT INTO services (name, description, price, duration_minutes, image_url) VALUES 
('Swedish Massage', 'A gentle, relaxing massage.', 80.00, 60, 'assets/images/swedish.jpg'),
('Deep Tissue Massage', 'Relieves severe tension in the muscle.', 100.00, 60, 'assets/images/deep-tissue.jpg'),
('Hot Stone Massage', 'Smooth, flat, heated stones are placed on specific parts of your body.', 120.00, 90, 'assets/images/hot-stone.jpg');

-- Insert some sample masseuses
INSERT INTO masseuses (name, bio, specialties) VALUES 
('Sarah Jenkins', 'Expert in Swedish and Aromatherapy.', 'Swedish Massage'),
('Mike Ross', 'Specializes in Deep Tissue and Sports Massage.', 'Deep Tissue Massage');

-- Insert sample availability
INSERT INTO availability (masseuse_id, day_of_week, start_time, end_time) VALUES 
(1, 'Monday', '09:00:00', '17:00:00'),
(1, 'Tuesday', '09:00:00', '17:00:00'),
(2, 'Monday', '10:00:00', '18:00:00'),
(2, 'Wednesday', '10:00:00', '18:00:00');
