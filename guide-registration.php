<?php
// guide-registration.php
// Public page — no login required
// Guide role is set immediately; is_approved = 0 until admin approves

require_once __DIR__ . '/config/db.php';

$success = false;
$errors  = [];
function detectIDType($text) {
    $textUpper = strtoupper($text);
    
    if (strpos($textUpper, 'PHILSYS') !== false || strpos($textUpper, 'PAMBANSANG PAGKAKAKILANLAN') !== false || strpos($textUpper, 'PHILIPPINE IDENTIFICATION CARD') !== false) {
        return 'PhilSys National ID';
    }
    
    if (strpos($textUpper, 'UNIFIED MULTI-PURPOSE ID') !== false || strpos($textUpper, 'UMID') !== false || strpos($textUpper, 'CRN-') !== false) {
        return 'UMID';
    }
    
    if (strpos($textUpper, 'PERSON WITH DISABILITY') !== false || strpos($textUpper, 'PWD') !== false || strpos($textUpper, 'REPUBLIC ACTS 9442') !== false) {
        return 'PWD ID';
    }
    
    if (strpos($textUpper, "DRIVER'S LICENSE") !== false || strpos($textUpper, 'DRIVING LICENSE') !== false || strpos($textUpper, 'LTO') !== false) {
        return "Driver's License";
    }
    
    if (strpos($textUpper, 'PASSPORT') !== false || strpos($textUpper, 'REPUBLIC OF THE PHILIPPINES PASSPORT') !== false) {
        return 'Philippine Passport';
    }
    
    if (strpos($textUpper, 'SSS') !== false || strpos($textUpper, 'SOCIAL SECURITY SYSTEM') !== false) {
        return 'SSS ID';
    }
    
    if (strpos($textUpper, 'GSIS') !== false || strpos($textUpper, 'GOVERNMENT SERVICE INSURANCE SYSTEM') !== false) {
        return 'GSIS ID';
    }
    
    if (strpos($textUpper, 'PRC') !== false || strpos($textUpper, 'PROFESSIONAL REGULATION COMMISSION') !== false) {
        return 'PRC ID';
    }
    
    if (strpos($textUpper, "VOTER'S ID") !== false || strpos($textUpper, 'VOTER ID') !== false || strpos($textUpper, 'COMELEC') !== false) {
        return "Voter's ID";
    }
    
    if (strpos($textUpper, 'POSTAL ID') !== false || strpos($textUpper, 'PHILIPPINE POSTAL') !== false) {
        return 'Postal ID';
    }
    
    if (strpos($textUpper, 'SENIOR CITIZEN') !== false) {
        return 'Senior Citizen ID';
    }
    
    return null;
}

