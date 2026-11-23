<?php
require 'koneksi.php';
session_start();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['custom_design'])) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . basename($_FILES["custom_design"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["custom_design"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["custom_design"]["tmp_name"], $target_file)) {
            $_SESSION['custom_design'] = $target_file;
            // Reset positions when new design is uploaded
            unset($_SESSION['element_positions']);
        }
    }
    
    header('Location: cetak_jadwal.php');
    exit;
}

// Handle remove custom design
if (isset($_GET['remove_design'])) {
    if (isset($_SESSION['custom_design']) && file_exists($_SESSION['custom_design'])) {
        unlink($_SESSION['custom_design']);
    }
    unset($_SESSION['custom_design']);
    unset($_SESSION['element_positions']);
    header('Location: cetak_jadwal.php');
    exit;
}

// Handle element position saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_positions'])) {
  // Expect positions as JSON string from AJAX. Decode and store as PHP array.
  $positionsJson = isset($_POST['positions']) ? $_POST['positions'] : null;
  if ($positionsJson) {
    $decoded = json_decode($positionsJson, true);
    if (is_array($decoded)) {
      $_SESSION['element_positions'] = $decoded;
    } else {
      // Fallback: store raw string
      $_SESSION['element_positions'] = $positionsJson;
    }
  }

  // If this is an AJAX/save request, return JSON success. Otherwise redirect.
  // Detect AJAX by checking Accept header or presence of positions parameter.
  $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
  if (strpos($accept, 'application/json') !== false || $positionsJson) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
  } else {
    header('Location: cetak_jadwal.php');
    exit;
  }
}

// Handle template download
if (isset($_GET['download_template'])) {
    // Create template data array
    $templateData = [
        'version' => '1.0',
        'created_at' => date('Y-m-d H:i:s'),
        'template_type' => isset($_SESSION['template_type']) ? $_SESSION['template_type'] : 'default',
        'custom_settings' => [
            'title' => isset($_SESSION['custom_title']) ? $_SESSION['custom_title'] : 'JADWAL PIKET',
            'color' => isset($_SESSION['custom_color']) ? $_SESSION['custom_color'] : '#4caf50',
            'school' => isset($_SESSION['custom_school']) ? $_SESSION['custom_school'] : 'SMP NEGERI 1 KANDANGAN',
            'show_icons' => isset($_SESSION['custom_icons']) ? $_SESSION['custom_icons'] : true,
            'show_footer' => isset($_SESSION['custom_footer']) ? $_SESSION['custom_footer'] : true
        ],
        'element_positions' => isset($_SESSION['element_positions']) ? $_SESSION['element_positions'] : [],
        'has_custom_design' => isset($_SESSION['custom_design']),
        'custom_design_filename' => isset($_SESSION['custom_design']) ? basename($_SESSION['custom_design']) : null
    ];
    
    // Convert to JSON
    $jsonTemplate = json_encode($templateData, JSON_PRETTY_PRINT);
    
    // Set headers for download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="jadwal_piket_template_' . date('Y-m-d') . '.json"');
    
    // Output JSON
    echo $jsonTemplate;
    exit;
}

// Handle template import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['template_import'])) {
    $templateFile = $_FILES['template_import']['tmp_name'];
    
    if (is_uploaded_file($templateFile)) {
        $templateContent = file_get_contents($templateFile);
        $templateData = json_decode($templateContent, true);
        
        if ($templateData && isset($templateData['version'])) {
            // Import template type
            if (isset($templateData['template_type'])) {
                $_SESSION['template_type'] = $templateData['template_type'];
            }
            
            // Import custom settings
            if (isset($templateData['custom_settings'])) {
                $_SESSION['custom_title'] = $templateData['custom_settings']['title'];
                $_SESSION['custom_color'] = $templateData['custom_settings']['color'];
                $_SESSION['custom_school'] = $templateData['custom_settings']['school'];
                $_SESSION['custom_icons'] = $templateData['custom_settings']['show_icons'];
                $_SESSION['custom_footer'] = $templateData['custom_settings']['show_footer'];
            }
            
            // Import element positions
            if (isset($templateData['element_positions'])) {
                $_SESSION['element_positions'] = $templateData['element_positions'];
            }
            
            // If template has custom design, check if the file exists
            if (isset($templateData['has_custom_design']) && $templateData['has_custom_design'] && 
                isset($templateData['custom_design_filename'])) {
                
                $customDesignPath = "uploads/" . $templateData['custom_design_filename'];
                if (file_exists($customDesignPath)) {
                    $_SESSION['custom_design'] = $customDesignPath;
                }
            }
            
            // Set success message
            $_SESSION['import_message'] = "Template berhasil diimpor!";
        } else {
            // Set error message
            $_SESSION['import_message'] = "Format template tidak valid!";
        }
    } else {
        $_SESSION['import_message'] = "Gagal mengunggah file template!";
    }
    
    header('Location: cetak_jadwal.php');
    exit;
}

// Handle AJAX request for real-time data refresh
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_latest_data'])) {
    // Get the latest data from database
    $res = mysqli_query($koneksi, "SELECT * FROM jadwal_piket ORDER BY FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu')");
    $jadwalData = [];
    while($r = mysqli_fetch_assoc($res)){
        $jadwalData[] = $r;
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $jadwalData,
        'timestamp' => time()
    ]);
    exit;
}

// Handle save custom settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_custom_settings'])) {
    $_SESSION['custom_title'] = $_POST['custom_title'];
    $_SESSION['custom_color'] = $_POST['custom_color'];
    $_SESSION['custom_school'] = $_POST['custom_school'];
    $_SESSION['custom_icons'] = isset($_POST['custom_icons']) ? $_POST['custom_icons'] : 'false';
    $_SESSION['custom_footer'] = isset($_POST['custom_footer']) ? $_POST['custom_footer'] : 'false';
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Ambil data jadwal dari database (real-time)
 $res = mysqli_query($koneksi, "SELECT * FROM jadwal_piket ORDER BY FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu')");
 $jadwalData = [];
while($r = mysqli_fetch_assoc($res)){
    $jadwalData[] = $r;
}

// Store in session for comparison with future updates
 $_SESSION['last_data_fetch'] = time();
 $_SESSION['last_data_count'] = count($jadwalData);

