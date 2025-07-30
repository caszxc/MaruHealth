<?php
//config.php
$host = "localhost";
$dbname = "maruhealthdb";
$username = "root";
$password = "";

try {
    // Connect to MySQL without specifying a database
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it does not exist
    $conn->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    
    // Connect to the newly created database
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set MySQL timezone to Asia/Manila
    $conn->exec("SET time_zone = '+08:00'");
    
    // SQL to create events table
    $sql = "CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        image VARCHAR(255) NULL,
        start TIME NOT NULL,
        end TIME NOT NULL,
        venue VARCHAR(255) NULL
    )";
    
    $conn->exec($sql);

    // Create Users Table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(255) NOT NULL,
        last_name VARCHAR(255) NOT NULL,
        middle_name VARCHAR(255) NOT NULL,
        gender ENUM('Male', 'Female') NOT NULL,
        birthday DATE NOT NULL,
        address TEXT NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone_number VARCHAR(20) UNIQUE NOT NULL,
        valid_id_front VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        profile_picture VARCHAR(255) DEFAULT NULL, 
        role VARCHAR(20),
        date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);

    // Create Pending Users Table
    $sql = "CREATE TABLE IF NOT EXISTS pending_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(255) NOT NULL,
        last_name VARCHAR(255) NOT NULL,
        middle_name VARCHAR(255) NOT NULL,
        gender ENUM('Male', 'Female') NOT NULL,
        birthday DATE NOT NULL,
        address TEXT NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone_number VARCHAR(20) UNIQUE NOT NULL,
        valid_id_front VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) default 'user',
        date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $conn->exec($sql);

    $sql = "CREATE TABLE IF NOT EXISTS admin_staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        role ENUM('super_admin', 'admin', 'staff') NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);
    

    // Check if admin exists
    $checkAdmin = $conn->prepare("SELECT * FROM admin_staff WHERE role = 'super_admin'");
    $checkAdmin->execute();
    
    if ($checkAdmin->rowCount() == 0) {
        // Insert super admin user if not exists
        $hashed_password = password_hash("superadmin123", PASSWORD_DEFAULT);
        $insertAdmin = $conn->prepare("INSERT INTO admin_staff (full_name, role, email, username, password) VALUES (:fullName, 'super_admin', :email, :username, :password)");
        $insertAdmin->execute([
            ':fullName' => 'Super Admin',
            ':email' => 'super_admin@gmail.com',
            ':username' => 'super_admin',
            ':password' => $hashed_password
        ]);
    }

    // Create announcements table
    $sql = "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image VARCHAR(255) NULL,
        admin_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'archived') DEFAULT 'active',
        FOREIGN KEY (admin_id) REFERENCES admin_staff(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create main Services Table (e.g., Check Up, Family Planning, etc.)
    $sql = "CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        icon_path VARCHAR(255) NOT NULL,
        intro TEXT
    )";
    $conn->exec($sql);

    // Insert services into the services table
    $services = [
        [
            'name' => 'Check Up',
            'description' => 'Ensures that residents receive proper medical attention for their health concerns. Prenatal care is available for expectant mothers, offering regular monitoring of pregnancy health, nutritional guidance, and essential check-ups to ensure a safe delivery. Senior citizens can undergo routine health assessments, including blood pressure checks and diabetes screenings, to help manage their well-being. Daily consultations are also open to all residents, allowing them to seek medical advice, address minor illnesses, and receive early treatment before conditions worsen.',
            'icon_path' => './images/uploads/service_images/icons/checkup.png',
            'intro' => 'Prenatal care, senior check-ups, and daily consultations for overall wellness.'
        ],
        [
            'name' => 'Vaccination',
            'description' => 'Vaccinations are provided for infants, children, and adults to protect against preventable diseases. The health center administers vaccines as per DOH guidelines and schedules. Regular immunization days are conducted for infants and booster shots are available for school-age children and adults.',
            'icon_path' => './images/uploads/service_images/icons/vaccine.png',
            'intro' => 'Child immunization and other free barangay-provided vaccines.'
        ],
        [
            'name' => 'Family Planning',
            'description' => 'The Family Planning service provides counseling and access to contraceptives for couples and individuals who wish to manage the timing and size of their family. Educational sessions, consultations, and free commodities are provided.',
            'icon_path' => './images/uploads/service_images/icons/family.png',
            'intro' => 'Guidance and services for reproductive health and contraception.'
        ],
        [
            'name' => 'Dental Care',
            'description' => 'Basic dental services including oral examination, tooth extraction, and dental health education are provided. The goal is to promote oral hygiene and prevent dental issues through accessible and affordable dental care.',
            'icon_path' => './images/uploads/service_images/icons/dentist.png',
            'intro' => 'Simple tooth extractions for dental care.'
        ]
    ];

    foreach ($services as $service) {
        $checkService = $conn->prepare("SELECT COUNT(*) FROM services WHERE name = :name");
        $checkService->execute([':name' => $service['name']]);
        $exists = $checkService->fetchColumn();

        if ($exists == 0) {
            $insertService = $conn->prepare("INSERT INTO services (name, description, icon_path, intro) VALUES (:name, :description, :icon_path, :intro)");
            $insertService->execute([
                ':name' => $service['name'],
                ':description' => $service['description'],
                ':icon_path' => $service['icon_path'],
                ':intro' => $service['intro']
            ]);
        }
    }


    // Create Sub-Services Table (e.g., Daily Check Up, Prenatal Check Up)
    $sql = "CREATE TABLE IF NOT EXISTS  sub_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create Schedules Table(e.g., Monday, Friday)
    $sql = "CREATE TABLE IF NOT EXISTS  schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sub_service_id INT NOT NULL,
        day_of_schedule VARCHAR(20) NOT NULL,
        FOREIGN KEY (sub_service_id) REFERENCES sub_services(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create Service Images Table
    $sql = "CREATE TABLE IF NOT EXISTS service_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create Medicines Table
    $sql = "CREATE TABLE IF NOT EXISTS medicines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        therapeutic_category VARCHAR(50) NOT NULL,
        batch_lot_number VARCHAR(50) NOT NULL,
        pono VARCHAR(50),
        generic_name VARCHAR(255) NOT NULL,
        brand_name VARCHAR(255),
        dosage VARCHAR(50),
        dosage_form VARCHAR(100),
        unit VARCHAR(50),
        manufacturing_date DATE,
        expiration_date DATE,
        stocks INT DEFAULT 0,
        min_stock INT NOT NULL DEFAULT 0,
        source VARCHAR(255),
        stock_status ENUM('In Stock', 'Low Stock', 'Out of Stock') NOT NULL DEFAULT 'In Stock', 
        expiry_status ENUM('Valid', 'Expiring within a month', 'Expiring within a week', 'Expired') NOT NULL DEFAULT 'Valid',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);

    $sql = "CREATE TABLE IF NOT EXISTS stock_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    quantity_change INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES admin_staff(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    
    // Create Medicine Request Table
   $sql = "CREATE TABLE IF NOT EXISTS medicine_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id VARCHAR(50) UNIQUE NOT NULL,
        user_id INT NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        gender ENUM('Male', 'Female', 'Other') NOT NULL,
        birthdate DATE NOT NULL,
        address VARCHAR(100),
        phone VARCHAR(20) NOT NULL,
        reason TEXT,
        request_status ENUM('pending', 'to be claimed', 'claimed', 'declined') NOT NULL DEFAULT 'pending',
        prescription VARCHAR(255) NOT NULL,
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        claim_date DATETIME NULL,
        claim_until_date DATETIME NULL,
        claimed_date DATETIME NULL,
        note TEXT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create Requested Medicine Table
    $sql = "CREATE TABLE IF NOT EXISTS requested_medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    medicine_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100),
    quantity INT,
    status ENUM('requested', 'approved', 'declined') NOT NULL DEFAULT 'requested',
    FOREIGN KEY (request_id) REFERENCES medicine_requests(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create medicine_distributions table
    $sql = "CREATE TABLE IF NOT EXISTS medicine_distributions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        requested_medicine_id INT NOT NULL,
        inventory_medicine_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        status ENUM('reserved', 'claimed', 'returned') NOT NULL DEFAULT 'reserved',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES medicine_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (requested_medicine_id) REFERENCES requested_medicines(id) ON DELETE CASCADE,
        FOREIGN KEY (inventory_medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    // Create Family Number Table
    $sql = "CREATE TABLE IF NOT EXISTS families (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_number VARCHAR(50) UNIQUE NOT NULL,
    member_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
            
    $conn->exec($sql);

    // Create Patient Table
    $sql = "CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_number VARCHAR(50) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    birthdate DATE NOT NULL,
    sex ENUM('Male', 'Female', 'Other') NOT NULL,
    civil_status ENUM('Single', 'Married', 'Divorced', 'Widowed') NOT NULL,
    contact_number VARCHAR(20),
    occupation VARCHAR(100),
    address VARCHAR(100),
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    bmi DECIMAL(4,1),
    bmi_status ENUM('Underweight', 'Normal', 'Overweight', 'Obese'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);

    // Create Patient Consultation Table
    $sql = "CREATE TABLE IF NOT EXISTS consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    consultation_type ENUM('General Check Up', 'Vaccination', 'Prenatal', 'Dentistry', 'Family Planning') NOT NULL,
    consultation_date DATE NOT NULL,
    reason_for_consultation TEXT NOT NULL,
    blood_pressure VARCHAR(20),
    temperature DECIMAL(4,1),
    diagnosis TEXT NOT NULL,
    prescribed_medicine TEXT,
    treatment_given TEXT,
    consulting_physician_nurse VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    )";
    
    $conn->exec($sql);

    // SMS logs Table
    $sql = "CREATE TABLE IF NOT EXISTS sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_name VARCHAR(255) NOT NULL,
        recipient_phone VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('success', 'failed') NOT NULL,
        error_message TEXT,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);

    // Create Email logs Table
    $sql = "CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_name VARCHAR(255) NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('success', 'failed') NOT NULL,
        error_message TEXT,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);

    // Create Password Reset Tokens Table
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);

    
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

?>