function extractNameFromID($imagePath) {
    // Try multiple PSM modes and take the best result
    $psmModes = [6, 4, 3, 8]; // 6=uniform block, 4=single column, 3=auto, 8=single word
    $allText = '';
    
    foreach ($psmModes as $psm) {
        $outputFile = tempnam(sys_get_temp_dir(), 'ocr_');
        exec("tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg($outputFile) . " -l eng --psm $psm 2>&1");
        if (file_exists($outputFile . '.txt')) {
            $text = file_get_contents($outputFile . '.txt');
            $allText .= "\n" . $text;
            unlink($outputFile . '.txt');
        }
    }
    
    // Try with image preprocessing if text is sparse
    if (strlen(trim($allText)) < 30) {
        $processedPath = tempnam(sys_get_temp_dir(), 'ocr_proc_') . '.png';
        $convertCmd = "convert " . escapeshellarg($imagePath) . " -colorspace Gray -contrast-stretch 5% -sharpen 0x1 -resize 200% " . escapeshellarg($processedPath) . " 2>&1";
        exec($convertCmd);
        
        if (file_exists($processedPath)) {
            $outputFile2 = tempnam(sys_get_temp_dir(), 'ocr_enh_');
            exec("tesseract " . escapeshellarg($processedPath) . " " . escapeshellarg($outputFile2) . " -l eng --psm 6 2>&1");
            if (file_exists($outputFile2 . '.txt')) {
                $allText .= "\n" . file_get_contents($outputFile2 . '.txt');
                unlink($outputFile2 . '.txt');
            }
            unlink($processedPath);
        }
    }
    
    // Get lines and clean
    $lines = preg_split('/\r\n|\r|\n/', $allText);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines, function($line) {
        return strlen($line) > 0;
    });
    
    // ============================================
    // STRATEGY 1: Look for label-value pairs
    // ============================================
    $lastName = '';
    $firstName = '';
    $middleName = '';
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = strtoupper($lines[$i]);
        
        // PhilSys: Apelyido/Last Name -> next line is last name
        if ((strpos($line, 'APELYIDO') !== false || strpos($line, 'LAST NAME') !== false) && isset($lines[$i + 1])) {
            $lastName = trim($lines[$i + 1]);
        }
        
        // PhilSys: Mga Pangalan/Given Names -> next line(s) are given names
        if ((strpos($line, 'MGA PANGALAN') !== false || strpos($line, 'GIVEN NAMES') !== false || strpos($line, 'PANGALAN') !== false) && isset($lines[$i + 1])) {
            $firstName = trim($lines[$i + 1]);
            // Check if there are multiple lines of given names
            if (isset($lines[$i + 2]) && !preg_match('/(GITNANG|MIDDLE|APELYIDO|LAST|SEX|KASARIAN|BIRTH|DATE)/i', $lines[$i + 2])) {
                $firstName .= ' ' . trim($lines[$i + 2]);
            }
        }
        
        // UMID: SURNAME, GIVEN NAME, MIDDLE NAME
        if (strpos($line, 'SURNAME') !== false && isset($lines[$i + 1])) {
            $lastName = trim($lines[$i + 1]);
        }
        if (strpos($line, 'GIVEN NAME') !== false && isset($lines[$i + 1])) {
            $firstName = trim($lines[$i + 1]);
        }
        if (strpos($line, 'MIDDLE NAME') !== false && isset($lines[$i + 1])) {
            $middleName = trim($lines[$i + 1]);
        }
        
        // PWD ID: "NAME" label followed by name line
        if ($line === 'NAME' && isset($lines[$i + 1])) {
            $candidate = trim($lines[$i + 1]);
            // Filter out non-name words
            if (!preg_match('/(PSYCHOSOCIAL|DISABILITY|SIGNATURE)/i', $candidate)) {
                $nameParts = explode(' ', $candidate);
                if (count($nameParts) >= 2) {
                    $firstName = $nameParts[0];
                    $lastName = $nameParts[count($nameParts) - 1];
                    if (count($nameParts) > 2) {
                        $middleName = implode(' ', array_slice($nameParts, 1, -1));
                    }
                }
            }
        }
    }
    
    // Build name from found parts
    if (!empty($lastName) && !empty($firstName)) {
        $fullName = ucwords(strtolower($firstName . ' ' . $lastName));
        if (!empty($middleName)) {
            $middleInitial = strtoupper(substr($middleName, 0, 1));
            $fullName = ucwords(strtolower($firstName . ' ' . $lastName . ' ' . $middleInitial . '.'));
        }
        if (strlen($fullName) > 5 && !preg_match('/(REPUBLIC|PHILIPPINES|CARD|ID)/i', $fullName)) {
            return ['name' => $fullName, 'id_type' => null];
        }
    }
    
    // ============================================
    // STRATEGY 2: Find all-caps lines with 2-3 words (likely a name)
    // ============================================
    $excludeWords = ['REPUBLIC', 'PHILIPPINES', 'PROVINCE', 'CITY', 'MUNICIPALITY', 'PAMBANSAG', 
                     'PAGKAKAKILANLAN', 'IDENTIFICATION', 'CARD', 'SURNAME', 'GIVEN', 'MIDDLE', 
                     'NAME', 'SEX', 'BIRTH', 'DATE', 'ADDRESS', 'SIGNATURE', 'DISABILITY', 'PSYCHOSOCIAL',
                     'NON-TRANFERABLE', 'VALID', 'VIOLATION', 'PUNISHABLE', 'BENEFITS', 'PRIVILEGES'];
    
    foreach ($lines as $line) {
        $lineUpper = strtoupper($line);
        // Check if it's all caps with 2-4 words
        if (preg_match('/^[A-Z]{2,}(?:\s+[A-Z]{2,}){1,3}$/', $lineUpper)) {
            $isValid = true;
            foreach ($excludeWords as $exclude) {
                if (strpos($lineUpper, $exclude) !== false) {
                    $isValid = false;
                    break;
                }
            }
            // Also check it's not just numbers
            if ($isValid && !preg_match('/^\d+$/', $lineUpper) && strlen($lineUpper) > 8) {
                $name = ucwords(strtolower($lineUpper));
                return ['name' => $name, 'id_type' => null];
            }
        }
    }
    
    // ============================================
    // STRATEGY 3: Look for common Filipino surname patterns
    // ============================================
    $commonSurnames = ['CRUZ', 'SANTOS', 'REYES', 'GARCIA', 'MENDOZA', 'DELA CRUZ', 'BAUTISTA', 
                       'GUEVARA', 'VERGEL', 'ROSARIO', 'HERNANDEZ', 'LIGON', 'PADILLA', 'SEBASTIAN', 
                       'CABRERA', 'ZAMORA', 'MORENO', 'ALONZO', 'NATIVIDAD', 'YU', 'CAYETANO', 
                       'VILLANUEVA', 'DALISAY', 'TORRES', 'RAMOS', 'FLORES'];
    
    foreach ($lines as $i => $line) {
        $lineUpper = strtoupper($line);
        foreach ($commonSurnames as $surname) {
            if (strpos($lineUpper, $surname) !== false) {
                // Found a surname! Try to get first name from previous line
                $surnameClean = ucwords(strtolower($surname));
                $firstNameClean = '';
                
                if ($i > 0 && !preg_match('/(' . implode('|', $excludeWords) . ')/i', $lines[$i - 1])) {
                    $firstNameClean = ucwords(strtolower(trim($lines[$i - 1])));
                    if (strlen($firstNameClean) < 20 && strpos($firstNameClean, ' ') === false) {
                        return ['name' => $firstNameClean . ' ' . $surnameClean, 'id_type' => null];
                    }
                }
                
                // If no first name found, just return the surname (better than nothing)
                if (strlen($surnameClean) > 3) {
                    return ['name' => $surnameClean, 'id_type' => null];
                }
            }
        }
    }
    
    return null;
}
// Handle OCR AJAX request with Tesseract
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['ocr_image']) || $_FILES['ocr_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No image uploaded']);
        exit;
    }
    
    $file = $_FILES['ocr_image'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Invalid image type']);
        exit;
    }
    
    $tempPath = $file['tmp_name'];
    $extractedName = extractNameFromID($tempPath);
    
    if ($extractedName && strlen($extractedName) > 3) {
    // Also detect ID type from the raw text
    $detectedIdType = detectIDType($allText);
    echo json_encode([
        'success' => true, 
        'name' => $extractedName,
        'detected_id_type' => $detectedIdType
    ]);
} else {
        // If still failing, return the raw text for debugging
        $debugFile = tempnam(sys_get_temp_dir(), 'ocr_debug_');
        exec("tesseract " . escapeshellarg($tempPath) . " " . escapeshellarg($debugFile) . " -l eng 2>&1");
        $rawText = '';
        if (file_exists($debugFile . '.txt')) {
            $rawText = file_get_contents($debugFile . '.txt');
            unlink($debugFile . '.txt');
        }
        echo json_encode([
            'success' => false, 
            'error' => 'Could not extract name. Please ensure the name is clearly visible and type it manually.',
            'debug' => $rawText // Only for testing, remove in production
        ]);
    }
    exit;
}