// Get current custom settings
 $currentTitle = isset($_SESSION['custom_title']) ? $_SESSION['custom_title'] : 'JADWAL PIKET';
 $currentColor = isset($_SESSION['custom_color']) ? $_SESSION['custom_color'] : '#4caf50';
 $currentSchool = isset($_SESSION['custom_school']) ? $_SESSION['custom_school'] : 'SMP NEGERI 1 KANDANGAN';
 $currentIcons = isset($_SESSION['custom_icons']) ? $_SESSION['custom_icons'] : true;
 $currentFooter = isset($_SESSION['custom_footer']) ? $_SESSION['custom_footer'] : true;
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Jadwal Piket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
      @media print {
        /* Hide all control elements */
        .no-print { 
          display: none !important; 
        }
        
        body { 
          padding: 0; 
          margin: 0;
          background: white;
        }
        
        .container { 
          max-width: 100%; 
          padding: 0;
          margin: 0;
        }
        
        /* Hide all template selector and adjustment panels */
        .template-selector,
        .adjustment-panel,
        .remove-design-btn,
        #adjust-positions-btn,
        .data-refresh-section,
        .template-actions {
          display: none !important;
        }
        
        /* Hide all templates by default */
        .default-template,
        .simple-template,
        .uploaded-template {
          display: none !important;
        }
        
        /* Only show active template based on selected option */
        body[data-active-template="default"] .default-template {
          display: block !important;
        }
        
        body[data-active-template="simple"] .simple-template {
          display: block !important;
        }
        
        body[data-active-template="upload"] .uploaded-template {
          display: block !important;
        }
        
        /* Make sure active template takes full page */
        body[data-active-template="default"] .default-template,
        body[data-active-template="simple"] .simple-template,
        body[data-active-template="upload"] .uploaded-template {
          box-shadow: none;
          border: none;
          margin: 0;
          padding: 20px;
          page-break-after: auto;
        }
        
        /* Show footer only for default and simple templates */
        body[data-active-template="default"] .footer,
        body[data-active-template="simple"] .footer {
          display: block !important;
        }
        
        /* Hide footer for upload template (it's part of uploaded design) */
        body[data-active-template="upload"] .footer {
          display: none !important;
        }
      }
      
      body { 
        padding: 20px; 
        font-family: 'Comic Sans MS', cursive, sans-serif;
        background-color: #f8f9fa;
      }
      .template-selector {
        background-color: white;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 0 5px rgba(0,0,0,0.1);
      }
      .template-option {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.3s;
      }
      .template-option:hover {
        background-color: #f8f9fa;
      }
      .template-option.selected {
        border-color: #4caf50;
        background-color: #f1f8e9;
      }
      .template-preview {
        height: 100px;
        background-color: #f8f9fa;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 10px;
        font-size: 0.9rem;
        color: #666;
      }
      .custom-template {
        display: none;
        margin-top: 15px;
      }
      .upload-section {
        display: none;
        margin-top: 15px;
        padding: 15px;
        border: 1px dashed #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
      }
      .upload-preview {
        max-width: 100%;
        max-height: 200px;
        margin-top: 10px;
        border-radius: 5px;
      }
      .schedule-container {
        background-color: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        border: 3px solid #4caf50;
      }
      .schedule-container::before {
        content: "";
        position: absolute;
        top: -50px;
        right: -50px;
        width: 150px;
        height: 150px;
        background-color: rgba(76, 175, 80, 0.1);
        border-radius: 50%;
        z-index: 0;
      }
      .schedule-title {
        text-align: center;
        margin-bottom: 30px;
        position: relative;
        z-index: 1;
      }
      .schedule-title h2 {
        color: #2e7d32;
        font-weight: bold;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        font-size: 2.5rem;
      }
      /* Modified days-grid layout for better print arrangement */
      .days-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr); /* 3 columns for better layout */
        gap: 15px;
        margin-bottom: 20px;
      }
      .day-card {
        border: 2px dashed #4caf50;
        border-radius: 15px;
        padding: 15px;
        background-color: #f1f8e9;
        position: relative;
        min-height: 280px; /* Consistent height for all cards */
        transition: transform 0.3s;
      }
      .day-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      }
      .day-title {
        text-align: center;
        font-weight: bold;
        color: white;
        background-color: #4caf50;
        padding: 8px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 1.2rem;
      }
      /* Modified student list for vertical layout with 5 names per column */
      .student-list {
        list-style-type: none;
        padding-left: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 0; /* No gap between columns */
      }
      .student-column {
        flex: 1;
        min-width: 0;
        padding-right: 10px; /* Space between columns */
      }
      .student-list li {
        margin-bottom: 8px;
        padding: 6px 10px;
        background-color: rgba(255, 255, 255, 0.7);
        border-radius: 8px;
        position: relative;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
      }
      .student-list li:before {
        content: "👤";
        margin-right: 8px;
        font-size: 0.9rem;
      }
      /* Special layout for Saturday to be next to Monday */
      .day-card.saturday-card {
        grid-column: 1; /* Place in first column */
        grid-row: 2; /* Place in second row */
        min-height: 280px; /* Same height as other cards */
      }
      .day-card:not(.saturday-card) {
        grid-row: auto;
      }
      .cleaning-icon {
        position: absolute;
        font-size: 1.5rem;
        color: #4caf50;
        opacity: 0.7;
      }
      .icon-broom {
        top: 10px;
        left: 10px;
      }
      .icon-trash {
        bottom: 10px;
        right: 10px;
      }
      .recycle-icon {
        position: absolute;
        bottom: 20px;
        right: 20px;
        font-size: 2.5rem;
        color: #4caf50;
        z-index: 2;
      }
      .decoration {
        position: absolute;
        font-size: 1.5rem;
        opacity: 0.3;
      }
      .decoration-1 {
        top: 20px;
        left: 20px;
        transform: rotate(-15deg);
      }
      .decoration-2 {
        bottom: 30px;
        left: 30px;
        transform: rotate(10deg);
      }
      .footer {
        text-align: center;
        margin-top: 30px;
        color: #757575;
        font-size: 0.8rem;
      }
      .school-name {
        text-align: center;
        margin-top: 20px;
        font-weight: bold;
        color: #2e7d32;
        font-size: 1.2rem;
      }
      .simple-template {
        display: none;
      }
      .simple-template .schedule-container {
        border: none;
        background: none;
        box-shadow: none;
        padding: 0;
      }
      .simple-template .schedule-title h2 {
        color: #000;
        text-shadow: none;
        font-size: 2rem;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
      }
      .simple-template .day-card {
        border: 1px solid #000;
        background: none;
        min-height: 280px; /* Consistent height */
      }
      .simple-template .day-title {
        background: none;
        color: #000;
        border-bottom: 1px solid #000;
        padding: 5px 0;
      }
      .simple-template .student-list li {
        background: none;
        padding: 5px 0;
        border-bottom: 1px dotted #ccc;
      }
      .simple-template .student-list li:before {
        content: "•";
        margin-right: 8px;
      }
      .simple-template .cleaning-icon,
      .simple-template .recycle-icon,
      .simple-template .decoration {
        display: none;
      }
      .uploaded-template {
        display: none;
        position: relative;
        background-color: white;
        border-radius: 15px;
        overflow: hidden;
      }
      .uploaded-template .template-bg {
        width: 100%;
        display: block;
      }
      .uploaded-template .content-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
      }
      .uploaded-template .draggable-element {
        position: absolute;
        cursor: move;
        pointer-events: auto;
        z-index: 10;
      }
      .uploaded-template .element-title {
        font-weight: bold;
        text-align: center;
        margin-bottom: 10px;
      }
      .uploaded-template .element-school {
        text-align: center;
        font-weight: bold;
        margin-bottom: 20px;
      }
      .uploaded-template .element-day {
        border: 1px solid rgba(0,0,0,0.3);
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 15px;
        background-color: rgba(255,255,255,0.7);
      }
      .uploaded-template .element-day-title {
        font-weight: bold;
        margin-bottom: 8px;
        text-align: center;
        border-bottom: 1px solid rgba(0,0,0,0.2);
        padding-bottom: 5px;
      }
      .uploaded-template .element-student {
        margin-bottom: 5px;
        padding-left: 20px;
        position: relative;
      }
      .uploaded-template .element-student:before {
        content: "•";
        position: absolute;
        left: 5px;
      }
      .adjustment-panel {
        display: none;
        background-color: white;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 0 5px rgba(0,0,0,0.1);
      }
      .adjustment-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
      }
      .adjustment-btn {
        padding: 5px 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background-color: #f8f9fa;
        cursor: pointer;
      }
      .adjustment-btn:hover {
        background-color: #e9ecef;
      }
      .adjustment-btn.active {
        background-color: #4caf50;
        color: white;
        border-color: #4caf50;
      }
      .element-properties {
        display: none;
        background-color: #f8f9fa;
        border-radius: 5px;
        padding: 10px;
        margin-top: 10px;
      }
      .property-row {
        display: flex;
        margin-bottom: 8px;
        align-items: center;
      }
      .property-label {
        width: 100px;
        font-weight: bold;
      }
      .property-input {
        flex: 1;
      }
      .remove-design-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
      }
      .position-indicator {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: 2px dashed #ff0000;
        pointer-events: none;
        z-index: 5;
        display: none;
      }
      .data-source {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 5px;
        font-style: italic;
      }
      .data-refresh-section {
        background-color: white;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 0 5px rgba(0,0,0,0.1);
      }
      .refresh-status {
        display: inline-block;
        margin-left: 10px;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8rem;
      }
      .refresh-status.success {
        background-color: #d4edda;
        color: #155724;
      }
      .refresh-status.error {
        background-color: #f8d7da;
        color: #721c24;
      }
      .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px;
        border-radius: 5px;
        color: white;
        font-weight: bold;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      }
      .notification.success {
        background-color: #4caf50;
      }
      .notification.info {
        background-color: #2196f3;
      }
      .template-actions {
        background-color: white;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 0 5px rgba(0,0,0,0.1);
      }
      .import-section {
        margin-top: 15px;
        padding: 15px;
        border: 1px dashed #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
      }
    </style>
  </head>
  <body data-active-template="default">
    <div class="container">
      <div class="d-flex justify-content-between mb-3 no-print">
        <h3>Cetak Jadwal Piket</h3>
        <div>
          <a href="jadwal.php" class="btn btn-sm btn-secondary">Kembali</a>
          <button onclick="window.print()" class="btn btn-primary">Cetak Jadwal</button>
        </div>
      </div>
      
      <div class="template-actions no-print">
        <h5>Template Actions</h5>
        <div class="d-flex gap-2">
          <a href="cetak_jadwal.php?download_template=1" class="btn btn-success">
            <i class="fas fa-download"></i> Download Template
          </a>
          <button class="btn btn-info" data-bs-toggle="collapse" data-bs-target="#importTemplateCollapse">
            <i class="fas fa-upload"></i> Import Template
          </button>
        </div>
        
        <div class="collapse" id="importTemplateCollapse">
          <div class="import-section">
            <form action="cetak_jadwal.php" method="post" enctype="multipart/form-data">
              <div class="mb-3">
                <label class="form-label">Pilih File Template</label>
                <input type="file" class="form-control" name="template_import" accept=".json" required>
                <div class="form-text">Format yang didukung: JSON. Pilih file template yang telah Anda download sebelumnya.</div>
              </div>
              <button type="submit" class="btn btn-primary">Import Template</button>
            </form>
            
            <?php if (isset($_SESSION['import_message'])): ?>
              <div class="alert alert-info mt-3">
                <?php 
                  echo $_SESSION['import_message'];
                  unset($_SESSION['import_message']);
                ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="data-refresh-section no-print">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h5>Real-time Data</h5>
            <div class="data-source">
              <i class="fas fa-sync-alt"></i> Data diambil langsung dari database (real-time)
              <span id="last-update-time" class="ms-2"></span>
            </div>
          </div>
          <div>
            <button id="refresh-data-btn" class="btn btn-sm btn-success">
              <i class="fas fa-sync-alt"></i> Refresh Data
            </button>
            <div class="form-check form-check-inline ms-2">
              <input class="form-check-input" type="checkbox" id="auto-refresh" checked>
              <label class="form-check-label" for="auto-refresh">
                Auto Refresh (30 detik)
              </label>
            </div>
          </div>
        </div>
        <div id="refresh-status" class="refresh-status"></div>
      </div>
      
      <div class="template-selector no-print">
        <h5>Pilih Template Desain</h5>
        <div class="row">
          <div class="col-md-3">
            <div class="template-option selected" data-template="default">
              <strong>Template Default</strong>
              <div class="template-preview">
                Template dengan desain warna hijau dan ikon kebersihan
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="template-option" data-template="simple">
              <strong>Template Sederhana</strong>
              <div class="template-preview">
                Template hitam putih sederhana tanpa dekorasi
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="template-option" data-template="custom">
              <strong>Template Kustom</strong>
              <div class="template-preview">
                Buat desain Anda sendiri
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="template-option" data-template="upload">
              <strong>Upload Desain</strong>
              <div class="template-preview">
                Upload gambar desain Anda sendiri
              </div>
            </div>
          </div>
        </div>
        
        <div class="custom-template">
          <h6>Desain Kustom</h6>
          <div class="mb-3">
            <label class="form-label">Judul Jadwal</label>
            <input type="text" class="form-control" id="custom-title" value="<?php echo $currentTitle; ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Warna Tema</label>
            <select class="form-select" id="custom-color">
              <option value="#4caf50" <?php echo ($currentColor == '#4caf50') ? 'selected' : ''; ?>>Hijau</option>
              <option value="#2196f3" <?php echo ($currentColor == '#2196f3') ? 'selected' : ''; ?>>Biru</option>
              <option value="#f44336" <?php echo ($currentColor == '#f44336') ? 'selected' : ''; ?>>Merah</option>
              <option value="#ff9800" <?php echo ($currentColor == '#ff9800') ? 'selected' : ''; ?>>Oranye</option>
              <option value="#9c27b0" <?php echo ($currentColor == '#9c27b0') ? 'selected' : ''; ?>>Ungu</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Sekolah</label>
            <input type="text" class="form-control" id="custom-school" value="<?php echo $currentSchool; ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Tampilkan Ikon</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="custom-icons" <?php echo $currentIcons ? 'checked' : ''; ?>>
              <label class="form-check-label" for="custom-icons">
                Tampilkan ikon kebersihan
              </label>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Tampilkan Nama Pembuat</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="custom-footer" <?php echo $currentFooter ? 'checked' : ''; ?>>
              <label class="form-check-label" for="custom-footer">
                Tampilkan "Dibuat oleh Moh. Yazidur Rizky"
              </label>
            </div>
          </div>
          <div class="mb-3">
            <button class="btn btn-success" id="apply-custom-settings">Terapkan Pengaturan</button>
          </div>
        </div>
        
        <div class="upload-section">
          <h6>Upload Desain Sendiri</h6>
          <form action="cetak_jadwal.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label">Pilih Gambar Desain</label>
              <input type="file" class="form-control" name="custom_design" accept="image/*" required>
              <div class="form-text">Format yang didukung: JPG, PNG, GIF. Maksimal 2MB.</div>
            </div>
            <button type="submit" class="btn btn-primary">Upload Desain</button>
            <?php if (isset($_SESSION['custom_design'])): ?>
              <a href="cetak_jadwal.php?remove_design=1" class="btn btn-danger">Hapus Desain</a>
            <?php endif; ?>
          </form>
          
          <?php if (isset($_SESSION['custom_design'])): ?>
            <div class="mt-3">
              <label class="form-label">Preview Desain Anda:</label>
              <img src="<?php echo $_SESSION['custom_design']; ?>" alt="Custom Design" class="upload-preview">
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="adjustment-panel no-print" id="adjustment-panel">
        <h5>Menyesuaikan Posisi Elemen</h5>
        <p>Drag elemen untuk menyesuaikan posisinya sesuai dengan desain Anda.</p>
        
        <!-- New section for background properties -->
        <div class="bg-properties" id="bg-properties" style="display: none;">
            <h6>Properti Background</h6>
            <div class="property-row">
                <div class="property-label">Blur Background:</div>
                <div class="property-input">
                    <input type="range" class="form-range" id="bg-blur-slider" min="0" max="10" value="0" step="0.5">
                    <span id="bg-blur-value">0px</span>
                </div>
            </div>
        </div>
        
        <div class="adjustment-controls">
          <div class="adjustment-btn active" data-element="title">Judul</div>
          <div class="adjustment-btn" data-element="school">Sekolah</div>
          <div class="adjustment-btn" data-element="presisi">Presisi</div>
          <div class="adjustment-btn" data-element="day-senin">Senin</div>
          <div class="adjustment-btn" data-element="day-selasa">Selasa</div>
          <div class="adjustment-btn" data-element="day-rabu">Rabu</div>
          <div class="adjustment-btn" data-element="day-kamis">Kamis</div>
          <div class="adjustment-btn" data-element="day-jumat">Jumat</div>
        </div>
        
        <div class="element-properties" id="title-properties">
          <h6>Properti Judul</h6>
          <div class="property-row">
            <div class="property-label">Ukuran Font:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="title-font-size" min="10" max="50" value="30">
              <span id="title-font-size-value">30px</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Warna Teks:</div>
            <div class="property-input">
              <input type="color" class="form-control form-control-color" id="title-color" value="#000000">
            </div>
          </div>
        </div>
        
        <div class="element-properties" id="school-properties">
          <h6>Properti Sekolah</h6>
          <div class="property-row">
            <div class="property-label">Ukuran Font:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="school-font-size" min="10" max="30" value="18">
              <span id="school-font-size-value">18px</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Warna Teks:</div>
            <div class="property-input">
              <input type="color" class="form-control form-control-color" id="school-color" value="#000000">
            </div>
          </div>
        </div>

        <div class="element-properties" id="presisi-properties">
          <h6>Properti Presisi</h6>
          <div class="property-row">
            <div class="property-label">Ukuran Font:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="presisi-font-size" min="8" max="50" value="18">
              <span id="presisi-font-size-value">18px</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Warna Teks:</div>
            <div class="property-input">
              <input type="color" class="form-control form-control-color" id="presisi-color" value="#000000">
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Konten:</div>
            <div class="property-input">
              <select id="presisi-content-type" class="form-select">
                <option value="school">Nama Sekolah</option>
                <option value="creator">Nama Pembuat</option>
                <option value="custom">Custom</option>
              </select>
              <input type="text" id="presisi-custom-text" class="form-control mt-2" placeholder="Teks custom (jika dipilih)">
            </div>
          </div>
        </div>
        
        <div class="element-properties" id="day-properties">
          <h6>Properti Hari</h6>
          <div class="property-row">
            <div class="property-label">Lebar:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="day-width" min="100" max="500" value="200">
              <span id="day-width-value">200px</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Tinggi:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="day-height" min="100" max="400" value="280">
              <span id="day-height-value">280px</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Ukuran Font:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="day-font-size" min="10" max="20" value="14">
              <span id="day-font-size-value">14px</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Warna Teks:</div>
            <div class="property-input">
              <input type="color" class="form-control form-control-color" id="day-color" value="#000000">
            </div>
          </div>
          <!-- New properties for box customization -->
          <div class="property-row">
            <div class="property-label">Warna Kotak:</div>
            <div class="property-input">
              <input type="color" class="form-control form-control-color" id="day-bg-color" value="#f1f8e9">
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Warna Border:</div>
            <div class="property-input">
              <input type="color" class="form-control form-control-color" id="day-border-color" value="#4caf50">
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Ketebalan Border:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="day-border-width" min="0" max="10" value="2" step="0.5">
              <span id="day-border-width-value">2px</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Transparansi:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="day-opacity" min="0" max="1" value="1" step="0.1">
              <span id="day-opacity-value">100%</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">&nbsp;</div>
            <div class="property-input">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="day-box-only-opacity">
                <label class="form-check-label" for="day-box-only-opacity">
                  Transparansi Kotak Saja
                </label>
              </div>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Blur Background:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="day-blur" min="0" max="10" value="0" step="0.5">
              <span id="day-blur-value">0px</span>
            </div>
          </div>
          <div class="property-row">
            <div class="property-label">Radius Sudut:</div>
            <div class="property-input">
              <input type="range" class="form-range" id="day-border-radius" min="0" max="50" value="15">
              <span id="day-border-radius-value">15px</span>
            </div>
          </div>
        </div>
        
        <div class="mt-3">
          <button class="btn btn-success" id="save-positions">Simpan Posisi</button>
          <button class="btn btn-secondary" id="reset-positions">Reset Posisi</button>
        </div>
      </div>
      
      <div class="schedule-container default-template">
        <div class="decoration decoration-1">🧹</div>
        <div class="decoration decoration-2">🪣</div>
        
        <div class="schedule-title">
          <h2><?php echo $currentTitle; ?></h2>
          <p class="text-muted">Jadwal Kebersihan Kelas</p>
        </div>
        
        <div class="days-grid" id="days-grid">
          <?php
          // Process data to arrange properly
          $processedData = [];
          $saturdayData = null;
          
          // First, separate Saturday from other days
          foreach($jadwalData as $r){
            $hari = isset($r['hari']) ? htmlspecialchars($r['hari'] ?? '') : '';
            
            if ($hari === 'Sabtu') {
              $saturdayData = $r;
            } else {
              $processedData[] = $r;
            }
          }
          
          // Add Saturday after Monday if it exists
          if ($saturdayData) {
            // Find Monday index and insert Saturday after it
            $mondayIndex = -1;
            foreach($processedData as $index => $r) {
              if (isset($r['hari']) && $r['hari'] === 'Senin') {
                $mondayIndex = $index;
                break;
              }
            }
            
            if ($mondayIndex >= 0) {
              array_splice($processedData, $mondayIndex + 1, 0, [$saturdayData]);
            }
          }
          
          // Display processed data
          foreach($processedData as $r){
            $hari = isset($r['hari']) ? htmlspecialchars($r['hari'] ?? '') : '';
            $is_saturday = ($hari === 'Sabtu');
            
            echo '<div class="day-card' . ($is_saturday ? ' saturday-card' : '') . '">';
            echo '<div class="day-title">' . $hari . '</div>';
            
            // Mengumpulkan nama siswa dari JSON
            $siswa_array = array();
            if (!empty($r['siswa_list'])) {
              $siswa_list = json_decode($r['siswa_list'], true);
              if (is_array($siswa_list)) {
                foreach ($siswa_list as $siswa) {
                  if (!empty($siswa)) {
                    $siswa_array[] = $siswa;
                  }
                }
              }
            }
            
            // Menampilkan nama siswa dalam layout vertikal (5 per kolom)
            echo '<ul class="student-list">';
            $columnCount = ceil(count($siswa_array) / 5);
            for ($col = 0; $col < $columnCount; $col++) {
              echo '<div class="student-column">';
              for ($i = $col * 5; $i < min(($col + 1) * 5, count($siswa_array)); $i++) {
                echo '<li>' . htmlspecialchars($siswa_array[$i]) . '</li>';
              }
              echo '</div>';
            }
            echo '</ul>';
            
            echo '<div class="cleaning-icon icon-broom"><i class="fas fa-broom"></i></div>';
            echo '<div class="cleaning-icon icon-trash"><i class="fas fa-trash-alt"></i></div>';
            echo '</div>';
          }
          ?>
        </div>
        
        <div class="recycle-icon">
          <i class="fas fa-recycle"></i>
        </div>
        
        <div class="school-name">
          <?php echo $currentSchool; ?>
        </div>
      </div>
      
      <div class="schedule-container simple-template">
        <div class="schedule-title">
          <h2><?php echo $currentTitle; ?></h2>
        </div>
        
        <div class="days-grid" id="days-grid-simple">
          <?php
          // Process data to arrange properly for simple template
          $processedDataSimple = [];
          $saturdayDataSimple = null;
          
          // First, separate Saturday from other days
          foreach($jadwalData as $r){
            $hari = isset($r['hari']) ? htmlspecialchars($r['hari'] ?? '') : '';
            
            if ($hari === 'Sabtu') {
              $saturdayDataSimple = $r;
            } else {
              $processedDataSimple[] = $r;
            }
          }
          
          // Add Saturday after Monday if it exists
          if ($saturdayDataSimple) {
            // Find Monday index and insert Saturday after it
            $mondayIndex = -1;
            foreach($processedDataSimple as $index => $r) {
              if (isset($r['hari']) && $r['hari'] === 'Senin') {
                $mondayIndex = $index;
                break;
              }
            }
            
            if ($mondayIndex >= 0) {
              array_splice($processedDataSimple, $mondayIndex + 1, 0, [$saturdayDataSimple]);
            }
          }
          
          // Display processed data
          foreach($processedDataSimple as $r){
            $hari = isset($r['hari']) ? htmlspecialchars($r['hari'] ?? '') : '';
            $is_saturday = ($hari === 'Sabtu');
            
            echo '<div class="day-card' . ($is_saturday ? ' saturday-card' : '') . '">';
            echo '<div class="day-title">' . $hari . '</div>';
            
            // Mengumpulkan nama siswa dari JSON
            $siswa_array = array();
            if (!empty($r['siswa_list'])) {
              $siswa_list = json_decode($r['siswa_list'], true);
              if (is_array($siswa_list)) {
                foreach ($siswa_list as $siswa) {
                  if (!empty($siswa)) {
                    $siswa_array[] = $siswa;
                  }
                }
              }
            }
            
            // Menampilkan nama siswa dalam layout vertikal (5 per kolom)
            echo '<ul class="student-list">';
            $columnCount = ceil(count($siswa_array) / 5);
            for ($col = 0; $col < $columnCount; $col++) {
              echo '<div class="student-column">';
              for ($i = $col * 5; $i < min(($col + 1) * 5, count($siswa_array)); $i++) {
                echo '<li>' . htmlspecialchars($siswa_array[$i]) . '</li>';
              }
              echo '</div>';
            }
            echo '</ul>';
            
            echo '</div>';
          }
          ?>
        </div>
        
        <div class="school-name">
          <?php echo $currentSchool; ?>
        </div>
      </div>
      
      <div class="uploaded-template" id="uploaded-template">
        <?php if (isset($_SESSION['custom_design'])): ?>
          <a href="cetak_jadwal.php?remove_design=1" class="btn btn-sm btn-danger remove-design-btn no-print">
            <i class="fas fa-times"></i> Hapus Desain
          </a>
          <button class="btn btn-sm btn-primary no-print" id="adjust-positions-btn" style="top: 10px; right: 120px; position: absolute;">
            <i class="fas fa-edit"></i> Sesuaikan Posisi
          </button>
        <?php endif; ?>
        
        <img src="<?php echo isset($_SESSION['custom_design']) ? $_SESSION['custom_design'] : ''; ?>" alt="Custom Design" class="template-bg">
        
        <div class="content-overlay" id="content-overlay">
          <div class="draggable-element element-title" id="element-title" style="top: 50px; left: 0; width: 100%; text-align: center;">
            <h2><?php echo $currentTitle; ?></h2>
          </div>
          
            <div class="draggable-element element-school" id="element-school" style="top: 120px; left: 0; width: 100%; text-align: center;">
              <h3><?php echo $currentSchool; ?></h3>
            </div>

            <div class="draggable-element element-presisi" id="element-presisi" style="top: 160px; left: 0; width: 100%; text-align: center;">
              <div class="presisi-content"><?php echo $currentSchool; ?><br><small>Dibuat oleh Moh. Yazidur Rizky</small></div>
            </div>
          
          <?php
          // Process data for uploaded template with same arrangement
          $processedDataUpload = [];
          $saturdayDataUpload = null;
          
          // First, separate Saturday from other days
          foreach($jadwalData as $r){
            $hari = isset($r['hari']) ? htmlspecialchars($r['hari'] ?? '') : '';
            
            if ($hari === 'Sabtu') {
              $saturdayDataUpload = $r;
            } else {
              $processedDataUpload[] = $r;
            }
          }
          
          // Add Saturday after Monday if it exists
          if ($saturdayDataUpload) {
            // Find Monday index and insert Saturday after it
            $mondayIndex = -1;
            foreach($processedDataUpload as $index => $r) {
              if (isset($r['hari']) && $r['hari'] === 'Senin') {
                $mondayIndex = $index;
                break;
              }
            }
            
            if ($mondayIndex >= 0) {
              array_splice($processedDataUpload, $mondayIndex + 1, 0, [$saturdayDataUpload]);
            }
          }
          
          // Gunakan data dari $processedDataUpload yang sudah diatur
          $dayPositions = [
            'Senin' => ['top' => 180, 'left' => 50],
            'Sabtu' => ['top' => 180, 'left' => 300], // Saturday next to Monday
            'Selasa' => ['top' => 180, 'left' => 550],
            'Rabu' => ['top' => 350, 'left' => 50],
            'Kamis' => ['top' => 350, 'left' => 300],
            'Jumat' => ['top' => 350, 'left' => 550]
          ];
          
          foreach($processedDataUpload as $r){
            // Fix: Check if 'hari' key exists and is not null before using htmlspecialchars
            $hari = isset($r['hari']) ? htmlspecialchars($r['hari'] ?? '') : '';
            
            $position = $dayPositions[$hari] ?? ['top' => 180, 'left' => 50];
            
            echo '<div class="draggable-element element-day" id="element-day-' . strtolower($hari) . '" style="top: ' . $position['top'] . 'px; left: ' . $position['left'] . 'px; width: 200px;">';
            echo '<div class="element-day-title">' . $hari . '</div>';
            
            // Mengumpulkan nama siswa dari JSON
            $siswa_array = array();
            if (!empty($r['siswa_list'])) {
              $siswa_list = json_decode($r['siswa_list'], true);
              if (is_array($siswa_list)) {
                foreach ($siswa_list as $siswa) {
                  if (!empty($siswa)) {
                    $siswa_array[] = $siswa;
                  }
                }
              }
            }
            
            // Menampilkan nama siswa dalam layout vertikal dengan maksimal 5 per kolom
            $columnCount = ceil(count($siswa_array) / 5);
            for ($col = 0; $col < $columnCount; $col++) {
              echo '<div class="element-student-column" style="display: inline-block; vertical-align: top; width: 48%; margin-right: 2%;">';
              for ($i = $col * 5; $i < min(($col + 1) * 5, count($siswa_array)); $i++) {
                echo '<div class="element-student">' . htmlspecialchars($siswa_array[$i]) . '</div>';
              }
              echo '</div>';
            }
            
            echo '</div>';
          }
          ?>
        </div>
        
        <div class="position-indicator" id="position-indicator"></div>
      </div>
      
      <div class="footer">
        <small>Dibuat oleh Moh. Yazidur Rizky</small>
      </div>
    </div>

    <!-- Notification element -->
    <div id="notification" class="notification"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const templateOptions = document.querySelectorAll('.template-option');
        const customTemplate = document.querySelector('.custom-template');
        const uploadSection = document.querySelector('.upload-section');
        const defaultTemplate = document.querySelector('.default-template');
        const simpleTemplate = document.querySelector('.simple-template');
        const uploadedTemplate = document.querySelector('.uploaded-template');
        const footer = document.querySelector('.footer');
        const adjustmentPanel = document.getElementById('adjustment-panel');
        const adjustPositionsBtn = document.getElementById('adjust-positions-btn');
        const savePositionsBtn = document.getElementById('save-positions');
        const resetPositionsBtn = document.getElementById('reset-positions');
        const positionIndicator = document.getElementById('position-indicator');
        const bgProperties = document.getElementById('bg-properties');
        const refreshDataBtn = document.getElementById('refresh-data-btn');
        const autoRefreshCheckbox = document.getElementById('auto-refresh');
        const refreshStatus = document.getElementById('refresh-status');
        const lastUpdateTime = document.getElementById('last-update-time');
        const notification = document.getElementById('notification');
        
        // Store initial data count for comparison
        let initialDataCount = <?php echo count($jadwalData); ?>;
        let autoRefreshInterval;
        
        // Update last update time
        function updateLastUpdateTime() {
          const now = new Date();
          lastUpdateTime.textContent = `(Terakhir diperbarui: ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')})`;
        }
        updateLastUpdateTime();
        
        // Show notification
        function showNotification(message, type = 'info') {
          notification.textContent = message;
          notification.className = 'notification ' + type;
          notification.style.display = 'block';
          
          // Hide after 5 seconds
          setTimeout(() => {
            notification.style.display = 'none';
          }, 5000);
        }
        
        // Function to refresh data
        function refreshScheduleData() {
          fetch('cetak_jadwal.php?get_latest_data=1')
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                const newDataCount = data.data.length;
                
                if (newDataCount > initialDataCount) {
                  showNotification(`Data jadwal telah diperbarui! Ada ${newDataCount - initialDataCount} data baru.`, 'success');
                  initialDataCount = newDataCount;
                }
                
                // Update all three templates with new data
                updateDefaultTemplate(data.data);
                updateSimpleTemplate(data.data);
                updateUploadedTemplate(data.data);
                
                // Update status
                refreshStatus.textContent = 'Data berhasil diperbarui';
                refreshStatus.className = 'refresh-status success';
                
                // Update last update time
                updateLastUpdateTime();
                
                // Hide status after 3 seconds
                setTimeout(() => {
                  refreshStatus.textContent = '';
                  refreshStatus.className = 'refresh-status';
                }, 3000);
              } else {
                refreshStatus.textContent = 'Gagal memperbarui data';
                refreshStatus.className = 'refresh-status error';
                
                setTimeout(() => {
                  refreshStatus.textContent = '';
                  refreshStatus.className = 'refresh-status';
                }, 3000);
              }
            })
            .catch(error => {
              console.error('Error:', error);
              refreshStatus.textContent = 'Terjadi kesalahan saat memperbarui data';
              refreshStatus.className = 'refresh-status error';
              
              setTimeout(() => {
                refreshStatus.textContent = '';
                refreshStatus.className = 'refresh-status';
              }, 3000);
            });
        }
        
        // Function to update default template
        function updateDefaultTemplate(data) {
          const daysGrid = document.getElementById('days-grid');
          if (!daysGrid) return;
          
          // Process data to arrange properly
          const processedData = [];
          let saturdayData = null;
          
          // First, separate Saturday from other days
          data.forEach(r => {
            const hari = r.hari || '';
            
            if (hari === 'Sabtu') {
              saturdayData = r;
            } else {
              processedData.push(r);
            }
          });
          
          // Add Saturday after Monday if it exists
          if (saturdayData) {
            // Find Monday index and insert Saturday after it
            const mondayIndex = processedData.findIndex(r => r.hari === 'Senin');
            
            if (mondayIndex >= 0) {
              processedData.splice(mondayIndex + 1, 0, saturdayData);
            }
          }
          
          // Clear existing content
          daysGrid.innerHTML = '';
          
          // Add new content
          processedData.forEach(r => {
            const hari = r.hari || '';
            const is_saturday = (hari === 'Sabtu');
            
            const dayCard = document.createElement('div');
            dayCard.className = 'day-card' + (is_saturday ? ' saturday-card' : '');
            
            // Create day title
            const dayTitle = document.createElement('div');
            dayTitle.className = 'day-title';
            dayTitle.textContent = hari;
            dayCard.appendChild(dayTitle);
            
            // Create student list
            const studentList = document.createElement('ul');
            studentList.className = 'student-list';
            
            // Parse and add students
            let siswa_array = [];
            if (r.siswa_list) {
              try {
                siswa_array = JSON.parse(r.siswa_list);
                if (!Array.isArray(siswa_array)) siswa_array = [];
              } catch (e) {
                siswa_array = [];
              }
            }
            
            // Split students into columns of 5 each
            const columnCount = Math.ceil(siswa_array.length / 5);
            for (let col = 0; col < columnCount; col++) {
              const studentColumn = document.createElement('div');
              studentColumn.className = 'student-column';
              
              for (let i = col * 5; i < Math.min((col + 1) * 5, siswa_array.length); i++) {
                const li = document.createElement('li');
                li.textContent = siswa_array[i];
                studentColumn.appendChild(li);
              }
              
              studentList.appendChild(studentColumn);
            }
            
            dayCard.appendChild(studentList);
            
            // Add cleaning icons
            const broomIcon = document.createElement('div');
            broomIcon.className = 'cleaning-icon icon-broom';
            broomIcon.innerHTML = '<i class="fas fa-broom"></i>';
            dayCard.appendChild(broomIcon);
            
            const trashIcon = document.createElement('div');
            trashIcon.className = 'cleaning-icon icon-trash';
            trashIcon.innerHTML = '<i class="fas fa-trash-alt"></i>';
            dayCard.appendChild(trashIcon);
            
            daysGrid.appendChild(dayCard);
          });
        }
        
        // Function to update simple template
        function updateSimpleTemplate(data) {
          const daysGridSimple = document.getElementById('days-grid-simple');
          if (!daysGridSimple) return;
          
          // Process data to arrange properly
          const processedData = [];
          let saturdayData = null;
          
          // First, separate Saturday from other days
          data.forEach(r => {
            const hari = r.hari || '';
            
            if (hari === 'Sabtu') {
              saturdayData = r;
            } else {
              processedData.push(r);
            }
          });
          
          // Add Saturday after Monday if it exists
          if (saturdayData) {
            // Find Monday index and insert Saturday after it
            const mondayIndex = processedData.findIndex(r => r.hari === 'Senin');
            
            if (mondayIndex >= 0) {
              processedData.splice(mondayIndex + 1, 0, saturdayData);
            }
          }
          
          // Clear existing content
          daysGridSimple.innerHTML = '';
          
          // Add new content
          processedData.forEach(r => {
            const hari = r.hari || '';
            const is_saturday = (hari === 'Sabtu');
            
            const dayCard = document.createElement('div');
            dayCard.className = 'day-card' + (is_saturday ? ' saturday-card' : '');
            
            // Create day title
            const dayTitle = document.createElement('div');
            dayTitle.className = 'day-title';
            dayTitle.textContent = hari;
            dayCard.appendChild(dayTitle);
            
            // Create student list
            const studentList = document.createElement('ul');
            studentList.className = 'student-list';
            
            // Parse and add students
            let siswa_array = [];
            if (r.siswa_list) {
              try {
                siswa_array = JSON.parse(r.siswa_list);
                if (!Array.isArray(siswa_array)) siswa_array = [];
              } catch (e) {
                siswa_array = [];
              }
            }
            
            // Split students into columns of 5 each
            const columnCount = Math.ceil(siswa_array.length / 5);
            for (let col = 0; col < columnCount; col++) {
              const studentColumn = document.createElement('div');
              studentColumn.className = 'student-column';
              
              for (let i = col * 5; i < Math.min((col + 1) * 5, siswa_array.length); i++) {
                const li = document.createElement('li');
                li.textContent = siswa_array[i];
                studentColumn.appendChild(li);
              }
              
              studentList.appendChild(studentColumn);
            }
            
            dayCard.appendChild(studentList);
            daysGridSimple.appendChild(dayCard);
          });
        }
        
        // Function to update uploaded template
        function updateUploadedTemplate(data) {
          const contentOverlay = document.getElementById('content-overlay');
          if (!contentOverlay) return;
          
          // Process data to arrange properly
          const processedData = [];
          let saturdayData = null;
          
          // First, separate Saturday from other days
          data.forEach(r => {
            const hari = r.hari || '';
            
            if (hari === 'Sabtu') {
              saturdayData = r;
            } else {
              processedData.push(r);
            }
          });
          
          // Add Saturday after Monday if it exists
          if (saturdayData) {
            // Find Monday index and insert Saturday after it
            const mondayIndex = processedData.findIndex(r => r.hari === 'Senin');
            
            if (mondayIndex >= 0) {
              processedData.splice(mondayIndex + 1, 0, saturdayData);
            }
          }
          
          // Get existing draggable elements (title, school, etc.)
          const existingElements = {};
          contentOverlay.querySelectorAll('.draggable-element').forEach(el => {
            if (el.id.startsWith('element-day-')) {
              const dayName = el.id.replace('element-day-', '');
              existingElements[dayName] = el;
            }
          });
          
          // Day positions for layout
          const dayPositions = [
            {name: 'Senin', top: 180, left: 50},
            {name: 'Sabtu', top: 180, left: 300}, // Saturday next to Monday
            {name: 'Selasa', top: 180, left: 550},
            {name: 'Rabu', top: 350, left: 50},
            {name: 'Kamis', top: 350, left: 300},
            {name: 'Jumat', top: 350, left: 550}
          ];
          
          // Update or create day elements
          dayPositions.forEach(pos => {
            const dayData = processedData.find(d => d.hari === pos.name);
            if (!dayData) return;
            
            let dayElement = existingElements[pos.name.toLowerCase()];
            
            if (dayElement) {
              // Update existing element
              // Remove existing student elements but keep title
              const existingStudents = dayElement.querySelectorAll('.element-student-column');
              existingStudents.forEach(el => el.remove());
            } else {
              // Create new element
              dayElement = document.createElement('div');
              dayElement.className = 'draggable-element element-day';
              dayElement.id = 'element-day-' + pos.name.toLowerCase();
              dayElement.style.top = pos.top + 'px';
              dayElement.style.left = pos.left + 'px';
              dayElement.style.width = '200px';
              contentOverlay.appendChild(dayElement);
              
              // Add day title
              const dayTitle = document.createElement('div');
              dayTitle.className = 'element-day-title';
              dayTitle.textContent = pos.name;
              dayElement.appendChild(dayTitle);
            }
            
            // Parse and add students in columns
            let siswa_array = [];
            if (dayData.siswa_list) {
              try {
                siswa_array = JSON.parse(dayData.siswa_list);
                if (!Array.isArray(siswa_array)) siswa_array = [];
              } catch (e) {
                siswa_array = [];
              }
            }
            
            // Split students into columns of 5 each
            const columnCount = Math.ceil(siswa_array.length / 5);
            for (let col = 0; col < columnCount; col++) {
              const studentColumn = document.createElement('div');
              studentColumn.className = 'element-student-column';
              studentColumn.style.display = 'inline-block';
              studentColumn.style.verticalAlign = 'top';
              studentColumn.style.width = '48%';
              studentColumn.style.marginRight = '2%';
              
              for (let i = col * 5; i < Math.min((col + 1) * 5, siswa_array.length); i++) {
                const studentDiv = document.createElement('div');
                studentDiv.className = 'element-student';
                studentDiv.textContent = siswa_array[i];
                studentColumn.appendChild(studentDiv);
              }
              
              dayElement.appendChild(studentColumn);
            }
          });
        }
        
        // Manual refresh button
        refreshDataBtn.addEventListener('click', function() {
          refreshScheduleData();
        });
        
        // Auto refresh toggle
        function setupAutoRefresh() {
          if (autoRefreshCheckbox.checked) {
            autoRefreshInterval = setInterval(refreshScheduleData, 30000); // Refresh every 30 seconds
          } else {
            clearInterval(autoRefreshInterval);
          }
        }
        
        autoRefreshCheckbox.addEventListener('change', setupAutoRefresh);
        
        // Start auto refresh if checked
        setupAutoRefresh();
        
        // Check if custom design is uploaded
        const hasCustomDesign = <?php echo isset($_SESSION['custom_design']) ? 'true' : 'false'; ?>;
        if (hasCustomDesign) {
          // Select upload template by default
          templateOptions.forEach(opt => opt.classList.remove('selected'));
          document.querySelector('[data-template="upload"]').classList.add('selected');
          defaultTemplate.style.display = 'none';
          simpleTemplate.style.display = 'none';
          uploadedTemplate.style.display = 'block';
          
          // Update body attribute for print
          document.body.setAttribute('data-active-template', 'upload');

          // Apply saved positions if available, but wait for image load so we can compute relative positions correctly
          const savedPositions = <?php echo isset($_SESSION['element_positions']) ? json_encode($_SESSION['element_positions']) : 'null'; ?>;
          if (savedPositions) {
            const bgImg = uploadedTemplate.querySelector('.template-bg');
            if (bgImg && bgImg.src) {
              if (!bgImg.complete) {
                bgImg.onload = function() { applySavedPositions(savedPositions); };
              } else {
                applySavedPositions(savedPositions);
              }
            } else {
              applySavedPositions(savedPositions);
            }
          }
        }
        
        templateOptions.forEach(option => {
          option.addEventListener('click', function() {
            // Remove selected class from all options
            templateOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Get selected template
            const selectedTemplate = this.getAttribute('data-template');
            
            // Update body attribute for print
            document.body.setAttribute('data-active-template', selectedTemplate);
            
            // Save template type to session
            fetch('cetak_jadwal.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'save_template_type=' + selectedTemplate
            });
            
            // Hide all templates
            defaultTemplate.style.display = 'none';
            simpleTemplate.style.display = 'none';
            uploadedTemplate.style.display = 'none';
            customTemplate.style.display = 'none';
            uploadSection.style.display = 'none';
            adjustmentPanel.style.display = 'none';
            bgProperties.style.display = 'none'; // Hide background properties
            
            // Show selected template
            if (selectedTemplate === 'default') {
              defaultTemplate.style.display = 'block';
              applyCustomTemplate(false);
            } else if (selectedTemplate === 'simple') {
              simpleTemplate.style.display = 'block';
              applyCustomTemplate(false);
            } else if (selectedTemplate === 'custom') {
              defaultTemplate.style.display = 'block';
              customTemplate.style.display = 'block';
              applyCustomTemplate(true);
            } else if (selectedTemplate === 'upload') {
              if (hasCustomDesign) {
                uploadedTemplate.style.display = 'block';
                adjustmentPanel.style.display = 'block'; // Show panel
                bgProperties.style.display = 'block'; // Show background properties
              } else {
                uploadSection.style.display = 'block';
              }
            }
          });
        });
        
        // Adjust positions button
        if (adjustPositionsBtn) {
          adjustPositionsBtn.addEventListener('click', function() {
            adjustmentPanel.style.display = 'block';
            bgProperties.style.display = 'block'; // Show background properties
            enableDragMode();
          });
        }
        
        // Save positions button
        if (savePositionsBtn) {
          savePositionsBtn.addEventListener('click', function() {
            const positions = {};
            
            // Get title position
            const titleElement = document.getElementById('element-title');
            positions.title = {
              top: titleElement.style.top,
              left: titleElement.style.left,
              width: titleElement.style.width,
              fontSize: document.getElementById('title-font-size').value + 'px',
              color: document.getElementById('title-color').value
            };
            
            // Get school position
            const schoolElement = document.getElementById('element-school');
            positions.school = {
              top: schoolElement.style.top,
              left: schoolElement.style.left,
              width: schoolElement.style.width,
              fontSize: document.getElementById('school-font-size').value + 'px',
              color: document.getElementById('school-color').value
            };

            // Get presisi position and content
            const presisiElement = document.getElementById('element-presisi');
            if (presisiElement) {
              positions.presisi = {
                top: presisiElement.style.top,
                left: presisiElement.style.left,
                width: presisiElement.style.width,
                fontSize: document.getElementById('presisi-font-size').value + 'px',
                color: document.getElementById('presisi-color').value,
                contentType: document.getElementById('presisi-content-type').value,
                customText: document.getElementById('presisi-custom-text').value,
                innerHTML: presisiElement.querySelector('.presisi-content') ? presisiElement.querySelector('.presisi-content').innerHTML : ''
              };
            }
            
            // Get day positions
            const days = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];
            days.forEach(day => {
              const dayElement = document.getElementById('element-day-' + day);
              if (dayElement) {
                positions['day-' + day] = {
                  top: dayElement.style.top,
                  left: dayElement.style.left,
                  width: document.getElementById('day-width').value + 'px',
                  height: document.getElementById('day-height').value + 'px',
                  fontSize: document.getElementById('day-font-size').value + 'px',
                  color: document.getElementById('day-color').value,
                  // New properties for box customization
                  bgColor: document.getElementById('day-bg-color').value,
                  borderColor: document.getElementById('day-border-color').value,
                  borderWidth: document.getElementById('day-border-width').value + 'px',
                  opacity: document.getElementById('day-opacity').value,
                  boxOnlyOpacity: document.getElementById('day-box-only-opacity').checked,
                  blur: document.getElementById('day-blur').value + 'px',
                  borderRadius: document.getElementById('day-border-radius').value + 'px'
                };
              }
            });

            // Save background blur
            if (hasCustomDesign) {
                positions.backgroundBlur = document.getElementById('bg-blur-slider').value + 'px';
            }
            
            // Save positions via AJAX
            const formData = new FormData();
            formData.append('save_positions', 'true');
            formData.append('positions', JSON.stringify(positions));
            
            fetch('cetak_jadwal.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                alert('Posisi berhasil disimpan!');
                adjustmentPanel.style.display = 'none';
                bgProperties.style.display = 'none';
                disableDragMode();
              } else {
                alert('Gagal menyimpan posisi!');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('Gagal menyimpan posisi!');
            });
          });
        }
        
        // Reset positions button
        if (resetPositionsBtn) {
          resetPositionsBtn.addEventListener('click', function() {
            if (confirm('Apakah Anda yakin ingin mereset semua posisi?')) {
              // Reset to default positions
              document.getElementById('element-title').style.top = '50px';
              document.getElementById('element-title').style.left = '0px';
              document.getElementById('element-title').style.width = '100%';
              
              document.getElementById('element-school').style.top = '120px';
              document.getElementById('element-school').style.left = '0px';
              document.getElementById('element-school').style.width = '100%';
              
              const dayPositions = [
                {id: 'element-day-senin', top: '180px', left: '50px'},
                {id: 'element-day-selasa', top: '180px', left: '300px'},
                {id: 'element-day-rabu', top: '180px', left: '550px'},
                {id: 'element-day-kamis', top: '350px', left: '50px'},
                {id: 'element-day-jumat', top: '350px', left: '300px'}
              ];
              
              dayPositions.forEach(pos => {
                const element = document.getElementById(pos.id);
                if (element) {
                  element.style.top = pos.top;
                  element.style.left = pos.left;
                  element.style.width = '200px';
                }
              });

              // Reset background blur
              const bgBlurSlider = document.getElementById('bg-blur-slider');
              const bgBlurValue = document.getElementById('bg-blur-value');
              const templateBg = document.querySelector('.template-bg');
              if (bgBlurSlider && bgBlurValue && templateBg) {
                bgBlurSlider.value = 0;
                bgBlurValue.textContent = '0px';
                templateBg.style.filter = 'none';
              }
            }
          });
        }
        
        // Element selection
        const adjustmentBtns = document.querySelectorAll('.adjustment-btn');
        const elementProperties = document.querySelectorAll('.element-properties');
        
        adjustmentBtns.forEach(btn => {
          btn.addEventListener('click', function() {
            // Remove active class from all buttons
            adjustmentBtns.forEach(b => b.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Hide all property panels
            elementProperties.forEach(panel => panel.style.display = 'none');
            
            // Show relevant property panel
            const element = this.getAttribute('data-element');
            if (element === 'title') {
              document.getElementById('title-properties').style.display = 'block';
            } else if (element === 'school') {
              document.getElementById('school-properties').style.display = 'block';
            } else if (element === 'presisi') {
              document.getElementById('presisi-properties').style.display = 'block';
            } else if (element.startsWith('day-')) {
              document.getElementById('day-properties').style.display = 'block';
            }
            
            // Highlight selected element
            highlightElement(element);
          });
        });
        
        // Property controls
        // Title font size
        const titleFontSizeSlider = document.getElementById('title-font-size');
        const titleFontSizeValue = document.getElementById('title-font-size-value');
        if (titleFontSizeSlider && titleFontSizeValue) {
          titleFontSizeSlider.addEventListener('input', function() {
            titleFontSizeValue.textContent = this.value + 'px';
            document.getElementById('element-title').style.fontSize = this.value + 'px';
          });
        }
        
        // Title color
        const titleColorPicker = document.getElementById('title-color');
        if (titleColorPicker) {
          titleColorPicker.addEventListener('input', function() {
            document.getElementById('element-title').style.color = this.value;
          });
        }
        
        // School font size
        const schoolFontSizeSlider = document.getElementById('school-font-size');
        const schoolFontSizeValue = document.getElementById('school-font-size-value');
        if (schoolFontSizeSlider && schoolFontSizeValue) {
          schoolFontSizeSlider.addEventListener('input', function() {
            schoolFontSizeValue.textContent = this.value + 'px';
            document.getElementById('element-school').style.fontSize = this.value + 'px';
          });
        }
        
        // School color
        const schoolColorPicker = document.getElementById('school-color');
        if (schoolColorPicker) {
          schoolColorPicker.addEventListener('input', function() {
            document.getElementById('element-school').style.color = this.value;
          });
        }
        
        // Day width
        const dayWidthSlider = document.getElementById('day-width');
        const dayWidthValue = document.getElementById('day-width-value');
        if (dayWidthSlider && dayWidthValue) {
          dayWidthSlider.addEventListener('input', function() {
            dayWidthValue.textContent = this.value + 'px';
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (activeBtn && activeBtn.getAttribute('data-element').startsWith('day-')) {
              const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
              if (dayElement) {
                dayElement.style.width = this.value + 'px';
              }
            }
          });
        }
        
        // Day height
        const dayHeightSlider = document.getElementById('day-height');
        const dayHeightValue = document.getElementById('day-height-value');
        if (dayHeightSlider && dayHeightValue) {
          dayHeightSlider.addEventListener('input', function() {
            dayHeightValue.textContent = this.value + 'px';
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (activeBtn && activeBtn.getAttribute('data-element').startsWith('day-')) {
              const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
              if (dayElement) {
                dayElement.style.height = this.value + 'px';
              }
            }
          });
        }
        
        // Day font size
        const dayFontSizeSlider = document.getElementById('day-font-size');
        const dayFontSizeValue = document.getElementById('day-font-size-value');
        if (dayFontSizeSlider && dayFontSizeValue) {
          dayFontSizeSlider.addEventListener('input', function() {
            dayFontSizeValue.textContent = this.value + 'px';
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (activeBtn && activeBtn.getAttribute('data-element').startsWith('day-')) {
              const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
              if (dayElement) {
                dayElement.style.fontSize = this.value + 'px';
              }
            }
          });
        }
        
        // Day color
        const dayColorPicker = document.getElementById('day-color');
        if (dayColorPicker) {
          dayColorPicker.addEventListener('input', function() {
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (activeBtn && activeBtn.getAttribute('data-element').startsWith('day-')) {
              const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
              if (dayElement) {
                dayElement.style.color = this.value;
              }
            }
          });
        }
        
        // Helper function to convert hex to RGB
        function hexToRgb(hex) {
          const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
          return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
          } : null;
        }

        // Function to update day opacity
        function updateDayOpacity() {
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (!activeBtn || !activeBtn.getAttribute('data-element').startsWith('day-')) return;

            const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
            if (!dayElement) return;

            const opacity = document.getElementById('day-opacity').value;
            const bgColor = document.getElementById('day-bg-color').value;
            const isBoxOnly = document.getElementById('day-box-only-opacity').checked;

            if (isBoxOnly) {
                // Jika centang, hanya transparankan kotak (background), bukan teks
                dayElement.style.opacity = '1'; // Pastikan teks tidak transparan
                const rgb = hexToRgb(bgColor);
                if (rgb) {
                    dayElement.style.backgroundColor = `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${opacity})`;
                }
            } else {
                // Jika tidak dicentang, transparankan seluruh elemen
                dayElement.style.opacity = opacity;
                // Kembalikan ke warna solid asli
                dayElement.style.backgroundColor = bgColor;
            }
        }

        // Day background color
        const dayBgColorPicker = document.getElementById('day-bg-color');
        if (dayBgColorPicker) {
          dayBgColorPicker.addEventListener('input', updateDayOpacity);
        }
        
        // Day border color
        const dayBorderColorPicker = document.getElementById('day-border-color');
        if (dayBorderColorPicker) {
          dayBorderColorPicker.addEventListener('input', function() {
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (activeBtn && activeBtn.getAttribute('data-element').startsWith('day-')) {
              const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
              if (dayElement) {
                dayElement.style.borderColor = this.value;
              }
            }
          });
        }
        
        // Day border width
        const dayBorderWidthSlider = document.getElementById('day-border-width');
        const dayBorderWidthValue = document.getElementById('day-border-width-value');
        if (dayBorderWidthSlider && dayBorderWidthValue) {
          dayBorderWidthSlider.addEventListener('input', function() {
            dayBorderWidthValue.textContent = this.value + 'px';
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (activeBtn && activeBtn.getAttribute('data-element').startsWith('day-')) {
              const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
              if (dayElement) {
                dayElement.style.borderWidth = this.value + 'px';
              }
            }
          });
        }
        
        // Day opacity
        const dayOpacitySlider = document.getElementById('day-opacity');
        const dayOpacityValue = document.getElementById('day-opacity-value');
        const dayBoxOnlyCheckbox = document.getElementById('day-box-only-opacity');

        if (dayOpacitySlider && dayOpacityValue) {
          dayOpacitySlider.addEventListener('input', function() {
            dayOpacityValue.textContent = Math.round(this.value * 100) + '%';
            updateDayOpacity();
          });
        }

        if (dayBoxOnlyCheckbox) {
          dayBoxOnlyCheckbox.addEventListener('change', updateDayOpacity);
        }

        // Day blur
        const dayBlurSlider = document.getElementById('day-blur');
        const dayBlurValue = document.getElementById('day-blur-value');
        if (dayBlurSlider && dayBlurValue) {
          dayBlurSlider.addEventListener('input', function() {
            dayBlurValue.textContent = this.value + 'px';
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (activeBtn && activeBtn.getAttribute('data-element').startsWith('day-')) {
              const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
              if (dayElement) {
                dayElement.style.backdropFilter = 'blur(' + this.value + 'px)';
              }
            }
          });
        }
        
        // Day border radius
        const dayBorderRadiusSlider = document.getElementById('day-border-radius');
        const dayBorderRadiusValue = document.getElementById('day-border-radius-value');
        if (dayBorderRadiusSlider && dayBorderRadiusValue) {
          dayBorderRadiusSlider.addEventListener('input', function() {
            dayBorderRadiusValue.textContent = this.value + 'px';
            const activeBtn = document.querySelector('.adjustment-btn.active');
            if (activeBtn && activeBtn.getAttribute('data-element').startsWith('day-')) {
              const dayElement = document.getElementById('element-day-' + activeBtn.getAttribute('data-element').substring(4));
              if (dayElement) {
                dayElement.style.borderRadius = this.value + 'px';
              }
            }
          });
        }

        // Background blur control
        const bgBlurSlider = document.getElementById('bg-blur-slider');
        const bgBlurValue = document.getElementById('bg-blur-value');
        const templateBg = document.querySelector('.template-bg');

        if (bgBlurSlider && bgBlurValue && templateBg) {
            bgBlurSlider.addEventListener('input', function() {
                bgBlurValue.textContent = this.value + 'px';
                templateBg.style.filter = 'blur(' + this.value + 'px)';
            });
        }
        
        // Drag and drop functionality
        let isDragging = false;
        let currentElement = null;
        let offset = { x: 0, y: 0 };
        
        function enableDragMode() {
          const draggableElements = document.querySelectorAll('.draggable-element');
          
          draggableElements.forEach(element => {
            element.addEventListener('mousedown', startDrag);
            element.addEventListener('touchstart', startDrag);
          });
          
          document.addEventListener('mousemove', drag);
          document.addEventListener('touchmove', drag);
          document.addEventListener('mouseup', endDrag);
          document.addEventListener('touchend', endDrag);
        }
        
        function disableDragMode() {
          const draggableElements = document.querySelectorAll('.draggable-element');
          
          draggableElements.forEach(element => {
            element.removeEventListener('mousedown', startDrag);
            element.removeEventListener('touchstart', startDrag);
          });
          
          document.removeEventListener('mousemove', drag);
          document.removeEventListener('touchmove', drag);
          document.removeEventListener('mouseup', endDrag);
          document.removeEventListener('touchend', endDrag);
        }
        
        function startDrag(e) {
          isDragging = true;
          currentElement = e.target.closest('.draggable-element');
          
          const rect = currentElement.getBoundingClientRect();
          const clientX = e.clientX || e.touches[0].clientX;
          const clientY = e.clientY || e.touches[0].clientY;
          
          offset.x = clientX - rect.left;
          offset.y = clientY - rect.top;
          
          currentElement.style.zIndex = 1000;
          positionIndicator.style.display = 'block';
          // initialize indicator using computed bounding box
          const containerRectInit = uploadedTemplate.getBoundingClientRect();
          const elRectInit = currentElement.getBoundingClientRect();
          const leftInit = elRectInit.left - containerRectInit.left;
          const topInit = elRectInit.top - containerRectInit.top;
          positionIndicator.style.left = (leftInit / containerRectInit.width * 100) + '%';
          positionIndicator.style.top = (topInit / containerRectInit.height * 100) + '%';
          positionIndicator.style.width = (elRectInit.width / containerRectInit.width * 100) + '%';
          positionIndicator.style.height = (elRectInit.height / containerRectInit.height * 100) + '%';
          
          e.preventDefault();
        }
        
        function drag(e) {
          if (!isDragging || !currentElement) return;
          const containerRect = uploadedTemplate.getBoundingClientRect();
          const clientX = e.clientX || (e.touches && e.touches[0] && e.touches[0].clientX);
          const clientY = e.clientY || (e.touches && e.touches[0] && e.touches[0].clientY);

          let newLeft = clientX - containerRect.left - offset.x;
          let newTop = clientY - containerRect.top - offset.y;

          // Constrain to container
          newLeft = Math.max(0, Math.min(newLeft, containerRect.width - currentElement.offsetWidth));
          newTop = Math.max(0, Math.min(newTop, containerRect.height - currentElement.offsetHeight));

          // Convert to percentages so positions stay relative to image size
          const leftPct = (newLeft / containerRect.width) * 100;
          const topPct = (newTop / containerRect.height) * 100;
          currentElement.style.left = leftPct + '%';
          currentElement.style.top = topPct + '%';

          // Keep width responsive as percentage of container
          const widthPct = (currentElement.offsetWidth / containerRect.width) * 100;
          currentElement.style.width = widthPct + '%';

          // Update position indicator similarly
          positionIndicator.style.top = currentElement.style.top;
          positionIndicator.style.left = currentElement.style.left;
          positionIndicator.style.width = currentElement.style.width;
          // height as percent
          positionIndicator.style.height = (currentElement.offsetHeight / containerRect.height * 100) + '%';

          e.preventDefault();
        }
        
        function endDrag() {
          if (!isDragging) return;
          
          isDragging = false;
          if (currentElement) {
            currentElement.style.zIndex = '';
            currentElement = null;
          }
          positionIndicator.style.display = 'none';
        }
        
        function highlightElement(elementId) {
          // Remove all highlights
          document.querySelectorAll('.draggable-element').forEach(el => {
            el.style.outline = '';
          });
          
          // Highlight selected element
          if (elementId === 'title') {
            document.getElementById('element-title').style.outline = '2px dashed #ff0000';
          } else if (elementId === 'school') {
            document.getElementById('element-school').style.outline = '2px dashed #ff0000';
          } else if (elementId === 'presisi') {
            const p = document.getElementById('element-presisi');
            if (p) p.style.outline = '2px dashed #ff0000';
          } else if (elementId.startsWith('day-')) {
            const dayElement = document.getElementById('element-day-' + elementId.substring(4));
            if (dayElement) {
              dayElement.style.outline = '2px dashed #ff0000';
            }
          }
        }
        
        function applySavedPositions(positions) {
          // Apply title position
          if (positions.title) {
            const titleElement = document.getElementById('element-title');
            titleElement.style.top = positions.title.top;
            titleElement.style.left = positions.title.left;
            titleElement.style.width = positions.title.width;
            titleElement.style.fontSize = positions.title.fontSize;
            titleElement.style.color = positions.title.color;
          }
          
          // Apply school position
          if (positions.school) {
            const schoolElement = document.getElementById('element-school');
            schoolElement.style.top = positions.school.top;
            schoolElement.style.left = positions.school.left;
            schoolElement.style.width = positions.school.width;
            schoolElement.style.fontSize = positions.school.fontSize;
            schoolElement.style.color = positions.school.color;
          }
          
          // Apply day positions
          const days = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];
          days.forEach(day => {
            const dayKey = 'day-' + day;
            if (positions[dayKey]) {
              const dayElement = document.getElementById('element-day-' + day);
              if (dayElement) {
                dayElement.style.top = positions[dayKey].top;
                dayElement.style.left = positions[dayKey].left;
                dayElement.style.width = positions[dayKey].width;
                dayElement.style.height = positions[dayKey].height;
                dayElement.style.fontSize = positions[dayKey].fontSize;
                dayElement.style.color = positions[dayKey].color;
                
                // Apply new box properties if available
                if (positions[dayKey].bgColor) {
                  dayElement.style.backgroundColor = positions[dayKey].bgColor;
                }
                if (positions[dayKey].borderColor) {
                  dayElement.style.borderColor = positions[dayKey].borderColor;
                }
                if (positions[dayKey].borderWidth) {
                  dayElement.style.borderWidth = positions[dayKey].borderWidth;
                }
                if (positions[dayKey].opacity) {
                  dayElement.style.opacity = positions[dayKey].opacity;
                }
                if (positions[dayKey].blur) {
                  dayElement.style.backdropFilter = 'blur(' + positions[dayKey].blur + ')';
                }
                if (positions[dayKey].borderRadius) {
                  dayElement.style.borderRadius = positions[dayKey].borderRadius;
                }
                if (positions[dayKey].boxOnlyOpacity !== undefined) {
                  document.getElementById('day-box-only-opacity').checked = positions[dayKey].boxOnlyOpacity;
                }
                // Trigger update to apply box-only opacity correctly after load
                if (document.getElementById('day-box-only-opacity').checked) {
                   updateDayOpacity();
                }
              }
            }
          });

          // Apply background blur
          if (positions.backgroundBlur) {
            const templateBg = document.querySelector('.template-bg');
            const bgBlurSlider = document.getElementById('bg-blur-slider');
            const bgBlurValue = document.getElementById('bg-blur-value');
            if (templateBg) {
              templateBg.style.filter = 'blur(' + positions.backgroundBlur + ')';
            }
            if (bgBlurSlider && bgBlurValue) {
              bgBlurSlider.value = parseFloat(positions.backgroundBlur);
              bgBlurValue.textContent = positions.backgroundBlur;
            }
          }

          // Apply presisi position and content
          if (positions.presisi) {
            const pres = document.getElementById('element-presisi');
            if (pres) {
              pres.style.top = positions.presisi.top;
              pres.style.left = positions.presisi.left;
              pres.style.width = positions.presisi.width;
              pres.style.fontSize = positions.presisi.fontSize;
              pres.style.color = positions.presisi.color;
              // Apply content type/custom if available
              const contentType = positions.presisi.contentType || 'school';
              const customText = positions.presisi.customText || '';
              const inner = positions.presisi.innerHTML || '';
              const target = pres.querySelector('.presisi-content');
              if (target) {
                if (contentType === 'school') {
                  target.innerHTML = document.querySelector('.default-template .school-name').textContent;
                } else if (contentType === 'creator') {
                  target.innerHTML = '<small>Dibuat oleh Moh. Yazidur Rizky</small>';
                } else if (contentType === 'custom') {
                  target.innerHTML = customText || inner || '';
                } else if (inner) {
                  target.innerHTML = inner;
                }
              }
              // Sync controls
              const presFont = document.getElementById('presisi-font-size');
              const presFontVal = document.getElementById('presisi-font-size-value');
              const presColor = document.getElementById('presisi-color');
              const presType = document.getElementById('presisi-content-type');
              const presCustom = document.getElementById('presisi-custom-text');
              if (presFont) presFont.value = parseInt(positions.presisi.fontSize) || 18;
              if (presFontVal) presFontVal.textContent = (presFont.value || 18) + 'px';
              if (presColor) presColor.value = positions.presisi.color || '#000000';
              if (presType) presType.value = contentType;
              if (presCustom) presCustom.value = customText;
            }
          }
        }

        // Presisi controls
        const presisiFontSizeSlider = document.getElementById('presisi-font-size');
        const presisiFontSizeValue = document.getElementById('presisi-font-size-value');
        if (presisiFontSizeSlider && presisiFontSizeValue) {
          presisiFontSizeSlider.addEventListener('input', function() {
            presisiFontSizeValue.textContent = this.value + 'px';
            const elem = document.getElementById('element-presisi');
            if (elem) elem.style.fontSize = this.value + 'px';
          });
        }

        const presisiColorPicker = document.getElementById('presisi-color');
        if (presisiColorPicker) {
          presisiColorPicker.addEventListener('input', function() {
            const elem = document.getElementById('element-presisi');
            if (elem) elem.style.color = this.value;
          });
        }

        const presisiContentType = document.getElementById('presisi-content-type');
        const presisiCustomText = document.getElementById('presisi-custom-text');
        function updatePresisiContent() {
          const elem = document.getElementById('element-presisi');
          const target = elem ? elem.querySelector('.presisi-content') : null;
          if (!target) return;
          const type = presisiContentType.value;
          if (type === 'school') {
            target.innerHTML = document.querySelector('.default-template .school-name').textContent;
          } else if (type === 'creator') {
            target.innerHTML = '<small>Dibuat oleh Moh. Yazidur Rizky</small>';
          } else if (type === 'custom') {
            target.innerHTML = presisiCustomText.value || '';
          }
        }
        if (presisiContentType) {
          presisiContentType.addEventListener('change', updatePresisiContent);
        }
        if (presisiCustomText) {
          presisiCustomText.addEventListener('input', function() {
            if (presisiContentType.value === 'custom') updatePresisiContent();
          });
        }
        
        // Apply custom template settings
        function applyCustomTemplate(isCustom) {
          if (!isCustom) return;
          
          const customTitle = document.getElementById('custom-title').value;
          const customColor = document.getElementById('custom-color').value;
          const customSchool = document.getElementById('custom-school').value;
          const showIcons = document.getElementById('custom-icons').checked;
          const showFooter = document.getElementById('custom-footer').checked;
          
          // Apply custom title
          document.querySelector('.default-template .schedule-title h2').textContent = customTitle;
          
          // Apply custom color
          document.documentElement.style.setProperty('--custom-color', customColor);
          const style = document.createElement('style');
          style.innerHTML = `
            .default-template .schedule-container {
              border-color: ${customColor};
            }
            .default-template .schedule-title h2 {
              color: ${customColor};
            }
            .default-template .day-card {
              border-color: ${customColor};
              background-color: ${customColor}20;
            }
            .default-template .day-title {
              background-color: ${customColor};
            }
            .default-template .cleaning-icon,
            .default-template .recycle-icon {
              color: ${customColor};
            }
            .default-template .school-name {
              color: ${customColor};
            }
          `;
          document.head.appendChild(style);
          
          // Apply custom school name
          document.querySelector('.default-template .school-name').textContent = customSchool;
          
          // Show/hide icons
          const icons = document.querySelectorAll('.default-template .cleaning-icon, .default-template .recycle-icon, .default-template .decoration');
          icons.forEach(icon => {
            icon.style.display = showIcons ? 'block' : 'none';
          });
          
          // Show/hide footer
          footer.style.display = showFooter ? 'block' : 'none';
        }
        
        // Apply custom template when settings change
        document.getElementById('custom-title').addEventListener('input', function() {
          if (document.querySelector('[data-template="custom"]').classList.contains('selected')) {
            applyCustomTemplate(true);
          }
        });
        
        document.getElementById('custom-color').addEventListener('change', function() {
          if (document.querySelector('[data-template="custom"]').classList.contains('selected')) {
            applyCustomTemplate(true);
          }
        });
        
        document.getElementById('custom-school').addEventListener('input', function() {
          if (document.querySelector('[data-template="custom"]').classList.contains('selected')) {
            applyCustomTemplate(true);
          }
        });
        
        document.getElementById('custom-icons').addEventListener('change', function() {
          if (document.querySelector('[data-template="custom"]').classList.contains('selected')) {
            applyCustomTemplate(true);
          }
        });
        
        document.getElementById('custom-footer').addEventListener('change', function() {
          if (document.querySelector('[data-template="custom"]').classList.contains('selected')) {
            applyCustomTemplate(true);
          }
        });

        // Apply custom settings button
        document.getElementById('apply-custom-settings').addEventListener('click', function() {
          const customTitle = document.getElementById('custom-title').value;
          const customColor = document.getElementById('custom-color').value;
          const customSchool = document.getElementById('custom-school').value;
          const showIcons = document.getElementById('custom-icons').checked;
          const showFooter = document.getElementById('custom-footer').checked;
          
          // Save settings via AJAX
          fetch('cetak_jadwal.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'save_custom_settings=true&custom_title=' + encodeURIComponent(customTitle) + '&custom_color=' + encodeURIComponent(customColor) + '&custom_school=' + encodeURIComponent(customSchool) + '&custom_icons=' + (showIcons ? 'true' : 'false') + '&custom_footer=' + (showFooter ? 'true' : 'false')
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showNotification('Pengaturan kustom berhasil disimpan!', 'success');
              // Apply settings immediately
              applyCustomTemplate(true);
            } else {
              showNotification('Gagal menyimpan pengaturan kustom!', 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan saat menyimpan pengaturan!', 'error');
          });
        });
      });
    </script>
  </body>
</html>