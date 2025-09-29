<?php
// register.php
include 'config.php';

$registrationSuccess = false;
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get registration type
        $registrationType = filter_input(INPUT_POST, 'registration_type', FILTER_SANITIZE_STRING) ?: 'personal';
        
        // Initialize common variables
        $firstName = $lastName = $middleName = $gender = $birthday = $address = '';
        $email = $phone = $password = $confirmPassword = '';
        $familyNumber = null;
        $guardianData = null;
        $validIdFiles = [];

        // Define field prefixes based on type
        $prefix = ($registrationType === 'child') ? 'child_' : (($registrationType === 'senior') ? 'senior_' : '');
        $guardianPrefix = ($registrationType === 'senior') ? 'senior_guardian_' : 'guardian_';

        // Fetch common user data
        $familyNumber = filter_input(INPUT_POST, $prefix . 'family_number', FILTER_SANITIZE_STRING);
        $firstName = filter_input(INPUT_POST, $prefix . 'firstName', FILTER_SANITIZE_STRING);
        $lastName = filter_input(INPUT_POST, $prefix . 'lastName', FILTER_SANITIZE_STRING);
        $middleName = filter_input(INPUT_POST, $prefix . 'middleName', FILTER_SANITIZE_STRING);
        $gender = filter_input(INPUT_POST, $prefix . 'gender', FILTER_SANITIZE_STRING);
        $birthday = filter_input(INPUT_POST, $prefix . 'birthday', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, $prefix . 'address', FILTER_SANITIZE_STRING);

        // Handle type-specific data
        if ($registrationType === 'personal') {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
            $validIdFiles['user'] = $_FILES["validID_front"] ?? null;
        } else {
            // Guardian data for child/senior
            $guardianData = [
                'fullname' => filter_input(INPUT_POST, $guardianPrefix . 'fullname', FILTER_SANITIZE_STRING),
                'relationship' => filter_input(INPUT_POST, $guardianPrefix . 'relationship', FILTER_SANITIZE_STRING),
                'phone' => filter_input(INPUT_POST, $guardianPrefix . 'phone', FILTER_SANITIZE_STRING),
                'email' => filter_input(INPUT_POST, $guardianPrefix . 'email', FILTER_SANITIZE_EMAIL)
            ];
            $email = $guardianData['email'];
            $phone = $guardianData['phone'];
            $validIdFiles['user'] = $_FILES[$prefix . "validID"] ?? null;
            $validIdFiles['guardian'] = $_FILES[$guardianPrefix . "validID"] ?? null;
        }

        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        // Validate required common fields
        if (empty($firstName)) $errors[] = "First name is required";
        if (empty($lastName)) $errors[] = "Last name is required";
        if (empty($gender)) $errors[] = "Gender is required";
        if (empty($birthday)) $errors[] = "Date of birth is required";
        if (empty($address)) $errors[] = "Address is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($phone)) $errors[] = "Phone number is required";
        if (empty($password)) $errors[] = "Password is required";
        if (empty($confirmPassword)) $errors[] = "Password confirmation is required";
        
        // Validate guardian data if applicable
        if ($guardianData) {
            if (empty($guardianData['fullname'])) $errors[] = "Guardian full name is required";
            if (empty($guardianData['relationship'])) $errors[] = "Guardian relationship is required";
            if (empty($guardianData['phone'])) $errors[] = "Guardian phone number is required";
            if (empty($guardianData['email'])) $errors[] = "Guardian email is required";
        }
        
        // Validate formats
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (!empty($phone) && !preg_match('/^(\+63|0)[9][0-9]{9}$/', $phone)) {
            $errors[] = "Invalid phone number format";
        }
        
        if (!empty($password) && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, and numbers";
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }
        
        // Validate age
        if (!empty($birthday)) {
            $birthDate = new DateTime($birthday);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            if ($registrationType === 'personal' && $age < 18) {
                $errors[] = "You must be at least 18 years old to register";
            } elseif ($registrationType === 'child' && $age >= 18) {
                $errors[] = "Child registration is for individuals under 18 years old";
            } elseif ($registrationType === 'senior' && $age < 60) {
                $errors[] = "Senior registration is for individuals 60 years old and above";
            }
        }
        
        // File validation constants
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate user ID
        if (!isset($validIdFiles['user']) || $validIdFiles['user']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Valid ID is required";
        } elseif (!in_array($validIdFiles['user']['type'], $allowedTypes)) {
            $errors[] = "Invalid file type for ID. Please upload JPEG or PNG images only";
        } elseif ($validIdFiles['user']['size'] > $maxSize) {
            $errors[] = "ID file size exceeds the 5MB limit";
        }
        
        // Validate guardian ID if applicable
        if ($guardianData) {
            if (!isset($validIdFiles['guardian']) || $validIdFiles['guardian']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Guardian's valid ID is required";
            } elseif (!in_array($validIdFiles['guardian']['type'], $allowedTypes)) {
                $errors[] = "Invalid file type for guardian ID. Please upload JPEG or PNG images only";
            } elseif ($validIdFiles['guardian']['size'] > $maxSize) {
                $errors[] = "Guardian ID file size exceeds the 5MB limit";
            }
        }
        
        // Proceed if no errors
        if (empty($errors)) {
            // Check for existing email/phone
            $stmt = $conn->prepare("
                SELECT 'users' as source FROM users WHERE email = :email OR phone_number = :phone
                UNION
                SELECT 'pending_users' as source FROM pending_users WHERE email = :email OR phone_number = :phone
            ");
            $stmt->execute([':email' => $email, ':phone' => $phone]);
            
            if ($stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $errors[] = ($result['source'] === 'users') 
                    ? "Email or phone number is already registered with an active account"
                    : "Email or phone number is already pending approval";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
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
                    // Upload guardian ID if applicable
                    if ($guardianData) {
                        $guardianFileExtension = pathinfo($validIdFiles['guardian']['name'], PATHINFO_EXTENSION);
                        $guardianFileName = uniqid('guardian_id_') . '.' . $guardianFileExtension;
                        $guardianIdPath = $uploadDir . $guardianFileName;
                        
                        if (!move_uploaded_file($validIdFiles['guardian']['tmp_name'], $guardianIdPath)) {
                            $errors[] = "Error uploading guardian ID file. Please try again.";
                            unlink($userIdPath);
                        }
                    }
                    
                    if (empty($errors)) {
                        $conn->beginTransaction();
                        
                        try {
                            // Determine age category
                            $ageCategory = 'adult';
                            if (!empty($birthday)) {
                                $birthDate = new DateTime($birthday);
                                $today = new DateTime();
                                $age = $today->diff($birthDate)->y;
                                $ageCategory = ($age < 18) ? 'child' : (($age >= 60) ? 'senior' : 'adult');
                            }
                            
                            // Insert pending user
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
                            
                            // Insert guardian if applicable
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
                            
                            $conn->commit();
                            $registrationSuccess = true;
                            
                        } catch (Exception $e) {
                            $conn->rollback();
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

<!DOCTYPE html>
<html lang="en">
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
                <form method="POST" class="register-form" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="registration_type" value="personal">
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
                        <div id="personalFields">
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
                                    <input id="file-upload" type="file" name="validID_front" accept="image/jpeg,image/png" required>
                                    <span id="file-name">No file chosen</span>
                                </div>
                                <small class="field-hint">Max file size: 5MB. Accepted formats: JPEG, PNG</small>
                            </div>
                            <div class="button-container">
                                <button type="button" class="next-step">Next</button>
                            </div>
                        </div>
                        <!-- Child -->
                        <div id="childFields" class="hidden">
                            <h3>I. Child Information</h3>
                            <div class="group-col">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="child_lastName" autocomplete="off">
                            </div>
                            <div class="group-col">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="child_firstName" autocomplete="off">
                            </div>
                            <div class="group-col">
                                <label>Middle Name</label>
                                <input type="text" name="child_middleName" autocomplete="off">
                            </div>
                            <div class="group-row">
                                <div class="group-col">
                                    <label>Gender <span class="required">*</span></label>
                                    <select name="child_gender">
                                        <option value="" disabled selected>Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="group-col">
                                    <label>Date of Birth <span class="required">*</span></label>
                                    <input type="date" name="child_birthday">
                                    <small class="field-hint">Must be under 18 years old</small>
                                </div>    
                            </div>   
                            <div class="group-col">
                                <label>Address <span class="required">*</span></label>
                                <input type="text" name="child_address" autocomplete="off">
                            </div>
                            <div class="group-col">
                                <label>Family Number (Optional)</label>
                                <input type="text" name="child_family_number" placeholder="Enter family number if applicable" autocomplete="off">
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
                                <input type="text" name="guardian_fullname" autocomplete="off">
                            </div>
                            <div class="group-col">
                                <label>Relationship <span class="required">*</span></label>
                                <select name="guardian_relationship">
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
                                <input type="tel" name="guardian_phone" autocomplete="off">
                                <small class="field-hint">Format: +639XXXXXXXXX or 09XXXXXXXXX</small>
                            </div>
                            <div class="group-col">
                                <label>Email <span class="required">*</span></label>
                                <input type="email" name="guardian_email" autocomplete="off">
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
                        <!-- Senior -->
                        <div id="seniorFields" class="hidden">
                            <h3>I. Senior Information</h3>
                            <div class="group-col">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="senior_lastName" autocomplete="off">
                            </div>
                            <div class="group-col">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="senior_firstName" autocomplete="off">
                            </div>
                            <div class="group-col">
                                <label>Middle Name</label>
                                <input type="text" name="senior_middleName" autocomplete="off">
                            </div>
                            <div class="group-row">
                                <div class="group-col">
                                    <label>Gender <span class="required">*</span></label>
                                    <select name="senior_gender">
                                        <option value="" disabled selected>Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="group-col">
                                    <label>Date of Birth <span class="required">*</span></label>
                                    <input type="date" name="senior_birthday">
                                    <small class="field-hint">Must be 60+ years old</small>
                                </div>    
                            </div>   
                            <div class="group-col">
                                <label>Address <span class="required">*</span></label>
                                <input type="text" name="senior_address" autocomplete="off">
                            </div>
                            <div class="group-col">
                                <label>Family Number (Optional)</label>
                                <input type="text" name="senior_family_number" placeholder="Enter family number if applicable" autocomplete="off">
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
                                <input type="text" name="senior_guardian_fullname" autocomplete="off">
                            </div>
                            <div class="group-col">
                                <label>Relationship <span class="required">*</span></label>
                                <select name="senior_guardian_relationship">
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
                                <input type="tel" name="senior_guardian_phone" autocomplete="off">
                                <small class="field-hint">Format: +639XXXXXXXXX or 09XXXXXXXXX</small>
                            </div>
                            <div class="group-col">
                                <label>Email <span class="required">*</span></label>
                                <input type="email" name="senior_guardian_email" autocomplete="off">
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
                        <div id="guardianCredentials" class="hidden">
                            <h4>Guardian Account Credentials</h4>
                            <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                                The guardian's email and phone will be used as the account login credentials.
                            </p>
                        </div>
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
                            <h4 id="contactInfoHeader">Contact Information</h4>
                            <div class="group-col">
                                <label>E-mail Address</label>
                                <span id="reviewEmail"></span>
                            </div>
                            <div class="group-col">
                                <label>Phone Number</label>
                                <span id="reviewPhone"></span>
                            </div>
                            <div id="guardianInfo" class="hidden">
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
                            <div id="guardianIdPreview" class="hidden">
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
    <div id="successModal" class="success-modal" style="display: <?= $registrationSuccess ? 'flex' : 'none' ?>;">
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
                        <li><strong>Information We Collect</strong>
                            <ul>
                                <li>Personal Information: Name, sex, birth date, civil status, occupation, contact number, email address and 1 valid ID for proof that you are a resident of Barangay Marulas.</li>
                                <li>Medical Information: Consultation history, family folder number and family members.</li>
                                <li>Usage Data: System access logs, device information, and interactions with the platform.</li>
                            </ul>
                        </li>
                        <li><strong>How Do We Use Your Information</strong>
                            <ul>
                                <li>Providing and improving health services.</li>
                                <li>Sending notifications regarding health center updates and medicine availability.</li>
                                <li>Ensuring security and proper system functionality.</li>
                                <li>Complying with legal and regulatory requirements.</li>
                            </ul>
                        </li>
                        <li><strong>Data Sharing and Security</strong>
                            <ul>
                                <li>We do not sell or share user data with third parties, except as required by law or for health service purposes.</li>
                                <li>Personal data is encrypted and stored securely to prevent unauthorized access.</li>
                                <li>Users are responsible for keeping their login credentials confidential.</li>
                            </ul>
                        </li>
                        <li><strong>User Rights</strong>
                            <ul>
                                <li>Access and review their personal information.</li>
                                <li>Request corrections to inaccurate data.</li>
                                <li>Request deletion of their data, subject to legal and operational requirements.</li>
                            </ul>
                        </li>
                        <li><strong>Contact Information</strong>
                            <p>For privacy-related concerns, please contact Barangay Marulas 3S Health Center.<br>
                            By using MaruHealth, you acknowledge and agree to this Privacy Policy.</p>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Condition Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('termsModal')">&times;</span>
            <div class="policy-terms-container">
                <div class="policy-content">
                    <h3 class="section-title">Terms & Conditions</h3>
                    <p class="intro">Welcome to MaruHealth, by accessing and using this system, you agree to comply with and be bound by the following terms and conditions. Please read them carefully.</p>
                    <ol>
                        <li><strong>Acceptance of Terms</strong>
                            <p>By using MaruHealth, you acknowledge that you have read, understood, and agreed to these terms. If you do not agree with any part of these terms, you must discontinue use of the system.</p>
                        </li>
                        <li><strong>User Registration & Responsibilities</strong>
                            <ul>
                                <li>Users must provide accurate and complete information during registration.</li>
                                <li>Each user is responsible for maintaining the confidentiality of their account credentials.</li>
                                <li>Unauthorized access or use of another user's account is strictly prohibited.</li>
                            </ul>
                        </li>
                        <li><strong>Services Provided</strong>
                            <p>MaruHealth offers the following features:</p>
                            <ul>
                                <li>Checking health center schedules, announcements and health-related events.</li>
                                <li>Online medicine request.</li>
                                <li>Receiving notifications regarding the status of the requested medicine.</li>
                            </ul>
                        </li>
                        <li><strong>Privacy and Data Protection</strong>
                            <ul>
                                <li>MaruHealth values user privacy and ensures that personal data is protected in accordance with applicable data protection laws.</li>
                                <li>User information will only be used for health services management purposes.</li>
                            </ul>
                        </li>
                        <li><strong>Acceptable Use</strong>
                            <p>Users agree to:</p>
                            <ul>
                                <li>Use MaruHealth only for lawful purposes.</li>
                                <li>Refrain from transmitting any malicious software, hacking attempts, or engaging in unauthorized access.</li>
                                <li>Not disrupt the operation of the system or compromise its security.</li>
                            </ul>
                        </li>
                        <li><strong>Limitation of Liability</strong>
                            <ul>
                                <li>MaruHealth and its administrators are not liable for any damages or losses incurred due to misuse, system downtimes, or incorrect information provided by users.</li>
                                <li>The system does not replace professional medical consultations.</li>
                            </ul>
                        </li>
                        <li><strong>Termination of Access</strong>
                            <p>MaruHealth may suspend or terminate user access if there is a violation of these terms. Any misuse or unauthorized activity may lead to account deactivation or legal action.</p>
                        </li>
                        <li><strong>Governing Law</strong>
                            <p>These terms are governed by the laws of the Philippines, and any disputes shall be resolved within the appropriate legal jurisdiction.</p>
                        </li>
                        <li><strong>Contact Information</strong>
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
            let currentStep = 0;
            const steps = document.querySelectorAll(".form-step");
            const nextBtns = document.querySelectorAll(".next-step");
            const prevBtns = document.querySelectorAll(".prev-step");
            const stepperItems = document.querySelectorAll(".stepper-item");
            const form = document.querySelector('.register-form');
            const registrationTypeInput = form.querySelector('input[name="registration_type"]');
            const categorySelect = document.getElementById("category");
            const personalFields = document.getElementById("personalFields");
            const childFields = document.getElementById("childFields");
            const seniorFields = document.getElementById("seniorFields");
            const personalCredentials = document.getElementById("personalCredentials");
            const guardianCredentials = document.getElementById("guardianCredentials");
            const termsCheckbox = document.getElementById("termsCheckbox");
            const signUpBtn = document.getElementById("signUpBtn");

            // Category change handler
            categorySelect.addEventListener("change", function() {
                registrationTypeInput.value = this.value;
                personalFields.classList.toggle("hidden", this.value !== "personal");
                childFields.classList.toggle("hidden", this.value !== "child");
                seniorFields.classList.toggle("hidden", this.value !== "senior");
                updateStep2Display();
            });

            // File upload setup
            const fileUploads = [
                {input: 'file-upload', span: 'file-name'},
                {input: 'child-file-upload', span: 'child-file-name'},
                {input: 'guardian-file-upload', span: 'guardian-file-name'},
                {input: 'senior-file-upload', span: 'senior-file-name'},
                {input: 'senior-guardian-file-upload', span: 'senior-guardian-file-name'}
            ];

            fileUploads.forEach(({input, span}) => {
                const fileInput = document.getElementById(input);
                const fileNameSpan = document.getElementById(span);
                if (fileInput && fileNameSpan) {
                    fileInput.addEventListener('change', function () {
                        if (this.files.length > 0) {
                            const file = this.files[0];
                            const validImageTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                            const maxFileSize = 5 * 1024 * 1024;
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
                            fileNameSpan.textContent = file.name;
                        } else {
                            fileNameSpan.textContent = 'No file chosen';
                        }
                    });
                }
            });

            // Form fields for validation
            const formFields = {
                firstName: { element: form.querySelector('input[name="firstName"]'), errorMsg: "First name is required", validator: v => v.trim().length > 0 },
                lastName: { element: form.querySelector('input[name="lastName"]'), errorMsg: "Last name is required", validator: v => v.trim().length > 0 },
                gender: { element: form.querySelector('select[name="gender"]'), errorMsg: "Please select a gender", validator: v => v !== "" },
                birthday: { element: form.querySelector('input[name="birthday"]'), validator: v => {
                    if (!v) return false;
                    const age = calculateAge(v);
                    return age >= 18;
                }, customErrorMsg: v => !v ? "Date of birth is required" : (calculateAge(v) < 18 ? "You must be at least 18 years old to register" : "") },
                address: { element: form.querySelector('input[name="address"]'), errorMsg: "Address is required", validator: v => v.trim().length > 0 },
                phone: { element: form.querySelector('input[name="phone"]'), validator: v => /^(\+63|0)[9][0-9]{9}$/.test(v.trim()), customErrorMsg: v => !v.trim() ? "Phone number is required" : "Please enter a valid Philippines phone number" },
                email: { element: form.querySelector('input[name="email"]'), validator: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()), customErrorMsg: v => !v.trim() ? "Email is required" : "Please enter a valid email address" },
                password: { element: form.querySelector('input[name="password"]'), validator: v => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(v), customErrorMsg: v => !v ? "Password is required" : "Password must be at least 8 characters and include uppercase, lowercase, and numbers" },
                confirmPassword: { element: form.querySelector('input[name="confirmPassword"]'), validator: v => v === form.querySelector('input[name="password"]').value && v.length > 0, customErrorMsg: v => !v ? "Please confirm your password" : "Passwords do not match" },
                // Child
                child_firstName: { element: form.querySelector('input[name="child_firstName"]'), errorMsg: "First name is required", validator: v => v.trim().length > 0 },
                child_lastName: { element: form.querySelector('input[name="child_lastName"]'), errorMsg: "Last name is required", validator: v => v.trim().length > 0 },
                child_gender: { element: form.querySelector('select[name="child_gender"]'), errorMsg: "Please select a gender", validator: v => v !== "" },
                child_birthday: { element: form.querySelector('input[name="child_birthday"]'), validator: v => {
                    if (!v) return false;
                    const age = calculateAge(v);
                    return age < 18;
                }, customErrorMsg: v => !v ? "Date of birth is required" : (calculateAge(v) >= 18 ? "Child registration is for individuals under 18 years old" : "") },
                child_address: { element: form.querySelector('input[name="child_address"]'), errorMsg: "Address is required", validator: v => v.trim().length > 0 },
                guardian_fullname: { element: form.querySelector('input[name="guardian_fullname"]'), errorMsg: "Guardian full name is required", validator: v => v.trim().length > 0 },
                guardian_relationship: { element: form.querySelector('select[name="guardian_relationship"]'), errorMsg: "Guardian relationship is required", validator: v => v !== "" },
                guardian_phone: { element: form.querySelector('input[name="guardian_phone"]'), validator: v => /^(\+63|0)[9][0-9]{9}$/.test(v.trim()), customErrorMsg: v => !v.trim() ? "Guardian phone number is required" : "Please enter a valid Philippines phone number" },
                guardian_email: { element: form.querySelector('input[name="guardian_email"]'), validator: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()), customErrorMsg: v => !v.trim() ? "Guardian email is required" : "Please enter a valid email address" },
                // Senior
                senior_firstName: { element: form.querySelector('input[name="senior_firstName"]'), errorMsg: "First name is required", validator: v => v.trim().length > 0 },
                senior_lastName: { element: form.querySelector('input[name="senior_lastName"]'), errorMsg: "Last name is required", validator: v => v.trim().length > 0 },
                senior_gender: { element: form.querySelector('select[name="senior_gender"]'), errorMsg: "Please select a gender", validator: v => v !== "" },
                senior_birthday: { element: form.querySelector('input[name="senior_birthday"]'), validator: v => {
                    if (!v) return false;
                    const age = calculateAge(v);
                    return age >= 60;
                }, customErrorMsg: v => !v ? "Date of birth is required" : (calculateAge(v) < 60 ? "Senior registration is for individuals 60 years old and above" : "") },
                senior_address: { element: form.querySelector('input[name="senior_address"]'), errorMsg: "Address is required", validator: v => v.trim().length > 0 },
                senior_guardian_fullname: { element: form.querySelector('input[name="senior_guardian_fullname"]'), errorMsg: "Guardian full name is required", validator: v => v.trim().length > 0 },
                senior_guardian_relationship: { element: form.querySelector('select[name="senior_guardian_relationship"]'), errorMsg: "Guardian relationship is required", validator: v => v !== "" },
                senior_guardian_phone: { element: form.querySelector('input[name="senior_guardian_phone"]'), validator: v => /^(\+63|0)[9][0-9]{9}$/.test(v.trim()), customErrorMsg: v => !v.trim() ? "Guardian phone number is required" : "Please enter a valid Philippines phone number" },
                senior_guardian_email: { element: form.querySelector('input[name="senior_guardian_email"]'), validator: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()), customErrorMsg: v => !v.trim() ? "Guardian email is required" : "Please enter a valid email address" }
            };

            function calculateAge(birthday) {
                const today = new Date();
                const birthDate = new Date(birthday);
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;
                return age;
            }

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
                if (existingError) existingError.remove();
                element.style.borderColor = "";
            }

            function validateStep(step) {
                let isValid = true;
                const currentStepElement = steps[step];
                const registrationType = registrationTypeInput.value;
                const inputs = currentStepElement.querySelectorAll("input:not([type='hidden']), select");
                inputs.forEach(input => removeFieldError(input));
                inputs.forEach(input => {
                    const fieldName = input.name;
                    if (!formFields[fieldName]) return;
                    const prefixCheck = {
                        personal: !fieldName.startsWith('child_') && !fieldName.startsWith('senior_') && !fieldName.startsWith('guardian_') && !fieldName.startsWith('senior_guardian_'),
                        child: fieldName.startsWith('child_') || fieldName.startsWith('guardian_') || fieldName === 'password' || fieldName === 'confirmPassword',
                        senior: fieldName.startsWith('senior_') || fieldName === 'password' || fieldName === 'confirmPassword'
                    };
                    if (!prefixCheck[registrationType]) return;
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

            function updateStep(step) {
                steps.forEach((s, i) => s.classList.toggle("active", i === step));
                stepperItems.forEach((item, index) => {
                    item.classList.remove("active", "completed");
                    if (index < step) item.classList.add("completed");
                    else if (index === step) item.classList.add("active");
                });
                if (step === 2) populateReviewStep();
            }

            function updateStep2Display() {
                const isPersonal = registrationTypeInput.value === 'personal';
                personalCredentials.classList.toggle("hidden", !isPersonal);
                guardianCredentials.classList.toggle("hidden", isPersonal);
            }

            function populateReviewStep() {
                const registrationType = registrationTypeInput.value;
                const guardianInfo = document.getElementById("guardianInfo");
                const guardianIdPreview = document.getElementById("guardianIdPreview");
                const userIdLabel = document.getElementById("userIdLabel");
                const reviewFamilyNumberContainer = document.getElementById("reviewFamilyNumberContainer");
                const reviewFamilyNumber = document.getElementById("reviewFamilyNumber");
                const familyNumber = getFamilyNumber();
                reviewFamilyNumberContainer.style.display = familyNumber.trim() ? "block" : "none";
                reviewFamilyNumber.textContent = familyNumber;
                const isGuardianType = registrationType !== 'personal';
                guardianInfo.classList.toggle("hidden", !isGuardianType);
                guardianIdPreview.classList.toggle("hidden", !isGuardianType);

                let fieldMap = {};
                if (registrationType === 'personal') {
                    fieldMap = {
                        firstName: 'firstName',
                        lastName: 'lastName',
                        middleName: 'middleName',
                        gender: 'gender',
                        birthday: 'birthday',
                        address: 'address',
                        email: 'email',
                        phone: 'phone',
                        userId: 'validID_front'
                    };
                    userIdLabel.textContent = "Valid ID";
                } else if (registrationType === 'child') {
                    fieldMap = {
                        firstName: 'child_firstName',
                        lastName: 'child_lastName',
                        middleName: 'child_middleName',
                        gender: 'child_gender',
                        birthday: 'child_birthday',
                        address: 'child_address',
                        email: 'guardian_email',
                        phone: 'guardian_phone',
                        guardianName: 'guardian_fullname',
                        guardianRelationship: 'guardian_relationship',
                        userId: 'child_validID',
                        guardianId: 'guardian_validID'
                    };
                    userIdLabel.textContent = "Child's ID/Birth Certificate";
                    document.getElementById("reviewEmail").textContent += " (Guardian)";
                    document.getElementById("reviewPhone").textContent += " (Guardian)";
                } else {
                    fieldMap = {
                        firstName: 'senior_firstName',
                        lastName: 'senior_lastName',
                        middleName: 'senior_middleName',
                        gender: 'senior_gender',
                        birthday: 'senior_birthday',
                        address: 'senior_address',
                        email: 'senior_guardian_email',
                        phone: 'senior_guardian_phone',
                        guardianName: 'senior_guardian_fullname',
                        guardianRelationship: 'senior_guardian_relationship',
                        userId: 'senior_validID',
                        guardianId: 'senior_guardian_validID'
                    };
                    userIdLabel.textContent = "Senior's Valid ID";
                    document.getElementById("reviewEmail").textContent += " (Guardian)";
                    document.getElementById("reviewPhone").textContent += " (Guardian)";
                }

                document.getElementById("reviewFirstName").textContent = form.querySelector(`[name="${fieldMap.firstName}"]`).value;
                document.getElementById("reviewLastName").textContent = form.querySelector(`[name="${fieldMap.lastName}"]`).value;
                document.getElementById("reviewMiddleName").textContent = form.querySelector(`[name="${fieldMap.middleName}"]`).value;
                document.getElementById("reviewGender").textContent = form.querySelector(`[name="${fieldMap.gender}"]`).value;
                document.getElementById("reviewBirthday").textContent = form.querySelector(`[name="${fieldMap.birthday}"]`).value;
                document.getElementById("reviewAddress").textContent = form.querySelector(`[name="${fieldMap.address}"]`).value;
                document.getElementById("reviewEmail").textContent = form.querySelector(`[name="${fieldMap.email}"]`).value;
                document.getElementById("reviewPhone").textContent = form.querySelector(`[name="${fieldMap.phone}"]`).value;

                if (isGuardianType) {
                    document.getElementById("reviewGuardianName").textContent = form.querySelector(`[name="${fieldMap.guardianName}"]`).value;
                    document.getElementById("reviewGuardianRelationship").textContent = form.querySelector(`[name="${fieldMap.guardianRelationship}"]`).value;
                }

                const userIdInput = form.querySelector(`[name="${fieldMap.userId}"]`);
                if (userIdInput?.files.length > 0) {
                    document.getElementById("idFrontPreview").src = URL.createObjectURL(userIdInput.files[0]);
                    document.getElementById("idFrontPreview").style.display = "block";
                }

                if (isGuardianType) {
                    const guardianIdInput = form.querySelector(`[name="${fieldMap.guardianId}"]`);
                    if (guardianIdInput?.files.length > 0) {
                        document.getElementById("guardianIdImg").src = URL.createObjectURL(guardianIdInput.files[0]);
                        document.getElementById("guardianIdImg").style.display = "block";
                    }
                }
            }

            function getFamilyNumber() {
                const registrationType = registrationTypeInput.value;
                const familyField = form.querySelector(`[name="${registrationType === 'personal' ? 'family_number' : registrationType + '_family_number'}"]`);
                return familyField ? familyField.value : '';
            }

            nextBtns.forEach(btn => btn.addEventListener("click", () => {
                if (validateStep(currentStep) && currentStep < steps.length - 1) {
                    currentStep++;
                    updateStep(currentStep);
                }
            }));

            prevBtns.forEach(btn => btn.addEventListener("click", () => {
                if (currentStep > 0) {
                    currentStep--;
                    updateStep(currentStep);
                }
            }));

            termsCheckbox.addEventListener("change", function () {
                signUpBtn.disabled = !this.checked;
            });

            document.getElementById("goToLoginBtn").addEventListener("click", () => window.location.href = "login.php");

            updateStep(currentStep);
            updateStep2Display();
        });

        function openModal(id) {
            document.getElementById(id).classList.add("show");
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove("show");
        }
    </script>
</body>
</html>