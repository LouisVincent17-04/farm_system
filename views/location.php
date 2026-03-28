<?php
// views/location.php
error_reporting(0);
ini_set('display_errors', 0);

$page = "admin_dashboard";
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('location');
include '../common/navbar.php';
include '../common/chat_support.php';

if($_SESSION['user']['USER_TYPE'] < 3) {
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }
    $sql = "SELECT * FROM Locations ORDER BY LOCATION_ID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $location_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $location_data = [];
    $status = 'error';
    $msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Location Management System</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(16,185,129,0.5); /* Emerald Accent */
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--emerald); background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--emerald), #059669);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: var(--radius-md); font-size: 0.9rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent;
            cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: var(--emerald); color: #000; }
        .btn-primary:hover { background: #34d399; box-shadow: 0 0 16px var(--emerald-glow); transform: translateY(-1px); }
        .btn-gps { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(56,189,248,0.2); font-size: 0.8rem; padding: 8px 12px; }
        .btn-gps:hover { background: var(--blue); color: #000; }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── SEARCH ─── */
        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px; }
        .search-input {
            width: 100%; padding: 14px 14px 14px 2.8rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg);
            color: var(--text-primary); font-size: 1rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        .col-id { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem; }
        .col-name { font-weight: 600; color: #fff; }
        .col-coord { font-family: var(--font-mono); color: var(--blue); font-size: 0.85rem; }

        /* Actions */
        .actions { display: flex; gap: 8px; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary);
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.view:hover { color: var(--blue); border-color: var(--blue); }
        .action-btn.edit:hover { color: var(--emerald); border-color: var(--emerald); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 650px;
            max-height: 95vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--emerald); }
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        /* Form Layout */
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); }

        /* Map UI */
        #map, #viewMap { height: 300px; width: 100%; border-radius: 12px; border: 1px solid var(--border); margin: 10px 0; z-index: 1; }
        .location-controls { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .location-search { flex: 1; margin-bottom: 0; }
        .search-results {
            position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: 8px; z-index: 100; max-height: 200px;
            overflow-y: auto; display: none; box-shadow: var(--shadow-md);
        }
        .search-result { padding: 10px; cursor: pointer; font-size: 0.85rem; border-bottom: 1px solid var(--border); }
        .search-result:hover { background: var(--bg-hover); color: var(--emerald); }
        
        .location-info { background: var(--bg-elevated); padding: 12px; border-radius: 8px; border-left: 3px solid var(--emerald); margin-top: 10px; }
        .location-info-item { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 4px; }
        .location-info-label { color: var(--text-muted); font-weight: 600; }
        .location-info-value { font-family: var(--font-mono); color: var(--text-primary); }

        /* Alerts */
        .alert-box { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert-success { background: var(--emerald-dim); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--emerald); }
        .alert-error { background: var(--red-dim); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--red); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-buttons, .btn-primary { width: 100%; }
            .table-wrap { border: none; background: transparent; }
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
            }
            td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            td:last-child { border-bottom: none; justify-content: center; padding-top: 1rem; }
            td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
            .actions { justify-content: flex-end; width: 100%; }
            .location-controls { flex-direction: column; }
            .btn-gps { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-map-pin"></i> Geography</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Location <span>Management</span></h1>
            <p>Define and manage physical farm sites and geographic data.</p>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Add New Location
        </button>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert-box alert-<?php echo htmlspecialchars($status); ?>">
            <i class="fa-solid <?php echo ($status == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> me-2"></i>
            <?php echo htmlspecialchars(urldecode($msg)); ?>
        </div>
    <?php endif; ?>

    <div class="search-container">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" class="search-input" placeholder="Search locations by name or address..." onkeyup="filterTable()">
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 100px;">ID</th>
                        <th>Location Name</th>
                        <th>Address</th>
                        <th>Coordinates</th>
                        <th style="text-align: center; width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="location-table">
                    <?php foreach($location_data as $data): ?>
                    <tr data-id="<?php echo $data['LOCATION_ID']; ?>" 
                        data-lat="<?php echo $data['LATITUDE']; ?>" 
                        data-lon="<?php echo $data['LONGITUDE']; ?>"
                        data-address="<?php echo htmlspecialchars($data['COMPLETE_ADDRESS'] ?? ''); ?>"
                        data-city="<?php echo htmlspecialchars($data['CITY'] ?? ''); ?>"
                        data-province="<?php echo htmlspecialchars($data['PROVINCE'] ?? ''); ?>">
                        
                        <td data-label="ID" class="col-id">#<?php echo $data['LOCATION_ID']; ?></td>
                        <td data-label="Location Name" class="col-name"><?php echo htmlspecialchars($data['LOCATION_NAME']); ?></td>
                        <td data-label="Address">
                            <span class="location-address" style="color: var(--text-secondary);">
                                <?php echo !empty($data['COMPLETE_ADDRESS']) ? htmlspecialchars($data['COMPLETE_ADDRESS']) : '-'; ?>
                            </span>
                        </td>
                        <td data-label="Coordinates" class="col-coord">
                            <?php 
                            if(!empty($data['LATITUDE']) && !empty($data['LONGITUDE'])) {
                                echo number_format($data['LATITUDE'], 4) . ', ' . number_format($data['LONGITUDE'], 4);
                            } else { echo '-'; }
                            ?>
                        </td>
                        <td data-label="Actions">
                            <div class="actions">
                                <button class="action-btn view" onclick="viewLocation(this)" title="View on Map"><i class="fas fa-eye"></i></button>
                                <button class="action-btn edit" onclick="editLocation(this)" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="action-btn delete" onclick="deleteLocation(this)" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state" style="<?php echo empty($location_data) ? 'display:block' : 'display:none'; ?>">
                <i class="fa-solid fa-map-location-dot"></i>
                <h3>No locations found</h3>
                <p>Add your first farm site to get started.</p>
            </div>
        </div>
    </div>
</div>

<div id="locationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Add New Location</h2>
        </div>
        <div class="modal-body">
            <form id="locationForm" method="POST" action="../process/addLocation.php">
                <input type="hidden" id="location_id" name="location_id">
                
                <div class="form-group">
                    <label class="form-label">Location Name *</label>
                    <input type="text" class="form-control" id="location_name" name="location_name" placeholder="e.g. North Pasture" required>
                </div>

                <div class="location-controls">
                    <button type="button" class="btn btn-gps" id="gpsBtn" onclick="getCurrentLocation()">
                        <i class="fas fa-location-arrow"></i> Use My GPS
                    </button>
                    <div class="search-container location-search">
                        <input type="text" class="form-control" id="locationSearch" placeholder="Search address...">
                        <div class="search-results" id="searchResults"></div>
                    </div>
                </div>
                
                <div id="map"></div>
                
                <div class="location-info">
                    <div class="location-info-item">
                        <span class="location-info-label">Coordinates:</span>
                        <span class="location-info-value" id="loc-coordinates">Not set</span>
                    </div>
                </div>

                <div style="margin-top: 15px; padding: 1.25rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-lg);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <label class="form-label" style="margin:0;">Geographic Details</label>
                        <button type="button" class="btn btn-gps" onclick="fetchAddressFromCoordinates()" id="fetchAddressBtn" style="padding: 4px 10px; font-size: 11px;">
                            <i class="fas fa-magic"></i> Auto-Fill
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Address</label>
                        <input type="text" class="form-control" id="manual_address" name="address" placeholder="Street, Barangay...">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" id="manual_city" name="city">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Province</label>
                            <input type="text" class="form-control" id="manual_province" name="province">
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="latitude" name="latitude">
                <input type="hidden" id="longitude" name="longitude">
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitForm()" id="saveBtn">Save Site</button>
        </div>
    </div>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="view-title">Location Overview</h2>
        </div>
        <div class="modal-body">
            <div id="viewMap"></div>
            <div class="location-info">
                <div class="location-info-item">
                    <span class="location-info-label">Name:</span>
                    <span class="location-info-value" id="view-name">-</span>
                </div>
                <div class="location-info-item">
                    <span class="location-info-label">Coords:</span>
                    <span class="location-info-value" id="view-coordinates">-</span>
                </div>
                <div class="location-info-item">
                    <span class="location-info-label">Address:</span>
                    <span class="location-info-value" id="view-address" style="font-family: inherit; font-size: 0.8rem; text-align: right; line-height: 1.4;">-</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<form id="deleteLocationForm" method="POST" action="../process/deleteLocation.php" style="display: none;">
    <input type="hidden" id="delete_location_id" name="location_id">
</form>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let map, viewMap, marker, searchTimeout;
    let isEditMode = false;

    function openAddModal() {
        isEditMode = false;
        document.getElementById('modal-title').textContent = 'Add New Location';
        document.getElementById('locationForm').reset();
        document.getElementById('locationForm').action = '../process/addLocation.php';
        document.getElementById('location_id').value = '';
        document.getElementById('saveBtn').textContent = 'Save Location';
        document.getElementById('loc-coordinates').textContent = 'Not set';
        document.getElementById('locationModal').classList.add('show');
        
        setTimeout(() => {
            if (!map) { initMap(); } 
            else {
                map.invalidateSize();
                if (marker) { map.removeLayer(marker); marker = null; }
                map.setView([10.250608, 123.949470], 13);
            }
        }, 100);
    }

    function editLocation(btn) {
        isEditMode = true;
        const row = btn.closest('tr');
        const latStr = row.getAttribute('data-lat');
        const lonStr = row.getAttribute('data-lon');

        document.getElementById('modal-title').textContent = 'Edit Site Details';
        document.getElementById('locationForm').action = '../process/updateLocation.php';
        document.getElementById('saveBtn').textContent = 'Update Site';
        document.getElementById('location_id').value = row.getAttribute('data-id');
        document.getElementById('location_name').value = row.querySelector('.col-name').textContent.trim();
        document.getElementById('manual_address').value = row.getAttribute('data-address');
        document.getElementById('manual_city').value = row.getAttribute('data-city');
        document.getElementById('manual_province').value = row.getAttribute('data-province');
        document.getElementById('latitude').value = latStr;
        document.getElementById('longitude').value = lonStr;

        if (latStr && lonStr) {
            document.getElementById('loc-coordinates').textContent = `${parseFloat(latStr).toFixed(6)}, ${parseFloat(lonStr).toFixed(6)}`;
        }
        
        document.getElementById('locationModal').classList.add('show');
        
        setTimeout(() => {
            if (!map) { initMap(); } else { map.invalidateSize(); }
            if (latStr && lonStr) {
                const lat = parseFloat(latStr), lon = parseFloat(lonStr);
                map.setView([lat, lon], 16);
                placeMarker(lat, lon);
            }
        }, 100);
    }

    function initMap() {
        map = L.map('map', { zoomControl: false }).setView([10.250608, 123.949470], 13);
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM' }).addTo(map);
        map.on('click', (e) => placeMarker(e.latlng.lat, e.latlng.lng));
    }

    function placeMarker(lat, lon) {
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lon], { draggable: true }).addTo(map);
        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            placeMarker(pos.lat, pos.lng);
        });
        document.getElementById('loc-coordinates').textContent = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lon;
    }

    async function fetchAddressFromCoordinates() {
        const lat = document.getElementById('latitude').value;
        const lon = document.getElementById('longitude').value;
        if (!lat) return alert('Select a point on the map first');
        
        const btn = document.getElementById('fetchAddressBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`);
            const data = await res.json();
            if (data.address) {
                document.getElementById('manual_address').value = data.display_name;
                document.getElementById('manual_city').value = data.address.city || data.address.town || '';
                document.getElementById('manual_province').value = data.address.state || '';
            }
        } catch (e) { alert('Geocoding service busy. Please try again.'); }
        btn.innerHTML = '<i class="fas fa-magic"></i> Auto-Fill';
        btn.disabled = false;
    }

    function getCurrentLocation() {
        if (!navigator.geolocation) return alert('GPS not supported');
        navigator.geolocation.getCurrentPosition((p) => {
            map.setView([p.coords.latitude, p.coords.longitude], 16);
            placeMarker(p.coords.latitude, p.coords.longitude);
        });
    }

    function viewLocation(btn) {
        const row = btn.closest('tr');
        const lat = parseFloat(row.getAttribute('data-lat')), lon = parseFloat(row.getAttribute('data-lon'));
        document.getElementById('view-name').textContent = row.querySelector('.col-name').textContent;
        document.getElementById('view-address').textContent = row.getAttribute('data-address') || '-';
        document.getElementById('view-coordinates').textContent = lat ? `${lat.toFixed(6)}, ${lon.toFixed(6)}` : 'Not set';
        document.getElementById('viewModal').classList.add('show');
        setTimeout(() => {
            if (!viewMap) {
                viewMap = L.map('viewMap', { zoomControl: false });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(viewMap);
            }
            viewMap.invalidateSize();
            if (lat) {
                viewMap.setView([lat, lon], 16);
                L.marker([lat, lon]).addTo(viewMap);
            }
        }, 100);
    }

    function deleteLocation(btn) {
        const id = btn.closest('tr').getAttribute('data-id');
        const name = btn.closest('tr').querySelector('.col-name').textContent;
        if (confirm(`Permanently remove site: ${name}?`)) {
            document.getElementById('delete_location_id').value = id;
            document.getElementById('deleteLocationForm').submit();
        }
    }

    function filterTable() {
        const term = document.querySelector('.search-input').value.toLowerCase();
        const rows = document.querySelectorAll('#location-table tr');
        let count = 0;
        rows.forEach(r => {
            const match = r.textContent.toLowerCase().includes(term);
            r.style.display = match ? '' : 'none';
            if(match) count++;
        });
        document.getElementById('empty-state').style.display = count ? 'none' : 'block';
    }

    function closeModal() { document.getElementById('locationModal').classList.remove('show'); }
    function closeViewModal() { document.getElementById('viewModal').classList.remove('show'); }
    function submitForm() { document.getElementById('locationForm').submit(); }

    window.addEventListener('click', (e) => { if (e.target.classList.contains('modal')) { closeModal(); closeViewModal(); }});
</script>
</body>
</html>