// Regular POST handling (same as before)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name']         ?? '');
    $email            = trim($_POST['email']             ?? '');
    $phone            = trim($_POST['phone']             ?? '');
    $username         = trim($_POST['username']          ?? '');
    $password         = $_POST['password']               ?? '';
    $confirm_password = $_POST['confirm_password']       ?? '';
    $home_region      = trim($_POST['home_region']       ?? '');
    $specialization   = trim($_POST['specialization']    ?? '');
    $years_experience = (int)($_POST['years_experience'] ?? 0);
    $bio              = trim($_POST['bio']               ?? '');
    $id_type          = trim($_POST['id_type']           ?? '');
    $id_extracted_name = trim($_POST['id_extracted_name'] ?? '');

    if (empty($full_name) && !empty($id_extracted_name)) {
        $full_name = $id_extracted_name;
    }

    if (empty($full_name))                          $errors[] = "Full name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email address is required.";
    if (empty($phone))                              $errors[] = "Phone number is required.";
    if (empty($username))                           $errors[] = "Username is required.";
    if (strlen($password) < 8)                      $errors[] = "Password must be at least 8 characters.";
    if ($password !== $confirm_password)            $errors[] = "Passwords do not match.";
    if (empty($specialization))                     $errors[] = "Specialization is required.";
    if ($years_experience < 0)                      $errors[] = "Invalid years of experience.";
    if (empty($bio))                                $errors[] = "A short bio is required.";
    if (empty($id_type))                            $errors[] = "Please select your ID type.";

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $chk->execute([$email, $username]);
        if ($chk->fetch()) $errors[] = "An account with this email or username already exists.";
    }

   // No directory creation needed! We'll store image as base64 in database
    $id_img_path = '';      // Keep empty - we won't store file path
    $id_img_base64 = '';    // This will hold the base64 image

    if (empty($errors)) {
        if (isset($_FILES['gov_id']) && $_FILES['gov_id']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $_FILES['gov_id']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime, $allowed)) {
                $errors[] = "ID must be a JPG, PNG, or WEBP image.";
            } elseif ($_FILES['gov_id']['size'] > 5 * 1024 * 1024) {
                $errors[] = "ID image must be under 5 MB.";
            } else {
                // Read the file and convert to base64
                $imageData = file_get_contents($_FILES['gov_id']['tmp_name']);
                $id_img_base64 = 'data:' . $mime . ';base64,' . base64_encode($imageData);
                $id_img_path = ''; // No file path needed
            }
        } else {
            $errors[] = "A government-issued ID photo is required.";
        }
    }
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $uStmt = $pdo->prepare("
                INSERT INTO users
                    (name, email, username, password, role, phone, home_region, created_at)
                VALUES (?, ?, ?, ?, 'guide', ?, ?, NOW())
            ");
            $uStmt->execute([$full_name, $email, $username, $hashed, $phone, $home_region]);
            $user_id = $pdo->lastInsertId();

            $gStmt = $pdo->prepare("
    INSERT INTO guides
        (user_id, specialization, years_experience, rating, total_trips,
         is_available, bio, trail_status, currently_on_hike,
         id_type, id_image, id_image_base64, is_approved, submitted_at)
    VALUES (?, ?, ?, 0.0, 0, 0, ?, 'safe', 0, ?, ?, ?, 0, NOW())
");
$gStmt->execute([$user_id, $specialization, $years_experience, $bio, $id_type, $id_img_path, $id_img_base64]);

            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed — please try again.";
        }
    }
}

