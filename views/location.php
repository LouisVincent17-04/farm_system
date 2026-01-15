<?php
// views/location.php
error_reporting(0);
ini_set('display_errors', 0);

$page = "admin_dashboard";
include '../common/navbar.php';
include '../config/Connection.php';
// include '../config/Queries.php'; // Not needed for direct PDO
include '../security/checkRole.php';    
checkRole(3);

// Check for status messages
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Management System</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/location.css">
    <style>
        /* Add alert styles inline if not present in CSS file */
        .alert-box { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; font-weight: 500; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #6ee7b7; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }
    </style>
</head>
<body>

<div class="container">
    <?php if (!empty($msg)): ?>
        <div class="alert-box alert-<?php echo htmlspecialchars($status); ?>">
            <?php echo htmlspecialchars(urldecode($msg)); ?>
        </div>
    <?php endif; ?>

    <div class="header">
        <div class="header-info">
            <h1><i class="fas fa-map-marker-alt"></i> Location Management</h1>
            <p>Manage Locations and their geographic information</p>
        </div>
        <button class="add-btn" onclick="openAddModal()">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Location
        </button>
    </div>

    

    <div class="search-container">
        <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        <input type="text" class="search-input" placeholder="Search locations by name or address..." onkeyup="filterTable()">
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Location ID</th>
                    <th>Location Name</th>
                    <th>Address</th>
                    <th>Coordinates</th>
                    <th style="text-align: center;">Actions</th>
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
                    
                    <td><?php echo $data['LOCATION_ID']; ?></td>
                    <td>
                        <div class="location-name"><?php echo htmlspecialchars($data['LOCATION_NAME']); ?></div>
                    </td>
                    <td>
                        <div class="location-address">
                            <?php echo !empty($data['COMPLETE_ADDRESS']) ? htmlspecialchars($data['COMPLETE_ADDRESS']) : '-'; ?>
                        </div>
                    </td>
                    <td>
                        <div class="location-address">
                            <?php 
                            if(!empty($data['LATITUDE']) && !empty($data['LONGITUDE'])) {
                                echo number_format($data['LATITUDE'], 6) . ', ' . number_format($data['LONGITUDE'], 6);
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </td>
                    <td>
                        <div class="actions">
                            <button class="action-btn view" onclick="viewLocation(this)" title="View on Map">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn edit" onclick="editLocation(this)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete" onclick="deleteLocation(this)" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="empty-state" class="empty-state" style="<?php echo empty($location_data) ? 'display:block' : 'display:none'; ?>">
            <h3>No locations found</h3>
            <p>Try adjusting your search terms</p>
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
                    <label for="location_name">Location Name</label>
                    <input type="text" id="location_name" name="location_name" placeholder="Enter location name" required>
                </div>

                <div class="location-controls">
                    <button type="button" class="btn-gps" id="gpsBtn" onclick="getCurrentLocation()">
                        <i class="fas fa-location-arrow"></i> Use My Location
                    </button>
                    <div class="search-container location-search">
                        <input type="text" class="search-input" id="locationSearch" placeholder="Search location...">
                        <div class="search-results" id="searchResults"></div>
                    </div>
                </div>
                
                <div id="map"></div>
                
                <div class="location-info">
                    <div class="location-info-item">
                        <span class="location-info-label">Coordinates:</span>
                        <span class="location-info-value" id="loc-coordinates">Click on map to set location</span>
                    </div>
                </div>

                <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <label style="font-weight: 500; color: #333;">Address Information (Optional)</label>
                        <button type="button" class="btn-gps" onclick="fetchAddressFromCoordinates()" id="fetchAddressBtn" style="padding: 6px 12px; font-size: 13px;">
                            <i class="fas fa-sync"></i> Auto-Fill Address
                        </button>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label for="manual_address" style="font-size: 13px; color: #666;">Complete Address</label>
                        <input type="text" id="manual_address" name="address" placeholder="Enter address manually or click Auto-Fill" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label for="manual_city" style="font-size: 13px; color: #666;">City</label>
                            <input type="text" id="manual_city" name="city" placeholder="City" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div class="form-group">
                            <label for="manual_province" style="font-size: 13px; color: #666;">Province</label>
                            <input type="text" id="manual_province" name="province" placeholder="Province" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="latitude" name="latitude">
                <input type="hidden" id="longitude" name="longitude">
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn-save" onclick="submitForm()" id="saveBtn">Save Location</button>
        </div>
    </div>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>View Location</h2>
        </div>
        <div class="modal-body">
            <div id="viewMap"></div>
            <div class="location-info">
                <div class="location-info-item">
                    <span class="location-info-label">Location Name:</span>
                    <span class="location-info-value" id="view-name">-</span>
                </div>
                <div class="location-info-item">
                    <span class="location-info-label">Coordinates:</span>
                    <span class="location-info-value" id="view-coordinates">-</span>
                </div>
                <div class="location-info-item">
                    <span class="location-info-label">Address:</span>
                    <span class="location-info-value" id="view-address">-</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<form id="deleteLocationForm" method="POST" action="../process/deleteLocation.php" style="display: none;">
    <input type="hidden" id="delete_location_id" name="location_id">
</form>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let map;
    let viewMap;
    let marker;
    let searchTimeout;
    let isEditMode = false;

    function openAddModal() {
        isEditMode = false;
        document.getElementById('modal-title').textContent = 'Add New Location';
        
        document.getElementById('locationForm').reset();
        document.getElementById('locationForm').action = '../process/addLocation.php';
        document.getElementById('location_id').value = '';
        document.getElementById('saveBtn').textContent = 'Save Location';
        
        document.getElementById('loc-coordinates').textContent = 'Click on map to set location';
        
        document.getElementById('latitude').value = '';
        document.getElementById('longitude').value = '';
        document.getElementById('manual_address').value = '';
        document.getElementById('manual_city').value = '';
        document.getElementById('manual_province').value = '';
        
        document.getElementById('locationModal').classList.add('show');
        
        setTimeout(() => {
            if (!map) {
                initMap();
            } else {
                map.invalidateSize();
                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                }
                map.setView([10.250608982512142, 123.94947052001955], 13);
            }
        }, 100);
    }

    function editLocation(btn) {
        isEditMode = true;
        const row = btn.closest('tr');
        
        const locationId = row.getAttribute('data-id');
        const locationName = row.querySelector('.location-name').textContent.trim();
        const address = row.getAttribute('data-address');
        const city = row.getAttribute('data-city');
        const province = row.getAttribute('data-province');
        const latStr = row.getAttribute('data-lat');
        const lonStr = row.getAttribute('data-lon');

        document.getElementById('modal-title').textContent = 'Edit Location';
        document.getElementById('locationForm').action = '../process/updateLocation.php';
        document.getElementById('saveBtn').textContent = 'Update Location';
        
        document.getElementById('location_id').value = locationId;
        document.getElementById('location_name').value = locationName;

        document.getElementById('latitude').value = latStr;
        document.getElementById('longitude').value = lonStr;
        document.getElementById('manual_address').value = address;
        document.getElementById('manual_city').value = city;
        document.getElementById('manual_province').value = province;

        if (latStr && lonStr) {
            const lat = parseFloat(latStr);
            const lon = parseFloat(lonStr);
            document.getElementById('loc-coordinates').textContent = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
        } else {
            document.getElementById('loc-coordinates').textContent = 'Not set';
        }
        
        document.getElementById('locationModal').classList.add('show');
        
        setTimeout(() => {
            if (!map) {
                initMap();
            } else {
                map.invalidateSize();
                if (marker) {
                    map.removeLayer(marker);
                }
            }
            
            if (latStr && lonStr) {
                const lat = parseFloat(latStr);
                const lon = parseFloat(lonStr);
                if (!isNaN(lat) && !isNaN(lon)) {
                    map.setView([lat, lon], 16);
                    placeMarker(lat, lon);
                }
            } else {
                map.setView([10.250608982512142, 123.94947052001955], 13);
            }
        }, 100);
    }

    function closeModal() {
        document.getElementById('locationModal').classList.remove('show');
    }

    function initMap() {
        const defaultLat = 10.250608982512142;
        const defaultLon = 123.94947052001955;

        map = L.map('map').setView([defaultLat, defaultLon], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        map.on('click', function(e) {
            placeMarker(e.latlng.lat, e.latlng.lng);
        });
    }

    function placeMarker(lat, lon) {
        if (marker) {
            map.removeLayer(marker);
        }

        marker = L.marker([lat, lon], { 
            draggable: true,
            riseOnHover: true 
        }).addTo(map);

        marker.on('dragend', function() {
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

        if (!lat || !lon) {
            alert('Please select a location on the map first');
            return;
        }

        const fetchBtn = document.getElementById('fetchAddressBtn');
        const originalHTML = fetchBtn.innerHTML;
        fetchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        fetchBtn.disabled = true;

        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}&addressdetails=1`,
                {
                    headers: { 
                        'User-Agent': 'LocationManagementSystem/1.0',
                        'Accept': 'application/json'
                    }
                }
            );
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            if (data.address) {
                const address = data.display_name || '';
                const city = data.address.city || data.address.town || data.address.municipality || 
                             data.address.village || data.address.county || '';
                const province = data.address.state || data.address.province || 
                               data.address.region || '';
                
                document.getElementById('manual_address').value = address;
                document.getElementById('manual_city').value = city;
                document.getElementById('manual_province').value = province;
                
                alert('Address filled successfully!');
            } else {
                throw new Error('No address data available');
            }
        } catch (error) {
            console.error('Geocoding error:', error);
            alert('Unable to fetch address. You can enter it manually or try again later.');
        } finally {
            fetchBtn.innerHTML = originalHTML;
            fetchBtn.disabled = false;
        }
    }

    function getCurrentLocation() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        const gpsBtn = document.getElementById('gpsBtn');
        const originalHTML = gpsBtn.innerHTML;
        gpsBtn.innerHTML = '<span class="loading-spinner"></span> Getting Location...';
        gpsBtn.disabled = true;

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                
                map.setView([lat, lon], 16);
                placeMarker(lat, lon);
                
                gpsBtn.innerHTML = originalHTML;
                gpsBtn.disabled = false;
            },
            function(error) {
                console.error('Geolocation error:', error);
                alert('Unable to get your location. Please ensure location permissions are enabled.');
                gpsBtn.innerHTML = originalHTML;
                gpsBtn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function viewLocation(btn) {
        const row = btn.closest('tr');
        const locationName = row.querySelector('.location-name').textContent;
        const locationAddress = row.getAttribute('data-address') || '-';
        const latitude = parseFloat(row.getAttribute('data-lat'));
        const longitude = parseFloat(row.getAttribute('data-lon'));
        
        document.getElementById('view-name').textContent = locationName;
        document.getElementById('view-address').textContent = locationAddress;
        
        if (latitude && longitude) {
            document.getElementById('view-coordinates').textContent = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
        } else {
            document.getElementById('view-coordinates').textContent = 'No coordinates available';
        }
        
        document.getElementById('viewModal').classList.add('show');
        
        setTimeout(() => {
            if (!viewMap) {
                viewMap = L.map('viewMap');
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(viewMap);
            } else {
                viewMap.invalidateSize();
                viewMap.eachLayer(layer => {
                    if (layer instanceof L.Marker) {
                        viewMap.removeLayer(layer);
                    }
                });
            }
            
            if (latitude && longitude) {
                viewMap.setView([latitude, longitude], 16);
                L.marker([latitude, longitude]).addTo(viewMap);
            } else {
                viewMap.setView([10.250608982512142, 123.94947052001955], 13);
            }
        }, 100);
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('show');
    }

    function deleteLocation(btn) {
        const row = btn.closest('tr');
        const locationId = row.getAttribute('data-id');
        const locationName = row.querySelector('.location-name').textContent;
        
        if (confirm(`Are you sure you want to delete the location "${locationName}"? This action cannot be undone.`)) {
            document.getElementById('delete_location_id').value = locationId;
            document.getElementById('deleteLocationForm').submit();
        }
    }

    function submitForm() {
        const locationName = document.getElementById('location_name').value.trim();
        
        if (!locationName) {
            alert('Please enter a location name');
            return;
        }
        
        if (!isEditMode) {
             const latitude = document.getElementById('latitude').value;
             if (!latitude) {
                 alert('Please set a location on the map');
                 return;
             }
        }
        
        document.getElementById('locationForm').submit();
    }

    function filterTable() {
        const input = document.querySelector('.search-input');
        const filter = input.value.toUpperCase();
        const rows = document.getElementById('location-table').getElementsByTagName('tr');
        let visible = 0;
        
        for (let i = 0; i < rows.length; i++) {
            const nameCol = rows[i].getElementsByTagName('td')[1];
            const addressCol = rows[i].getElementsByTagName('td')[2];
            
            if (nameCol && addressCol) {
                const nameValue = nameCol.textContent || nameCol.innerText;
                const addressValue = addressCol.textContent || addressCol.innerText;
                
                if (nameValue.toUpperCase().indexOf(filter) > -1 || addressValue.toUpperCase().indexOf(filter) > -1) {
                    rows[i].style.display = '';
                    visible++;
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
        
        const emptyState = document.getElementById('empty-state');
        if (visible === 0) {
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
        }
    }

    document.getElementById('locationSearch').addEventListener('input', function() {
        const query = this.value.trim();
        const resultsContainer = document.getElementById('searchResults');
        
        clearTimeout(searchTimeout);
        
        if (query.length < 3) {
            resultsContainer.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(async function() {
            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`,
                    { 
                        headers: { 
                            'User-Agent': 'LocationManagementSystem/1.0',
                            'Accept': 'application/json'
                        } 
                    }
                );
                
                if (!response.ok) throw new Error('Search failed');
                const data = await response.json();
                
                resultsContainer.innerHTML = '';
                
                if (data.length === 0) {
                    resultsContainer.innerHTML = '<div class="search-result">No results found</div>';
                } else {
                    data.forEach(item => {
                        const resultItem = document.createElement('div');
                        resultItem.className = 'search-result';
                        resultItem.textContent = item.display_name;
                        resultItem.addEventListener('click', function() {
                            const lat = parseFloat(item.lat);
                            const lon = parseFloat(item.lon);
                            
                            map.setView([lat, lon], 16);
                            placeMarker(lat, lon);
                            
                            resultsContainer.style.display = 'none';
                            document.getElementById('locationSearch').value = '';
                        });
                        resultsContainer.appendChild(resultItem);
                    });
                }
                resultsContainer.style.display = 'block';
            } catch (error) {
                console.error('Search error:', error);
                resultsContainer.innerHTML = '<div class="search-result">Search unavailable</div>';
                resultsContainer.style.display = 'block';
            }
        }, 800);
    });

    document.addEventListener('click', function(e) {
        const searchInput = document.getElementById('locationSearch');
        const searchResults = document.getElementById('searchResults');
        
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.getElementById('location-table').getElementsByTagName('tr');
        if (rows.length === 0) {
            document.getElementById('empty-state').style.display = 'block';
        }
        
        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert-box');
        if(alerts.length > 0) {
            setTimeout(() => {
                alerts.forEach(el => el.style.display = 'none');
            }, 5000);
        }
    });
</script>
</body>
</html>