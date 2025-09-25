<?php
//register.php
include 'config.php';

$registrationSuccess = false;
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get registration type
        $registrationType = filter_input(INPUT_POST, 'registration_type', FILTER_SANITIZE_STRING) ?: 'personal';
        
        // Initialize variables
        $firstName = $lastName = $middleName = $gender = $birthday = $address = '';
        $email = $phone = $password = $confirmPassword = '';
        $familyNumber = null;
        $guardianData = null;
        $validIdFiles = [];
        
        // Handle different registration types
        switch ($registrationType) {
            case 'personal':
                $familyNumber = filter_input(INPUT_POST, 'family_number', FILTER_SANITIZE_STRING); 
                $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
                $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
                $middleName = filter_input(INPUT_POST, 'middleName', FILTER_SANITIZE_STRING);
                $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
                $birthday = filter_input(INPUT_POST, 'birthday', FILTER_SANITIZE_STRING);
                $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
                $validIdFiles['user'] = $_FILES["validID_front"] ?? null;
                break;
                
            case 'child':
                $familyNumber = filter_input(INPUT_POST, 'child_family_number', FILTER_SANITIZE_STRING);
                $firstName = filter_input(INPUT_POST, 'child_firstName', FILTER_SANITIZE_STRING);
                $lastName = filter_input(INPUT_POST, 'child_lastName', FILTER_SANITIZE_STRING);
                $middleName = filter_input(INPUT_POST, 'child_middleName', FILTER_SANITIZE_STRING);
                $gender = filter_input(INPUT_POST, 'child_gender', FILTER_SANITIZE_STRING);
                $birthday = filter_input(INPUT_POST, 'child_birthday', FILTER_SANITIZE_STRING);
                $address = filter_input(INPUT_POST, 'child_address', FILTER_SANITIZE_STRING);
                
                // Guardian info for child
                $guardianData = [
                    'fullname' => filter_input(INPUT_POST, 'guardian_fullname', FILTER_SANITIZE_STRING),
                    'relationship' => filter_input(INPUT_POST, 'guardian_relationship', FILTER_SANITIZE_STRING),
                    'phone' => filter_input(INPUT_POST, 'guardian_phone', FILTER_SANITIZE_STRING),
                    'email' => filter_input(INPUT_POST, 'guardian_email', FILTER_SANITIZE_EMAIL)
                ];
                
                $email = $guardianData['email']; // Use guardian's email for account
                $phone = $guardianData['phone']; // Use guardian's phone for account
                
                $validIdFiles['user'] = $_FILES["child_validID"] ?? null;
                $validIdFiles['guardian'] = $_FILES["guardian_validID"] ?? null;
                break;
                
            case 'senior':
                $familyNumber = filter_input(INPUT_POST, 'senior_family_number', FILTER_SANITIZE_STRING);
                $firstName = filter_input(INPUT_POST, 'senior_firstName', FILTER_SANITIZE_STRING);
                $lastName = filter_input(INPUT_POST, 'senior_lastName', FILTER_SANITIZE_STRING);
                $middleName = filter_input(INPUT_POST, 'senior_middleName', FILTER_SANITIZE_STRING);
                $gender = filter_input(INPUT_POST, 'senior_gender', FILTER_SANITIZE_STRING);
                $birthday = filter_input(INPUT_POST, 'senior_birthday', FILTER_SANITIZE_STRING);
                $address = filter_input(INPUT_POST, 'senior_address', FILTER_SANITIZE_STRING);
                
                // Guardian info for senior
                $guardianData = [
                    'fullname' => filter_input(INPUT_POST, 'senior_guardian_fullname', FILTER_SANITIZE_STRING),
                    'relationship' => filter_input(INPUT_POST, 'senior_guardian_relationship', FILTER_SANITIZE_STRING),
                    'phone' => filter_input(INPUT_POST, 'senior_guardian_phone', FILTER_SANITIZE_STRING),
                    'email' => filter_input(INPUT_POST, 'senior_guardian_email', FILTER_SANITIZE_EMAIL)
                ];
                
                $email = $guardianData['email']; // Use guardian's email for account
                $phone = $guardianData['phone']; // Use guardian's phone for account
                
                $validIdFiles['user'] = $_FILES["senior_validID"] ?? null;
                $validIdFiles['guardian'] = $_FILES["senior_guardian_validID"] ?? null;
                break;
        }
        
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        // Validate required fields
        if (empty($firstName)) $errors[] = "First name is required";
        if (empty($lastName)) $errors[] = "Last name is required";
        if (empty($gender)) $errors[] = "Gender is required";
        if (empty($birthday)) $errors[] = "Date of birth is required";
        if (empty($address)) $errors[] = "Address is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($phone)) $errors[] = "Phone number is required";
        if (empty($password)) $errors[] = "Password is required";
        if (empty($confirmPassword)) $errors[] = "Password confirmation is required";
        
        // Validate guardian data if needed
        if ($guardianData) {
            if (empty($guardianData['fullname'])) $errors[] = "Guardian full name is required";
            if (empty($guardianData['relationship'])) $errors[] = "Guardian relationship is required";
            if (empty($guardianData['phone'])) $errors[] = "Guardian phone number is required";
            if (empty($guardianData['email'])) $errors[] = "Guardian email is required";
        }
        
        // Validate email format
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Validate phone format
        if (!empty($phone) && !preg_match('/^(\+63|0)[9][0-9]{9}$/', $phone)) {
            $errors[] = "Invalid phone number format";
        }
        
        // Validate password
        if (!empty($password) && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, and numbers";
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }
        
        // Validate age based on registration type
        if (!empty($birthday)) {
            $birthDate = new DateTime($birthday);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            switch ($registrationType) {
                case 'personal':
                    if ($age < 18) {
                        $errors[] = "You must be at least 18 years old to register";
                    }
                    break;
                case 'child':
                    if ($age >= 18) {
                        $errors[] = "Child registration is for individuals under 18 years old";
                    }
                    break;
                case 'senior':
                    if ($age < 60) {
                        $errors[] = "Senior registration is for individuals 60 years old and above";
                    }
                    break;
            }
        }
        
        // Validate file uploads
        if (!isset($validIdFiles['user']) || $validIdFiles['user']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Valid ID is required";
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($validIdFiles['user']['type'], $allowedTypes)) {
                $errors[] = "Invalid file type for ID. Please upload JPEG or PNG images only";
            }
            if ($validIdFiles['user']['size'] > $maxSize) {
                $errors[] = "ID file size exceeds the 5MB limit";
            }
        }
        
        // Validate guardian ID if guardian data exists
        if ($guardianData && (!isset($validIdFiles['guardian']) || $validIdFiles['guardian']['error'] !== UPLOAD_ERR_OK)) {
            $errors[] = "Guardian's valid ID is required";
        } elseif ($guardianData && isset($validIdFiles['guardian'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($validIdFiles['guardian']['type'], $allowedTypes)) {
                $errors[] = "Invalid file type for guardian ID. Please upload JPEG or PNG images only";
            }
            if ($validIdFiles['guardian']['size'] > $maxSize) {
                $errors[] = "Guardian ID file size exceeds the 5MB limit";
            }
        }
        
        // If no errors, proceed with registration
        if (empty($errors)) {
            // Check if email or phone already exists
            $stmt = $conn->prepare("
                SELECT 'users' as source FROM users WHERE email = :email OR phone_number = :phone
                UNION
                SELECT 'pending_users' as source FROM pending_users WHERE email = :email OR phone_number = :phone
            ");
            $stmt->execute([':email' => $email, ':phone' => $phone]);
            
            if ($stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result['source'] === 'users') {
                    $errors[] = "Email or phone number is already registered with an active account";
                } else {
                    $errors[] = "Email or phone number is already pending approval";
                }
            } else {
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Handle file uploads
                $uploadDir = "images/uploads/IDs/";
                $userIdPath = '';
                $guardianIdPath = '';
                
                // Upload user ID
                $fileExtension = pathinfo($validIdFiles['user']['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('id_') . '.' . $fileExtension;
                $userIdPath = $uploadDir . $newFileName;
                
                if (!move_uploaded_file($validIdFiles['user']['tmp_name'], $userIdPath)) {
                    $errors[] = "Error uploading user ID file. Please try again.";
                } else {
                    // Upload guardian ID if exists
                    if ($guardianData && isset($validIdFiles['guardian'])) {
                        $guardianFileExtension = pathinfo($validIdFiles['guardian']['name'], PATHINFO_EXTENSION);
                        $guardianFileName = uniqid('guardian_id_') . '.' . $guardianFileExtension;
                        $guardianIdPath = $uploadDir . $guardianFileName;
                        
                        if (!move_uploaded_file($validIdFiles['guardian']['tmp_name'], $guardianIdPath)) {
                            $errors[] = "Error uploading guardian ID file. Please try again.";
                            // Clean up user file if guardian upload fails
                            unlink($userIdPath);
                        }
                    }
                    
                    if (empty($errors)) {
                        // Begin transaction
                        $conn->beginTransaction();
                        
                        try {
                            // Determine age category
                            $ageCategory = 'adult';
                            if (!empty($birthday)) {
                                $birthDate = new DateTime($birthday);
                                $today = new DateTime();
                                $age = $today->diff($birthDate)->y;
                                
                                if ($age < 18) {
                                    $ageCategory = 'child';
                                } elseif ($age >= 60) {
                                    $ageCategory = 'senior';
                                }
                            }
                            
                            // Insert into pending_users table
                            $stmt = $conn->prepare("INSERT INTO pending_users 
                                (first_name, last_name, middle_name, gender, birthday, address, email, phone_number, valid_id_front, password, registration_type, age_category, family_number, date_registered) 
                                VALUES 
                                (:firstName, :lastName, :middleName, :gender, :birthday, :address, :email, :phone, :validIDFront, :password, :registrationType, :ageCategory, :familyNumber, NOW())");

                            $stmt->execute([
                                ':firstName' => $firstName,
                                ':lastName' => $lastName,
                                ':middleName' => $middleName,
                                ':gender' => $gender,
                                ':birthday' => $birthday,
                                ':address' => $address,
                                ':email' => $email,
                                ':phone' => $phone,
                                ':validIDFront' => $userIdPath,
                                ':password' => $hashedPassword,
                                ':registrationType' => $registrationType,
                                ':ageCategory' => $ageCategory,
                                ':familyNumber' => $familyNumber
                            ]);
                            
                            $pendingUserId = $conn->lastInsertId();
                            
                            // Insert guardian data if exists
                            if ($guardianData && $guardianIdPath) {
                                $guardianStmt = $conn->prepare("INSERT INTO guardians 
                                    (pending_user_id, full_name, relationship, phone_number, email, valid_id_path) 
                                    VALUES 
                                    (:pendingUserId, :fullName, :relationship, :phone, :email, :validIdPath)");
                                
                                $guardianStmt->execute([
                                    ':pendingUserId' => $pendingUserId,
                                    ':fullName' => $guardianData['fullname'],
                                    ':relationship' => $guardianData['relationship'],
                                    ':phone' => $guardianData['phone'],
                                    ':email' => $guardianData['email'],
                                    ':validIdPath' => $guardianIdPath
                                ]);
                            }
                            
                            // Commit transaction
                            $conn->commit();
                            $registrationSuccess = true;
                            
                        } catch (Exception $e) {
                            // Rollback transaction
                            $conn->rollback();
                            
                            // Clean up uploaded files
                            if (file_exists($userIdPath)) unlink($userIdPath);
                            if ($guardianIdPath && file_exists($guardianIdPath)) unlink($guardianIdPath);
                            
                            $errors[] = "Registration failed: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Registration failed: " . $e->getMessage();
    }
}
?>


<html>
<head>
    <link rel="stylesheet" href="css/register.css">
    <link rel="stylesheet" href="css/nav_footer.css">
    <link rel="stylesheet" href="css/policy_terms.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Istok+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Register</title>
</head>
<body>
    <nav>
        <div class="logo-container">
            <img src="images/3s logo.png">
            <div>
                <h1>Maru-Health</h1>
                <p>Barangay Marulas 3S Health Station</p>
            </div>
        </div>
    </nav>
    <div class="register-container">
        <div class="register-content">
            <div class="register-box">
                <h2 id="formTitle">REGISTRATION FORM</h2>
                <hr>
                <p>Account creation supports three registration types: <strong>Personal</strong> (for individuals 18+ years old), <strong>Child</strong> (for minors under 18 with guardian assistance), and <strong>Senior Citizen</strong> (for individuals 60+ who may need guardian assistance). All users must provide accurate information during registration. Guardian information is required for child and senior registrations to ensure proper account management and communication.</p>
                <div class="stepper-wrapper" id="stepper">
                    <div class="stepper-item active">
                        <div class="step-counter">1</div>
                        <div class="step-name">Basic Information</div>
                    </div>
                    <div class="stepper-item">
                        <div class="step-counter">2</div>
                        <div class="step-name">Account Credentials</div>
                    </div>
                    <div class="stepper-item">
                        <div class="step-counter">3</div>
                        <div class="step-name">Review Your Information</div>
                    </div>
                </div>
                <!-- Updated form part that needs to be integrated into the register.php file -->
                <form method="POST" class="register-form" enctype="multipart/form-data" novalidate>
                    <?php if (!empty($errors)): ?>
                    <div class="error-summary">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Step 1 -->
                    <div class="form-step active">
                        <div class="group-col">
                            <label>Registering for <span class="required">*</span></label>
                            <select id="category">
                                <option value="personal" selected>Personal</option> 
                                <option value="child">Child</option> 
                                <option value="senior">Senior Citizen</option>
                            </select>
                        </div>
                        <!-- Personal -->
                        <div id="personalFields" class="">
                            <div class="group-col">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="lastName" required value="<?= htmlspecialchars($_POST['lastName'] ?? '') ?>" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="firstName" required value="<?= htmlspecialchars($_POST['firstName'] ?? '') ?>" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Middle Name</label>
                                <input type="text" name="middleName" value="<?= htmlspecialchars($_POST['middleName'] ?? '') ?>" autocomplete="off">
                            </div>

                            <div class="group-row">
                                <div class="group-col">
                                    <label>Gender <span class="required">*</span></label>
                                    <select name="gender" required>
                                        <option value="" disabled <?= empty($_POST['gender']) ? 'selected' : '' ?>>Select Gender</option>
                                        <option value="Male" <?= isset($_POST['gender']) && $_POST['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= isset($_POST['gender']) && $_POST['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                
                                <div class="group-col">
                                    <label>Date of Birth <span class="required">*</span></label>
                                    <input type="date" name="birthday" required value="<?= htmlspecialchars($_POST['birthday'] ?? '') ?>">
                                    <small class="field-hint">You must be at least 18 years old</small>
                                </div>    
                            </div>   
                            
                            <div class="group-col">
                                <label>Address <span class="required">*</span></label>
                                <input type="text" name="address" required value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Family Number (Optional)</label>
                                <input type="text" name="family_number" value="<?= htmlspecialchars($_POST['family_number'] ?? '') ?>" placeholder="Enter family number if applicable" autocomplete="off">
                                <small class="field-hint">Optional: Enter your family number for record linking</small>
                            </div>

                            <div class="group-col">
                                <label>Upload Valid ID <span class="required">*</span></label>
                                <div class="file-upload">
                                    <label for="file-upload" class="custom-file-upload">
                                        <i class="fas fa-cloud-upload-alt"></i> Add File
                                    </label>
                                    <input id="file-upload" type="file" name="validID_front" onchange="updateFileName()" accept="image/jpeg,image/png" required />
                                    <span id="file-name">No file chosen</span>
                                </div>
                                <small class="field-hint">Max file size: 5MB. Accepted formats: JPEG, PNG</small>
                            </div>

                            <div class="button-container">
                                <button type="button" class="next-step">Next</button>
                            </div>
                        </div>

                        <div id="childFields" class="hidden">
                            <h3>I. Child Information</h3>
                            <div class="group-col">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="child_lastName" id="child_lastName" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="child_firstName" id="child_firstName" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Middle Name</label>
                                <input type="text" name="child_middleName" id="child_middleName" autocomplete="off">
                            </div>

                            <div class="group-row">
                                <div class="group-col">
                                    <label>Gender <span class="required">*</span></label>
                                    <select name="child_gender" id="child_gender">
                                        <option value="" disabled selected>Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                
                                <div class="group-col">
                                    <label>Date of Birth <span class="required">*</span></label>
                                    <input type="date" name="child_birthday" id="child_birthday">
                                    <small class="field-hint">Must be under 18 years old</small>
                                </div>    
                            </div>   
                            
                            <div class="group-col">
                                <label>Address <span class="required">*</span></label>
                                <input type="text" name="child_address" id="child_address" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Family Number (Optional)</label>
                                <input type="text" name="child_family_number" id="child_family_number" placeholder="Enter family number if applicable" autocomplete="off">
                                <small class="field-hint">Optional: Enter your family number for record linking</small>
                            </div>

                            <div class="group-col">
                                <label>Upload School ID / Birth Certificate<span class="required">*</span></label>
                                <div class="file-upload">
                                    <label for="child-file-upload" class="custom-file-upload">
                                        <i class="fas fa-cloud-upload-alt"></i> Add File
                                    </label>
                                    <input id="child-file-upload" type="file" name="child_validID" accept="image/jpeg,image/png">
                                    <span id="child-file-name">No file chosen</span>
                                </div>
                                <small class="field-hint">Max file size: 5MB. Accepted formats: JPEG, PNG</small>
                            </div>

                            <h3 style="margin-top: 10px;">II. Guardian/Authorized Registrant's Information</h3>

                            <div class="group-col">
                                <label>Full Name <span class="required">*</span></label>
                                <input type="text" name="guardian_fullname" id="guardian_fullname" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Relationship <span class="required">*</span></label>
                                <select name="guardian_relationship" id="guardian_relationship">
                                    <option value="" disabled selected>Select Relationship</option>
                                    <option value="Father">Father</option>
                                    <option value="Mother">Mother</option>
                                    <option value="Guardian">Legal Guardian</option>
                                    <option value="Grandparent">Grandparent</option>
                                    <option value="Aunt/Uncle">Aunt/Uncle</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="group-col">
                                <label>Phone Number <span class="required">*</span></label>
                                <input type="tel" name="guardian_phone" id="guardian_phone" autocomplete="off">
                                <small class="field-hint">Format: +639XXXXXXXXX or 09XXXXXXXXX</small>
                            </div>

                            <div class="group-col">
                                <label>Email <span class="required">*</span></label>
                                <input type="email" name="guardian_email" id="guardian_email" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Upload Valid ID <span class="required">*</span></label>
                                <div class="file-upload">
                                    <label for="guardian-file-upload" class="custom-file-upload">
                                        <i class="fas fa-cloud-upload-alt"></i> Add File
                                    </label>
                                    <input id="guardian-file-upload" type="file" name="guardian_validID" accept="image/jpeg,image/png">
                                    <span id="guardian-file-name">No file chosen</span>
                                </div>
                                <small class="field-hint">Max file size: 5MB. Accepted formats: JPEG, PNG</small>
                            </div>
                            
                            <div class="button-container">
                                <button type="button" class="next-step">Next</button>
                            </div>
                        </div>

                        <div id="seniorFields" class="hidden">
                            <h3>I. Senior Information</h3>
                            <div class="group-col">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="senior_lastName" id="senior_lastName" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="senior_firstName" id="senior_firstName" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Middle Name</label>
                                <input type="text" name="senior_middleName" id="senior_middleName" autocomplete="off">
                            </div>

                            <div class="group-row">
                                <div class="group-col">
                                    <label>Gender <span class="required">*</span></label>
                                    <select name="senior_gender" id="senior_gender">
                                        <option value="" disabled selected>Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                
                                <div class="group-col">
                                    <label>Date of Birth <span class="required">*</span></label>
                                    <input type="date" name="senior_birthday" id="senior_birthday">
                                    <small class="field-hint">Must be 60+ years old</small>
                                </div>    
                            </div>   
                            
                            <div class="group-col">
                                <label>Address <span class="required">*</span></label>
                                <input type="text" name="senior_address" id="senior_address" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Family Number (Optional)</label>
                                <input type="text" name="senior_family_number" id="senior_family_number" placeholder="Enter family number if applicable" autocomplete="off">
                                <small class="field-hint">Optional: Enter your family number for record linking</small>
                            </div>

                            <div class="group-col">
                                <label>Upload Valid ID / Senior Citizen ID<span class="required">*</span></label>
                                <div class="file-upload">
                                    <label for="senior-file-upload" class="custom-file-upload">
                                        <i class="fas fa-cloud-upload-alt"></i> Add File
                                    </label>
                                    <input id="senior-file-upload" type="file" name="senior_validID" accept="image/jpeg,image/png">
                                    <span id="senior-file-name">No file chosen</span>
                                </div>
                                <small class="field-hint">Max file size: 5MB. Accepted formats: JPEG, PNG</small>
                            </div>

                            <h3 style="margin-top: 10px;">II. Guardian/Authorized Registrant's Information</h3>

                            <div class="group-col">
                                <label>Full Name <span class="required">*</span></label>
                                <input type="text" name="senior_guardian_fullname" id="senior_guardian_fullname" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Relationship <span class="required">*</span></label>
                                <select name="senior_guardian_relationship" id="senior_guardian_relationship">
                                    <option value="" disabled selected>Select Relationship</option>
                                    <option value="Son/Daughter">Son/Daughter</option>
                                    <option value="Spouse">Spouse</option>
                                    <option value="Grandchild">Grandchild</option>
                                    <option value="Caregiver">Caregiver</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="group-col">
                                <label>Phone Number <span class="required">*</span></label>
                                <input type="tel" name="senior_guardian_phone" id="senior_guardian_phone" autocomplete="off">
                                <small class="field-hint">Format: +639XXXXXXXXX or 09XXXXXXXXX</small>
                            </div>

                            <div class="group-col">
                                <label>Email <span class="required">*</span></label>
                                <input type="email" name="senior_guardian_email" id="senior_guardian_email" autocomplete="off">
                            </div>

                            <div class="group-col">
                                <label>Upload Valid ID <span class="required">*</span></label>
                                <div class="file-upload">
                                    <label for="senior-guardian-file-upload" class="custom-file-upload">
                                        <i class="fas fa-cloud-upload-alt"></i> Add File
                                    </label>
                                    <input id="senior-guardian-file-upload" type="file" name="senior_guardian_validID" accept="image/jpeg,image/png">
                                    <span id="senior-guardian-file-name">No file chosen</span>
                                </div>
                                <small class="field-hint">Max file size: 5MB. Accepted formats: JPEG, PNG</small>
                            </div>

                            <div class="button-container">
                                <button type="button" class="next-step">Next</button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="form-step">
                        <!-- Personal Registration Credentials -->
                        <div id="personalCredentials">
                            <div class="group-col">
                                <label>Phone Number <span class="required">*</span></label>
                                <input type="tel" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" autocomplete="off">
                                <small class="field-hint">Format: +639XXXXXXXXX or 09XXXXXXXXX</small>
                            </div>
                            <div class="group-col">
                                <label>E-mail Address <span class="required">*</span></label>
                                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="off">
                            </div>
                        </div>
                        
                        <!-- Guardian Credentials (for child/senior registration) -->
                        <div id="guardianCredentials" style="display: none;">
                            <h4>Guardian Account Credentials</h4>
                            <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                                The guardian's email and phone will be used as the account login credentials.
                            </p>
                        </div>
                        
                        <!-- Common password fields -->
                        <div class="group-col">
                            <label>Password <span class="required">*</span></label>
                            <input type="password" name="password" required>
                            <small class="field-hint">At least 8 characters with uppercase, lowercase, and numbers</small>
                        </div>
                        <div class="group-col">
                            <label>Confirm Password <span class="required">*</span></label>
                            <input type="password" name="confirmPassword" required>
                        </div>
                        
                        <div class="button-container">
                            <button type="button" class="prev-step">Back</button>
                            <button type="button" class="next-step">Next</button>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="form-step">
                        <div class="user-info">
                            <h4>Personal Information</h4>
                            <div class="group-col">
                                <label>Last Name</label>
                                <span id="reviewLastName"></span>
                            </div>
                            <div class="group-col">
                                <label>First Name</label>
                                <span id="reviewFirstName"></span>
                            </div>
                            <div class="group-col">
                                <label>Middle Name</label>
                                <span id="reviewMiddleName"></span>
                            </div>
                            <div class="group-col">
                                <label>Gender</label>
                                <span id="reviewGender"></span>
                            </div>
                            <div class="group-col">
                                <label>Birthdate</label>
                                <span id="reviewBirthday"></span>
                            </div>
                            <div class="group-col">
                                <label>Address</label>
                                <span id="reviewAddress"></span>
                            </div>
                            <div class="group-col" id="reviewFamilyNumberContainer" style="display: none;">
                                <label>Family Number</label>
                                <span id="reviewFamilyNumber"></span>
                            </div>
                            
                            <!-- Contact Information (changes based on registration type) -->
                            <h4 id="contactInfoHeader">Contact Information</h4>
                            <div class="group-col">
                                <label>E-mail Address</label>
                                <span id="reviewEmail"></span>
                            </div>
                            <div class="group-col">
                                <label>Phone Number</label>
                                <span id="reviewPhone"></span>
                            </div>
                            
                            <!-- Guardian Information (only shows for child/senior registration) -->
                            <div id="guardianInfo" style="display: none;">
                                <h4>Guardian Information</h4>
                                <div class="group-col">
                                    <label>Guardian Name</label>
                                    <span id="reviewGuardianName"></span>
                                </div>
                                <div class="group-col">
                                    <label>Relationship</label>
                                    <span id="reviewGuardianRelationship"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="id-preview">
                            <h4>Uploaded Documents</h4>
                            <div id="userIdPreview">
                                <label id="userIdLabel">Valid ID</label>
                                <img id="idFrontPreview" src="" alt="Valid ID Front" style="display: none;">
                            </div>
                            
                            <div id="guardianIdPreview" style="display: none;">
                                <label>Guardian's Valid ID</label>
                                <img id="guardianIdImg" src="" alt="Guardian Valid ID" style="display: none;">
                            </div>
                        </div>

                        <div class="policy_terms">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" id="termsCheckbox" required>
                                I have read and accept the 
                                <a href="#" onclick="openModal('privacyModal')">Privacy Policy</a> and the
                                <a href="#" onclick="openModal('termsModal')">Terms and Conditions</a>
                            </label>
                        </div>

                        <div class="button-container">
                            <button type="button" class="prev-step">Back</button>
                            <button type="submit" class="submit-button" id="signUpBtn" disabled>Sign Up</button>
                        </div>
                    </div>
                </form>
                <p style="text-align: center; margin-top: 20px;">
                Already have an account? Go to <a href="login.php" style="color: #8B0000; font-weight: bold;">Log In</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="success-modal">
        <div class="success-content">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="success-title">Registration Successful!</h3>
            <p class="success-message">Thank you for registering! Your account is pending approval. You will receive a confirmation email once your account has been approved.</p>
            <button class="success-button" id="goToLoginBtn">Go to Login</button>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('privacyModal')">&times;</span>
            <div class="policy-terms-container">
                <div class="policy-content">
                    <h3 class="section-title">Privacy Policy</h3>
                    <p class="intro">MaruHealth is committed to protecting your privacy. This Privacy Policy explains how we collect, use, and safeguard your personal information.</p>

                    <ol>
                        <li>
                            <strong>Information We Collect</strong>
                            <ul>
                                <li>Personal Information: Name, sex, birth date, civil status, occupation, contact number, email address and 1 valid ID for proof that you are a resident of Barangay Marulas.</li>
                                <li>Medical Information: Consultation history, family folder number and family members.</li>
                                <li>Usage Data: System access logs, device information, and interactions with the platform.</li>
                            </ul>
                        </li>
                        <li>
                            <strong>How Do We Use Your Information</strong>
                            <ul>
                                <li>Providing and improving health services.</li>
                                <li>Sending notifications regarding health center updates and medicine availability.</li>
                                <li>Ensuring security and proper system functionality.</li>
                                <li>Complying with legal and regulatory requirements.</li>
                            </ul>
                        </li>
                        <li>
                            <strong>Data Sharing and Security</strong>
                            <ul>
                                <li>We do not sell or share user data with third parties, except as required by law or for health service purposes.</li>
                                <li>Personal data is encrypted and stored securely to prevent unauthorized access.</li>
                                <li>Users are responsible for keeping their login credentials confidential.</li>
                            </ul>
                        </li>
                        <li>
                            <strong>User Rights</strong>
                            <ul>
                                <li>Access and review their personal information.</li>
                                <li>Request corrections to inaccurate data.</li>
                                <li>Request deletion of their data, subject to legal and operational requirements.</li>
                            </ul>
                        </li>
                        <li>
                            <strong>Contact Information</strong>
                            <p>For privacy-related concerns, please contact Barangay Marulas 3S Health Center.<br>
                            By using MaruHealth, you acknowledge and agree to this Privacy Policy.</p>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and  Condition Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('termsModal')">&times;</span>
            <div class="policy-terms-container">
                <div class="policy-content">
                    <h3 class="section-title">Terms & Conditions</h3>
                    <p class="intro">Welcome to MaruHealth, by accessing and using this system, you agree to comply with and be bound by the following terms and conditions. Please read them carefully.</p>

                    <ol>
                        <li>
                            <strong>Acceptance of Terms</strong>
                            <p>By using MaruHealth, you acknowledge that you have read, understood, and agreed to these terms. If you do not agree with any part of these terms, you must discontinue use of the system.</p>
                        </li>

                        <li>
                            <strong>User Registration & Responsibilities</strong>
                            <ul>
                                <li>Users must provide accurate and complete information during registration.</li>
                                <li>Each user is responsible for maintaining the confidentiality of their account credentials.</li>
                                <li>Unauthorized access or use of another user's account is strictly prohibited.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Services Provided</strong>
                            <p>MaruHealth offers the following features:</p>
                            <ul>
                                <li>Checking health center schedules, announcements and health-related events.</li>
                                <li>Online medicine request.</li>
                                <li>Receiving notifications regarding the status of the requested medicine.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Privacy and Data Protection</strong>
                            <ul>
                                <li>MaruHealth values user privacy and ensures that personal data is protected in accordance with applicable data protection laws.</li>
                                <li>User information will only be used for health services management purposes.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Acceptable Use</strong>
                            <p>Users agree to:</p>
                            <ul>
                                <li>Use MaruHealth only for lawful purposes.</li>
                                <li>Refrain from transmitting any malicious software, hacking attempts, or engaging in unauthorized access.</li>
                                <li>Not disrupt the operation of the system or compromise its security.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Limitation of Liability</strong>
                            <ul>
                                <li>MaruHealth and its administrators are not liable for any damages or losses incurred due to misuse, system downtimes, or incorrect information provided by users.</li>
                                <li>The system does not replace professional medical consultations.</li>
                            </ul>
                        </li>

                        <li>
                            <strong>Termination of Access</strong>
                            <p>MaruHealth may suspend or terminate user access if there is a violation of these terms. Any misuse or unauthorized activity may lead to account deactivation or legal action.</p>
                        </li>

                        <li>
                            <strong>Governing Law</strong>
                            <p>These terms are governed by the laws of the Philippines, and any disputes shall be resolved within the appropriate legal jurisdiction.</p>
                        </li>

                        <li>
                            <strong>Contact Information</strong>
                            <p>For any inquiries or concerns regarding these terms, please contact Barangay Marulas 3S Health Center.</p>
                        </li>
                    </ol>

                    <p class="closing">By using MaruHealth, you acknowledge and agree to these terms and conditions.<br>Thank you for using our system responsibly.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Form Step Navigation
            let currentStep = 0;
            const steps = document.querySelectorAll(".form-step");
            const nextBtns = document.querySelectorAll(".next-step");
            const prevBtns = document.querySelectorAll(".prev-step");
            const stepperItems = document.querySelectorAll(".stepper-item");
            
            // Create hidden input for registration type
            const form = document.querySelector('.register-form');
            const registrationTypeInput = document.createElement('input');
            registrationTypeInput.type = 'hidden';
            registrationTypeInput.name = 'registration_type';
            registrationTypeInput.value = 'personal';
            form.appendChild(registrationTypeInput);
            
            // Update category change handler
            const category = document.getElementById("category");
            category.addEventListener("change", function() {
                // Update hidden input value
                registrationTypeInput.value = this.value;
                
                // Show/hide appropriate fields
                document.getElementById("personalFields").classList.add("hidden");
                document.getElementById("childFields").classList.add("hidden");
                document.getElementById("seniorFields").classList.add("hidden");

                if (this.value === "personal") {
                    document.getElementById("personalFields").classList.remove("hidden");
                } else if (this.value === "child") {
                    document.getElementById("childFields").classList.remove("hidden");
                } else if (this.value === "senior") {
                    document.getElementById("seniorFields").classList.remove("hidden");
                }
                updateStep2Display();
            });
            
            // Error handling functions
            function showFieldError(element, message) {
                removeFieldError(element);
                
                const errorSpan = document.createElement("span");
                errorSpan.className = "field-error";
                errorSpan.textContent = message;
                errorSpan.style.color = "#FF0000";
                errorSpan.style.fontSize = "12px";
                
                element.style.borderColor = "#FF0000";
                element.parentNode.appendChild(errorSpan);
            }

            function removeFieldError(element) {
                const existingError = element.parentNode.querySelector(".field-error");
                if (existingError) {
                    existingError.remove();
                }
                element.style.borderColor = "";
            }
            
            // Function to setup file upload handlers
            function setupFileUpload(inputId, spanId) {
                const fileInput = document.getElementById(inputId);
                const fileNameSpan = document.getElementById(spanId);
                
                if (fileInput && fileNameSpan) {
                    fileInput.addEventListener('change', function () {
                        if (this.files.length > 0) {
                            const file = this.files[0];
                            fileNameSpan.textContent = file.name;
                            
                            const validImageTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                            const maxFileSize = 5 * 1024 * 1024; // 5MB
                            
                            if (!validImageTypes.includes(file.type)) {
                                showFieldError(this, "Please upload a valid image file (JPEG, PNG)");
                                this.value = "";
                                fileNameSpan.textContent = "No file chosen";
                                return;
                            }
                            
                            if (file.size > maxFileSize) {
                                showFieldError(this, "File size exceeds 5MB limit");
                                this.value = "";
                                fileNameSpan.textContent = "No file chosen";
                                return;
                            }
                            
                            removeFieldError(this);
                        } else {
                            fileNameSpan.textContent = 'No file chosen';
                        }
                    });
                }
            }
            
            // Setup file upload handlers
            setupFileUpload('file-upload', 'file-name');
            setupFileUpload('child-file-upload', 'child-file-name');
            setupFileUpload('guardian-file-upload', 'guardian-file-name');
            setupFileUpload('senior-file-upload', 'senior-file-name');
            setupFileUpload('senior-guardian-file-upload', 'senior-guardian-file-name');
            
            // Form validation fields
            const formFields = {
                // Personal registration fields
                firstName: {
                    element: document.querySelector('input[name="firstName"]'),
                    errorMsg: "First name is required",
                    validator: (value) => value.trim().length > 0
                },
                lastName: {
                    element: document.querySelector('input[name="lastName"]'),
                    errorMsg: "Last name is required",
                    validator: (value) => value.trim().length > 0
                },
                gender: {
                    element: document.querySelector('select[name="gender"]'),
                    errorMsg: "Please select a gender",
                    validator: (value) => value !== ""
                },
                birthday: {
                    element: document.querySelector('input[name="birthday"]'),
                    errorMsg: "Date of birth is required",
                    validator: (value) => {
                        if (!value) return false;
                        const today = new Date();
                        const birthDate = new Date(value);
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const monthDiff = today.getMonth() - birthDate.getMonth();
                        
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        return age >= 18;
                    },
                    customErrorMsg: (value) => {
                        if (!value) return "Date of birth is required";
                        const today = new Date();
                        const birthDate = new Date(value);
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const monthDiff = today.getMonth() - birthDate.getMonth();
                        
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        return age < 18 ? "You must be at least 18 years old to register" : "";
                    }
                },
                address: {
                    element: document.querySelector('input[name="address"]'),
                    errorMsg: "Address is required",
                    validator: (value) => value.trim().length > 0
                },
                phone: {
                    element: document.querySelector('input[name="phone"]'),
                    errorMsg: "Phone number is required",
                    validator: (value) => {
                        const phoneRegex = /^(\+63|0)[9][0-9]{9}$/;
                        return phoneRegex.test(value.trim());
                    },
                    customErrorMsg: (value) => {
                        if (!value.trim()) return "Phone number is required";
                        return "Please enter a valid Philippines phone number";
                    }
                },
                email: {
                    element: document.querySelector('input[name="email"]'),
                    errorMsg: "Email is required",
                    validator: (value) => {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        return emailRegex.test(value.trim());
                    },
                    customErrorMsg: (value) => {
                        if (!value.trim()) return "Email is required";
                        return "Please enter a valid email address";
                    }
                },
                password: {
                    element: document.querySelector('input[name="password"]'),
                    errorMsg: "Password is required",
                    validator: (value) => {
                        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
                        return passwordRegex.test(value);
                    },
                    customErrorMsg: (value) => {
                        if (!value) return "Password is required";
                        return "Password must be at least 8 characters and include uppercase, lowercase, and numbers";
                    }
                },
                confirmPassword: {
                    element: document.querySelector('input[name="confirmPassword"]'),
                    errorMsg: "Please confirm your password",
                    validator: (value) => {
                        const password = document.querySelector('input[name="password"]').value;
                        return value === password && value.length > 0;
                    },
                    customErrorMsg: (value) => {
                        if (!value) return "Please confirm your password";
                        return "Passwords do not match";
                    }
                },
                // Child registration fields
                child_firstName: {
                    element: document.querySelector('input[name="child_firstName"]'),
                    errorMsg: "First name is required",
                    validator: (value) => value.trim().length > 0
                },
                child_lastName: {
                    element: document.querySelector('input[name="child_lastName"]'),
                    errorMsg: "Last name is required",
                    validator: (value) => value.trim().length > 0
                },
                child_gender: {
                    element: document.querySelector('select[name="child_gender"]'),
                    errorMsg: "Please select a gender",
                    validator: (value) => value !== ""
                },
                child_birthday: {
                    element: document.querySelector('input[name="child_birthday"]'),
                    errorMsg: "Date of birth is required",
                    validator: (value) => {
                        if (!value) return false;
                        const today = new Date();
                        const birthDate = new Date(value);
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const monthDiff = today.getMonth() - birthDate.getMonth();
                        
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        return age < 18;
                    },
                    customErrorMsg: (value) => {
                        if (!value) return "Date of birth is required";
                        const today = new Date();
                        const birthDate = new Date(value);
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const monthDiff = today.getMonth() - birthDate.getMonth();
                        
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        return age >= 18 ? "Child registration is for individuals under 18 years old" : "";
                    }
                },
                child_address: {
                    element: document.querySelector('input[name="child_address"]'),
                    errorMsg: "Address is required",
                    validator: (value) => value.trim().length > 0
                },
                guardian_fullname: {
                    element: document.querySelector('input[name="guardian_fullname"]'),
                    errorMsg: "Guardian full name is required",
                    validator: (value) => value.trim().length > 0
                },
                guardian_relationship: {
                    element: document.querySelector('select[name="guardian_relationship"]'),
                    errorMsg: "Guardian relationship is required",
                    validator: (value) => value !== ""
                },
                guardian_phone: {
                    element: document.querySelector('input[name="guardian_phone"]'),
                    errorMsg: "Guardian phone number is required",
                    validator: (value) => {
                        const phoneRegex = /^(\+63|0)[9][0-9]{9}$/;
                        return phoneRegex.test(value.trim());
                    },
                    customErrorMsg: (value) => {
                        if (!value.trim()) return "Guardian phone number is required";
                        return "Please enter a valid Philippines phone number";
                    }
                },
                guardian_email: {
                    element: document.querySelector('input[name="guardian_email"]'),
                    errorMsg: "Guardian email is required",
                    validator: (value) => {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        return emailRegex.test(value.trim());
                    },
                    customErrorMsg: (value) => {
                        if (!value.trim()) return "Guardian email is required";
                        return "Please enter a valid email address";
                    }
                },
                // Senior registration fields
                senior_firstName: {
                    element: document.querySelector('input[name="senior_firstName"]'),
                    errorMsg: "First name is required",
                    validator: (value) => value.trim().length > 0
                },
                senior_lastName: {
                    element: document.querySelector('input[name="senior_lastName"]'),
                    errorMsg: "Last name is required",
                    validator: (value) => value.trim().length > 0
                },
                senior_gender: {
                    element: document.querySelector('select[name="senior_gender"]'),
                    errorMsg: "Please select a gender",
                    validator: (value) => value !== ""
                },
                senior_birthday: {
                    element: document.querySelector('input[name="senior_birthday"]'),
                    errorMsg: "Date of birth is required",
                    validator: (value) => {
                        if (!value) return false;
                        const today = new Date();
                        const birthDate = new Date(value);
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const monthDiff = today.getMonth() - birthDate.getMonth();
                        
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        return age >= 60;
                    },
                    customErrorMsg: (value) => {
                        if (!value) return "Date of birth is required";
                        const today = new Date();
                        const birthDate = new Date(value);
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const monthDiff = today.getMonth() - birthDate.getMonth();
                        
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        return age < 60 ? "Senior registration is for individuals 60 years old and above" : "";
                    }
                },
                senior_address: {
                    element: document.querySelector('input[name="senior_address"]'),
                    errorMsg: "Address is required",
                    validator: (value) => value.trim().length > 0
                },
                senior_guardian_fullname: {
                    element: document.querySelector('input[name="senior_guardian_fullname"]'),
                    errorMsg: "Guardian full name is required",
                    validator: (value) => value.trim().length > 0
                },
                senior_guardian_relationship: {
                    element: document.querySelector('select[name="senior_guardian_relationship"]'),
                    errorMsg: "Guardian relationship is required",
                    validator: (value) => value !== ""
                },
                senior_guardian_phone: {
                    element: document.querySelector('input[name="senior_guardian_phone"]'),
                    errorMsg: "Guardian phone number is required",
                    validator: (value) => {
                        const phoneRegex = /^(\+63|0)[9][0-9]{9}$/;
                        return phoneRegex.test(value.trim());
                    },
                    customErrorMsg: (value) => {
                        if (!value.trim()) return "Guardian phone number is required";
                        return "Please enter a valid Philippines phone number";
                    }
                },
                senior_guardian_email: {
                    element: document.querySelector('input[name="senior_guardian_email"]'),
                    errorMsg: "Guardian email is required",
                    validator: (value) => {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        return emailRegex.test(value.trim());
                    },
                    customErrorMsg: (value) => {
                        if (!value.trim()) return "Guardian email is required";
                        return "Please enter a valid email address";
                    }
                }
            };
            
            // Updated validation function
            function validateStep(step) {
                let isValid = true;
                const currentStepElement = steps[step];
                const registrationType = document.querySelector('input[name="registration_type"]').value;
                
                const inputs = currentStepElement.querySelectorAll("input:not([type='hidden']), select");
                
                inputs.forEach(input => removeFieldError(input));
                
                inputs.forEach(input => {
                    const fieldName = input.getAttribute("name");
                    if (!fieldName || !formFields[fieldName]) return;
                    
                    // Skip validation for fields not related to current registration type
                    if (registrationType === 'personal' && (fieldName.startsWith('child_') || fieldName.startsWith('senior_') || fieldName.startsWith('guardian_'))) {
                        return;
                    }
                    if (registrationType === 'child' && (fieldName.startsWith('senior_') || (!fieldName.startsWith('child_') && !fieldName.startsWith('guardian_') && fieldName !== 'password' && fieldName !== 'confirmPassword'))) {
                        return;
                    }
                    if (registrationType === 'senior' && (fieldName.startsWith('child_') || (!fieldName.startsWith('senior_') && fieldName !== 'password' && fieldName !== 'confirmPassword'))) {
                        return;
                    }
                    
                    const field = formFields[fieldName];
                    const value = input.value;
                    
                    if (!field.validator(value)) {
                        isValid = false;
                        const errorMsg = field.customErrorMsg ? field.customErrorMsg(value) : field.errorMsg;
                        showFieldError(input, errorMsg);
                    }
                });
                
                return isValid;
            }
            
            // Update step function
            function updateStep(step) {
                steps.forEach((s, i) => {
                    s.classList.toggle("active", i === step);
                });

                stepperItems.forEach((item, index) => {
                    item.classList.remove("active", "completed");

                    if (index < step) {
                        item.classList.add("completed");
                    } else if (index === step) {
                        item.classList.add("active");
                    }
                });

                if (step === 2) {
                    populateReviewStep();
                }
            }

            // Function to update Step 2 based on registration type
            function updateStep2Display() {
                const registrationType = document.querySelector('input[name="registration_type"]').value;
                const personalCredentials = document.getElementById("personalCredentials");
                const guardianCredentials = document.getElementById("guardianCredentials");
                
                if (registrationType === 'personal') {
                    personalCredentials.style.display = "block";
                    guardianCredentials.style.display = "none";
                } else {
                    personalCredentials.style.display = "none";
                    guardianCredentials.style.display = "block";
                }
            }
            
            // Populate review step
            function populateReviewStep() {
                const registrationType = document.querySelector('input[name="registration_type"]').value;
                const guardianInfo = document.getElementById("guardianInfo");
                const guardianIdPreview = document.getElementById("guardianIdPreview");
                const userIdLabel = document.getElementById("userIdLabel");
                
                guardianInfo.style.display = "none";
                guardianIdPreview.style.display = "none";
                
                switch (registrationType) {
                    case 'personal':
                        document.getElementById("reviewFirstName").textContent = document.querySelector('input[name="firstName"]').value;
                        document.getElementById("reviewLastName").textContent = document.querySelector('input[name="lastName"]').value;
                        document.getElementById("reviewMiddleName").textContent = document.querySelector('input[name="middleName"]').value;
                        document.getElementById("reviewGender").textContent = document.querySelector('select[name="gender"]').value;
                        document.getElementById("reviewBirthday").textContent = document.querySelector('input[name="birthday"]').value;
                        document.getElementById("reviewAddress").textContent = document.querySelector('input[name="address"]').value;
                        document.getElementById("reviewEmail").textContent = document.querySelector('input[name="email"]').value;
                        document.getElementById("reviewPhone").textContent = document.querySelector('input[name="phone"]').value;
                        
                        userIdLabel.textContent = "Valid ID";
                        
                        const personalIdInput = document.querySelector('input[name="validID_front"]');
                        if (personalIdInput && personalIdInput.files.length > 0) {
                            document.getElementById("idFrontPreview").src = URL.createObjectURL(personalIdInput.files[0]);
                            document.getElementById("idFrontPreview").style.display = "block";
                        }
                        break;
                        
                    case 'child':
                        document.getElementById("reviewFirstName").textContent = document.querySelector('input[name="child_firstName"]').value;
                        document.getElementById("reviewLastName").textContent = document.querySelector('input[name="child_lastName"]').value;
                        document.getElementById("reviewMiddleName").textContent = document.querySelector('input[name="child_middleName"]').value;
                        document.getElementById("reviewGender").textContent = document.querySelector('select[name="child_gender"]').value;
                        document.getElementById("reviewBirthday").textContent = document.querySelector('input[name="child_birthday"]').value;
                        document.getElementById("reviewAddress").textContent = document.querySelector('input[name="child_address"]').value;
                        document.getElementById("reviewEmail").textContent = document.querySelector('input[name="guardian_email"]').value + " (Guardian)";
                        document.getElementById("reviewPhone").textContent = document.querySelector('input[name="guardian_phone"]').value + " (Guardian)";
                        
                        guardianInfo.style.display = "block";
                        document.getElementById("reviewGuardianName").textContent = document.querySelector('input[name="guardian_fullname"]').value;
                        document.getElementById("reviewGuardianRelationship").textContent = document.querySelector('select[name="guardian_relationship"]').value;
                        
                        userIdLabel.textContent = "Child's ID/Birth Certificate";
                        
                        const childIdInput = document.querySelector('input[name="child_validID"]');
                        if (childIdInput && childIdInput.files.length > 0) {
                            document.getElementById("idFrontPreview").src = URL.createObjectURL(childIdInput.files[0]);
                            document.getElementById("idFrontPreview").style.display = "block";
                        }
                        
                        const guardianIdInput = document.querySelector('input[name="guardian_validID"]');
                        if (guardianIdInput && guardianIdInput.files.length > 0) {
                            guardianIdPreview.style.display = "block";
                            document.getElementById("guardianIdImg").src = URL.createObjectURL(guardianIdInput.files[0]);
                            document.getElementById("guardianIdImg").style.display = "block";
                        }
                        break;
                        
                    case 'senior':
                        document.getElementById("reviewFirstName").textContent = document.querySelector('input[name="senior_firstName"]').value;
                        document.getElementById("reviewLastName").textContent = document.querySelector('input[name="senior_lastName"]').value;
                        document.getElementById("reviewMiddleName").textContent = document.querySelector('input[name="senior_middleName"]').value;
                        document.getElementById("reviewGender").textContent = document.querySelector('select[name="senior_gender"]').value;
                        document.getElementById("reviewBirthday").textContent = document.querySelector('input[name="senior_birthday"]').value;
                        document.getElementById("reviewAddress").textContent = document.querySelector('input[name="senior_address"]').value;
                        document.getElementById("reviewEmail").textContent = document.querySelector('input[name="senior_guardian_email"]').value + " (Guardian)";
                        document.getElementById("reviewPhone").textContent = document.querySelector('input[name="senior_guardian_phone"]').value + " (Guardian)";
                        
                        guardianInfo.style.display = "block";
                        document.getElementById("reviewGuardianName").textContent = document.querySelector('input[name="senior_guardian_fullname"]').value;
                        document.getElementById("reviewGuardianRelationship").textContent = document.querySelector('select[name="senior_guardian_relationship"]').value;
                        
                        userIdLabel.textContent = "Senior's Valid ID";
                        
                        const seniorIdInput = document.querySelector('input[name="senior_validID"]');
                        if (seniorIdInput && seniorIdInput.files.length > 0) {
                            document.getElementById("idFrontPreview").src = URL.createObjectURL(seniorIdInput.files[0]);
                            document.getElementById("idFrontPreview").style.display = "block";
                        }
                        
                        const seniorGuardianIdInput = document.querySelector('input[name="senior_guardian_validID"]');
                        if (seniorGuardianIdInput && seniorGuardianIdInput.files.length > 0) {
                            guardianIdPreview.style.display = "block";
                            document.getElementById("guardianIdImg").src = URL.createObjectURL(seniorGuardianIdInput.files[0]);
                            document.getElementById("guardianIdImg").style.display = "block";
                        }
                        break;
                }

                const familyNumber = getFamilyNumber(); 
                const familyNumberContainer = document.getElementById("reviewFamilyNumberContainer");
                const reviewFamilyNumber = document.getElementById("reviewFamilyNumber");
                
                if (familyNumber && familyNumber.trim()) {
                    familyNumberContainer.style.display = "block";
                    reviewFamilyNumber.textContent = familyNumber;
                } else {
                    familyNumberContainer.style.display = "none";
                }
            }

            function getFamilyNumber() {
                const registrationType = document.querySelector('input[name="registration_type"]').value;
                switch (registrationType) {
                    case 'personal':
                        return document.querySelector('input[name="family_number"]').value;
                    case 'child':
                        return document.querySelector('input[name="child_family_number"]').value;
                    case 'senior':
                        return document.querySelector('input[name="senior_family_number"]').value;
                    default:
                        return '';
                }
            }
            
            // Next button handlers
            nextBtns.forEach((btn) => {
                btn.addEventListener("click", async function () {
                    let isValid = validateStep(currentStep);

                    if (isValid && currentStep < steps.length - 1) {
                        currentStep++;
                        updateStep(currentStep);
                    }
                });
            });

            // Previous button handlers
            prevBtns.forEach((btn) => {
                btn.addEventListener("click", function () {
                    if (currentStep > 0) {
                        currentStep--;
                        updateStep(currentStep);
                    }
                });
            });

            // Terms checkbox handler
            const termsCheckbox = document.getElementById("termsCheckbox");
            const signUpBtn = document.getElementById("signUpBtn");

            if (termsCheckbox && signUpBtn) {
                termsCheckbox.addEventListener("change", function () {
                    signUpBtn.disabled = !this.checked;
                });
            }

            // Initialize form
            updateStep(currentStep);
        });

        // Modal functions
        function openModal(id) {
            let modal = document.getElementById(id);
            modal.classList.add("show");
        }

        function closeModal(id) {
            let modal = document.getElementById(id);
            modal.classList.remove("show");
        }

        // Legacy function for personal registration file upload
        function updateFileName() {
            const fileInput = document.getElementById('file-upload');
            const fileNameSpan = document.getElementById('file-name');
            
            if (fileInput.files.length > 0) {
                fileNameSpan.textContent = fileInput.files[0].name;
            } else {
                fileNameSpan.textContent = 'No file chosen';
            }
        }

        const registrationSuccess = <?= $registrationSuccess ? 'true' : 'false' ?>;
        if (registrationSuccess) {
            document.getElementById("successModal").style.display = "flex";
        }

        document.getElementById("goToLoginBtn").addEventListener("click", function () {
            window.location.href = "login.php";
        });
    </script>

</body>
</html>