$id_types = [
    "Driver's License", "Philippine Passport", "PhilSys National ID", "SSS ID",
    "GSIS ID", "PRC ID", "Voter's ID", "Postal ID", "Senior Citizen ID",
    "PWD ID", "OFW ID / iDOLE Card", "Barangay Clearance with Photo",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
  
    <title>Register as a Guide — LAKBAY</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@300;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --forest: #1a2e1a; --pine: #2c5e2c; --moss: #4a7c4a;
            --gold: #c6a43b; --gold-lt: #e0c268; --cream: #faf8f2;
            --stone: #5a5e5a; --mist: #e8eae6; --white: #ffffff;
            --radius: 20px; --rsm: 12px; --shadow: 0 8px 32px rgba(26,46,26,.10);
            --tr: all 0.28s cubic-bezier(.2,.9,.4,1.05);
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--forest); }
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            padding: 16px 36px; background: rgba(250,248,242,.93);
            backdrop-filter: blur(20px); display: flex; justify-content: space-between;
        }
        .logo { font-family: 'Fraunces', serif; font-size: 22px; font-weight: 700; color: var(--forest); text-decoration: none; }
        .page-hero {
            margin-top: 68px; background: var(--forest); padding: 54px 40px 50px;
            text-align: center; position: relative;
        }
        .page-hero h1 { font-family: 'Fraunces', serif; font-size: clamp(28px, 5vw, 46px); color: var(--white); }
        .page-body { max-width: 820px; margin: 0 auto; padding: 44px 24px 80px; }
        .form-card {
            background: var(--white); border-radius: var(--radius);
            box-shadow: var(--shadow); border: 1px solid var(--mist);
            overflow: hidden; margin-bottom: 22px;
        }
        .card-header { padding: 20px 26px 16px; border-bottom: 1px solid var(--mist); display: flex; gap: 12px; }
        .card-header h3 { font-size: 15px; font-weight: 700; }
        .card-body { padding: 24px 26px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        label { font-size: 13px; font-weight: 600; color: var(--forest); }
        label .req { color: #c44; margin-left: 3px; }
        input, select, textarea {
            width: 100%; padding: 11px 14px; border: 1.5px solid var(--mist);
            border-radius: var(--rsm); font-size: 14px; background: var(--cream);
            transition: var(--tr); outline: none;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--moss); }
        .ocr-zone-top {
            background: linear-gradient(135deg, rgba(198,164,59,.08), rgba(74,124,74,.08));
            border: 2px solid var(--gold); border-radius: var(--radius);
            padding: 28px 24px; margin-bottom: 28px;
        }
        .drop-zone {
            border: 2px dashed var(--moss); border-radius: var(--rsm);
            padding: 30px 20px; text-align: center; cursor: pointer;
            background: var(--white); transition: var(--tr);
        }
        .drop-zone:hover { border-color: var(--gold); background: rgba(198,164,59,.04); }
        .drop-zone.has-file { border-color: var(--pine); border-style: solid; }
        .ocr-status {
            display: none; align-items: center; gap: 10px; margin-top: 12px;
            padding: 11px 15px; border-radius: var(--rsm); font-size: 13px;
        }
        .ocr-status.loading { display: flex; background: rgba(198,164,59,.12); color: #7a6020; }
        .ocr-status.done { display: flex; background: rgba(74,124,74,.12); color: var(--pine); }
        .ocr-status.error { display: flex; background: #fff5f5; color: #c44; }
        .spinner {
            width: 16px; height: 16px; border: 2px solid rgba(198,164,59,.3);
            border-top-color: var(--gold); border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .id-type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 8px; }
        .id-option { display: none; }
        .id-label {
            display: flex; align-items: center; gap: 8px; padding: 10px 13px;
            border: 1.5px solid var(--mist); border-radius: 10px;
            font-size: 12px; cursor: pointer; background: var(--cream);
        }
        .id-option:checked + .id-label { border-color: var(--pine); background: rgba(44,94,44,.07); color: var(--forest); }
        .btn-submit {
            width: 100%; padding: 15px; background: var(--forest); color: var(--white);
            border: none; border-radius: 50px; font-weight: 700; cursor: pointer;
            transition: var(--tr);
        }
        .btn-submit:hover { background: var(--pine); transform: translateY(-2px); }
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .card-header, .card-body { padding: 16px 18px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="logo">LAKBAY</a>
    <a href="index.php" style="color: var(--stone); text-decoration: none;">← Back</a>
</nav>

<div class="page-hero">
    <h1>Become a Verified Guide</h1>
    <p style="color: rgba(255,255,245,.75); margin-top: 12px;">Share your expertise with hikers across the Philippines</p>
</div>

<div class="page-body">

<?php if ($success): ?>
    <div style="background: white; border-radius: 20px; padding: 60px 40px; text-align: center;">
        <h2>Application Submitted! 🎉</h2>
        <p>Your guide account is pending admin approval. You'll receive an email once verified.</p>
        <a href="index.php" style="display: inline-block; margin-top: 24px; padding: 12px 28px; background: var(--forest); color: white; text-decoration: none; border-radius: 50px;">Return Home</a>
    </div>
<?php else: ?>

<?php if (!empty($errors)): ?>
    <div style="background: #fff5f5; border: 1px solid #fca5a5; border-radius: 12px; padding: 16px; margin-bottom: 22px;">
        <ul><?php foreach ($errors as $e) echo "<li style='color:#c44'>" . htmlspecialchars($e) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="registerForm">

    <!-- ID VERIFICATION SECTION -->
    <div class="ocr-zone-top">
        <div style="display: flex; gap: 12px; margin-bottom: 20px;">
            <div>
                <h3>Government ID Verification</h3>
                <p style="font-size: 12px; color: var(--stone); margin-top: 4px;">Required — we'll auto-read your name from your ID</p>
            </div>
        </div>

        <div class="form-group">
            <label>Upload Your Government ID <span class="req">*</span></label>
            <div class="drop-zone" id="ocrZone" onclick="document.getElementById('govIdInput').click()">
                <input type="file" name="gov_id" id="govIdInput" accept="image/jpeg,image/png,image/webp" required onchange="handleIdUpload(this)">
                <div style="margin-bottom: 12px;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#4a7c4a" stroke-width="1.3">
                        <rect x="2" y="6" width="20" height="14" rx="2"/>
                        <circle cx="8" cy="13" r="2"/>
                        <path d="M12 10h8M12 14h4"/>
                    </svg>
                </div>
                <div class="upload-label" id="ocrZoneLabel">Click to upload a clear photo of your ID</div>
                <div class="upload-hint" style="font-size: 11px; color: var(--stone); margin-top: 5px;">JPG, PNG, or WEBP · Max 5 MB</div>
            </div>
        </div>

        <div class="ocr-status" id="ocrLoading">
            <div class="spinner"></div>
            Reading your ID...
        </div>
        <div class="ocr-status" id="ocrDone">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span id="ocrDoneMsg"></span>
        </div>
        <div class="ocr-status" id="ocrError">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
            <span id="ocrErrorMsg"></span>
        </div>

        <input type="hidden" name="id_extracted_name" id="id_extracted_name" value="">

        <div class="form-group" style="margin-top: 20px;">
            <label>ID Type <span class="req">*</span></label>
            <div class="id-type-grid">
                <?php foreach ($id_types as $id): ?>
                <div>
                    <input type="radio" name="id_type" id="id_<?= md5($id) ?>" value="<?= htmlspecialchars($id) ?>" class="id-option" <?= (isset($_POST['id_type']) && $_POST['id_type'] === $id) ? 'checked' : '' ?>>
                    <label class="id-label" for="id_<?= md5($id) ?>"><?= htmlspecialchars($id) ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ACCOUNT INFORMATION -->
    <div class="form-card">
        <div class="card-header">
            <div>
                <h3>Account Information</h3>
                <p style="font-size: 12px; color: var(--stone);">This will be your login credentials on LAKBAY</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name <span class="req">*</span> <span style="font-weight: normal; font-size: 11px;">(As shown on your ID)</span></label>
                    <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="Will auto-fill from ID" required>
                </div>
                <div class="form-group">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@email.com" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Phone Number <span class="req">*</span></label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="09XXXXXXXXX" required>
                </div>
                <div class="form-group">
                    <label>Home Region</label>
                    <select name="home_region">
                        <option value="">Select region</option>
                        <?php foreach (['Batangas','Cavite','Laguna','Quezon','Rizal','Metro Manila'] as $r): ?>
                        <option <?= (isset($_POST['home_region']) && $_POST['home_region'] === $r) ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Username <span class="req">*</span></label>
                    <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="e.g. juantrail" required>
                </div>
                <div class="form-group"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password <span class="req">*</span> <span style="font-weight: normal;">(min. 8 characters)</span></label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="req">*</span></label>
                    <input type="password" name="confirm_password" placeholder="••••••••" required>
                </div>
            </div>
        </div>
    </div>

    <!-- GUIDE PROFILE -->
    <div class="form-card">
        <div class="card-header">
            <div>
                <h3>Guide Profile</h3>
                <p style="font-size: 12px; color: var(--stone);">What hikers will see when browsing guides</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Specialization <span class="req">*</span></label>
                    <select name="specialization" required>
                        <option value="">Select your specialty</option>
                        <?php foreach (['Mountain Trekking','Bird Watching','Rock Climbing','Night Hiking','Family-Friendly Hiking','Photography Hike','Multi-day Expedition','River & Waterfall Trail','Eco-Tourism'] as $s): ?>
                        <option <?= (isset($_POST['specialization']) && $_POST['specialization'] === $s) ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Years of Experience <span class="req">*</span></label>
                    <input type="number" name="years_experience" value="<?= htmlspecialchars($_POST['years_experience'] ?? '0') ?>" min="0" max="50" required>
                </div>
            </div>
            <div class="form-group">
                <label>Bio / About You <span class="req">*</span></label>
                <textarea name="bio" rows="4" placeholder="Describe your experience, the trails you know, your guiding style..." required><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <button type="submit" class="btn-submit">Submit Guide Application</button>
</form>
<?php endif; ?>
</div>

<script>
async function handleIdUpload(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const zone = document.getElementById('ocrZone');
    const label = document.getElementById('ocrZoneLabel');
    const loadEl = document.getElementById('ocrLoading');
    const doneEl = document.getElementById('ocrDone');
    const doneMsg = document.getElementById('ocrDoneMsg');
    const errEl = document.getElementById('ocrError');
    const errMsg = document.getElementById('ocrErrorMsg');
    const nameField = document.getElementById('full_name');
    const hiddenField = document.getElementById('id_extracted_name');
    
    // Reset UI
    loadEl.className = 'ocr-status';
    doneEl.className = 'ocr-status';
    errEl.className = 'ocr-status';
    label.textContent = '✓ ' + file.name.substring(0, 40);
    zone.classList.add('has-file');
    
    // Prepare FormData for backend OCR
    const formData = new FormData();
    formData.append('ocr_image', file);
    
    loadEl.className = 'ocr-status loading';
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success && result.name) {
    nameField.value = result.name;
    hiddenField.value = result.name;
    doneMsg.textContent = `✓ Name read: "${result.name}" — please verify`;
    doneEl.className = 'ocr-status done';
    nameField.style.borderColor = '#4a7c4a';
    nameField.style.backgroundColor = 'rgba(74,124,74,.05)';
    
    // Auto-select ID type if detected
    if (result.detected_id_type) {
        // Find and click the matching radio button
        const radioButtons = document.querySelectorAll('input[name="id_type"]');
        for (let radio of radioButtons) {
            if (radio.value === result.detected_id_type) {
                radio.checked = true;
                // Add visual feedback
                const label = radio.nextElementSibling;
                if (label) {
                    label.style.borderColor = '#4a7c4a';
                    label.style.background = 'rgba(74,124,74,.07)';
                    setTimeout(() => {
                        label.style.borderColor = '';
                        label.style.background = '';
                    }, 2000);
                }
                break;
            }
        }
    }
    
    setTimeout(() => {
        nameField.style.borderColor = '';
        nameField.style.backgroundColor = '';
    }, 2000);
} else {
            let errorMessage = result.error || 'Could not read name. Please type it manually.';
            errMsg.textContent = errorMessage;
            errEl.className = 'ocr-status error';
            
            // Log debug info to console if available
            if (result.debug) {
                console.log('OCR Debug output:', result.debug);
            }
        }
    } catch (e) {
        console.error('OCR error:', e);
        errMsg.textContent = 'OCR service error. Please type your name manually.';
        errEl.className = 'ocr-status error';
    } finally {
        loadEl.className = 'ocr-status';
    }
}
</script>
</body>
</html>