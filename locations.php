<?php
session_start();
require_once 'database/config.php';
$search = trim($_GET['q'] ?? '');
$pattern = '%' . $search . '%';
$stmt = $pdo->prepare("SELECT c.clinic_id, c.clinic_name, c.address, c.contact_number, c.email, c.operating_hours, c.map_location, c.status, dentists.dentist_names, GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR ', ') AS service_names, MIN(sf.amount) AS lowest_fee, MAX(sf.amount) AS highest_fee, AVG(r.rating_average) AS average_rating FROM clinics c LEFT JOIN service_fees sf ON sf.clinic_id = c.clinic_id AND EXISTS (SELECT 1 FROM services active_service WHERE active_service.service_id = sf.service_id AND active_service.clinic_id = c.clinic_id AND active_service.status = 'active') LEFT JOIN services s ON s.service_id = sf.service_id AND s.clinic_id = c.clinic_id AND s.status = 'active' LEFT JOIN (SELECT cs.clinic_id, GROUP_CONCAT(DISTINCT au.name ORDER BY au.name SEPARATOR ', ') AS dentist_names FROM clinic_staff cs INNER JOIN auth_users au ON au.id = cs.user_id WHERE cs.role = 'dentist' AND au.status = 'active' GROUP BY cs.clinic_id) dentists ON dentists.clinic_id = c.clinic_id LEFT JOIN (SELECT clinic_id, AVG(rating) AS rating_average FROM clinic_ratings WHERE status = 'published' GROUP BY clinic_id) r ON r.clinic_id = c.clinic_id WHERE c.status IN ('approved', 'pending') AND (? = '' OR c.clinic_name LIKE ? OR c.address LIKE ? OR s.service_name LIKE ?) GROUP BY c.clinic_id ORDER BY (c.status = 'approved') DESC, c.clinic_name LIMIT 50");
$stmt->execute([$search, $pattern, $pattern, $pattern]);
$clinics = $stmt->fetchAll();
$clinicImages = [
    'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=640&q=80',
    'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=640&q=80',
    'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=640&q=80',
    'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=640&q=80',
    'https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=640&q=80',
];
$pageTitle = 'Locations - MinglaDent';
require_once 'includes/header.php';
?>
<style>
    /* Locations Section */
    .locations-section {
        padding: 120px 60px 60px 60px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .directory-heading {
        margin-bottom: 40px;
    }

    .directory-heading h2 {
        color: #6784c8;
        font-size: 48px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .directory-heading .subtitle {
        color: #666;
        font-size: 18px;
    }

    .directory-search {
        display: flex;
        gap: 15px;
        margin-bottom: 40px;
        max-width: 600px;
    }

    .directory-search input {
        flex: 1;
        padding: 15px 20px;
        font-size: 16px;
        border: 2px solid #6784c8;
        border-radius: 8px;
        background-color: #fff;
        color: #333;
        font-family: 'Montserrat', sans-serif;
    }

    .directory-search input:focus {
        outline: none;
        border-color: #6784c8;
        box-shadow: 0 0 0 3px rgba(103, 132, 200, 0.1);
    }

    .directory-search button {
        padding: 15px 30px;
        background-color: #6784c8;
        color: #fffbee;
        border: none;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 8px;
        font-family: 'Montserrat', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .directory-search button:hover {
        background-color: #5a6fa8;
    }

    .directory-empty {
        text-align: center;
        color: #666;
        font-size: 18px;
        padding: 40px;
    }

    .clinic-rail {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 30px;
    }

    .clinic-card {
        background-color: #fff;
        border: 2px solid #6784c8;
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .clinic-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(103, 132, 200, 0.2);
    }

    .clinic-cover {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .clinic-card-body {
        padding: 25px;
    }

    .clinic-status {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .clinic-status.approved {
        background-color: #d4edda;
        color: #155724;
    }

    .clinic-card h3 {
        color: #6784c8;
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .clinic-address {
        color: #666;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .clinic-dentists,
    .clinic-rating,
    .clinic-hours {
        color: #666;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .clinic-dentists i,
    .clinic-rating i,
    .clinic-hours i {
        color: #6784c8;
        margin-right: 5px;
    }

    .clinic-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .clinic-actions a,
    .clinic-actions span {
        flex: 1;
        padding: 10px;
        text-align: center;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
    }

    .clinic-actions .book-btn {
        background-color: #6784c8;
        color: #fffbee;
    }

    .clinic-actions .book-btn:hover {
        background-color: #5a6fa8;
    }

    .clinic-actions .map-link {
        background-color: #fff;
        color: #6784c8;
        border: 2px solid #6784c8;
    }

    .clinic-actions .map-link:hover {
        background-color: #f0f4ff;
    }

    .clinic-actions .map-link[aria-disabled="true"] {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Map Modal */
    .map-modal {
        display: none;
        position: fixed;
        z-index: 3000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
    }

    .map-modal.active {
        display: flex;
    }

    .map-modal-content {
        background-color: #fffbee;
        border: 2px solid #6784c8;
        border-radius: 12px;
        padding: 40px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    }

    .map-modal-close {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 28px;
        color: #6784c8;
        cursor: pointer;
        font-weight: bold;
    }

    .map-modal-close:hover {
        color: #000;
    }

    .map-modal-info h2 {
        color: #6784c8;
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 25px;
    }

    .info-item {
        margin-bottom: 20px;
    }

    .info-item label {
        display: block;
        color: #6784c8;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .info-item p {
        color: #333;
        font-size: 16px;
    }

    @media (max-width: 768px) {
        .locations-section {
            padding: 100px 20px 40px 20px;
        }

        .directory-heading h2 {
            font-size: 32px;
        }

        .clinic-rail {
            grid-template-columns: 1fr;
        }

        .directory-search {
            flex-direction: column;
        }
    }
</style>

    <div class="locations-section">
        <div class="directory-heading">
            <div>
                <h2>Dental Clinics in Minglanilla, Cebu</h2>
                <p class="subtitle">Search dental clinics, services, locations, and published fees</p>
            </div>
        </div>
        <form method="get" class="directory-search">
            <input type="search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Clinic, service, or location" aria-label="Search clinics">
            <button class="book-btn" type="submit">Search</button>
        </form>
        <?php if (!$clinics): ?>
            <p class="directory-empty">No clinic profiles match this search yet.</p>
        <?php endif; ?>
        <div class="clinic-rail" aria-label="Minglanilla dental clinics">
            <?php foreach ($clinics as $clinic): ?>
            <article class="clinic-card" onclick="openMapModal(<?php echo (int) $clinic['clinic_id']; ?>, <?php echo json_encode($clinic); ?>)">
                <?php $clinicImage = $clinicImages[((int) $clinic['clinic_id']) % count($clinicImages)]; ?>
                <img class="clinic-cover" src="<?php echo htmlspecialchars($clinicImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Dental clinic interior" loading="lazy">
                <div class="clinic-card-body">
                    <span class="clinic-status <?php echo $clinic['status'] === 'approved' ? 'approved' : ''; ?>"><?php echo $clinic['status'] === 'approved' ? 'Available for booking' : 'Pending approval'; ?></span>
                    <h3><?php echo htmlspecialchars($clinic['clinic_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="clinic-address"><?php echo htmlspecialchars($clinic['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="clinic-dentists"><i class="fas fa-user-md"></i> Dentists: <?php echo htmlspecialchars($clinic['dentist_names'] ?: 'Not listed', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if ($clinic['average_rating'] !== null): ?><p class="clinic-rating"><i class="fas fa-star"></i><?php echo number_format((float) $clinic['average_rating'], 1); ?></p><?php endif; ?>
                    <p>Services: <?php echo htmlspecialchars($clinic['service_names'] ?: 'Contact clinic for current services', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>Fees: <?php echo $clinic['lowest_fee'] === null ? 'Contact clinic' : '₱' . number_format((float) $clinic['lowest_fee'], 2) . ($clinic['highest_fee'] > $clinic['lowest_fee'] ? ' - ₱' . number_format((float) $clinic['highest_fee'], 2) : ''); ?></p>
                    <p><?php echo htmlspecialchars($clinic['contact_number'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="clinic-hours"><i class="fas fa-clock"></i> Open: <?php echo htmlspecialchars($clinic['operating_hours'] ?: 'Hours not set', ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="clinic-actions">
                        <?php if ($clinic['status'] === 'approved'): ?><a class="book-btn" href="patients/appointments.php?clinic_id=<?php echo (int) $clinic['clinic_id']; ?>" onclick="event.stopPropagation(); checkLogin(event, <?php echo (int) $clinic['clinic_id']; ?>);">Book appointment</a><?php else: ?><span class="map-link" aria-disabled="true">Booking unavailable</span><?php endif; ?>
                        <?php if ($clinic['map_location'] && filter_var($clinic['map_location'], FILTER_VALIDATE_URL) && str_starts_with($clinic['map_location'], 'https://')): ?><a class="map-link" href="<?php echo htmlspecialchars($clinic['map_location'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" onclick="event.stopPropagation();">Open map</a><?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Map Modal -->
    <div id="mapModal" class="map-modal">
        <div class="map-modal-content">
            <span class="map-modal-close" onclick="closeMapModal()">&times;</span>
            <div class="map-modal-info">
                <input type="hidden" id="modalClinicId" value="">
                <h2 id="modalClinicName">Clinic Name</h2>
                <div class="info-item">
                    <label>Address</label>
                    <p id="modalAddress">Address</p>
                </div>
                <div class="info-item">
                    <label>Contact Number</label>
                    <p id="modalContact">Contact</p>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <p id="modalEmail">Email</p>
                </div>
                <div class="info-item">
                    <label>Operating Hours</label>
                    <p id="modalHours">Hours</p>
                </div>
                <div class="info-item">
                    <label>Services</label>
                    <p id="modalServices">Services</p>
                </div>
                <div class="info-item">
                    <label>Fees</label>
                    <p id="modalFees">Fees</p>
                </div>
                <div class="info-item">
                    <label>Dentists</label>
                    <p id="modalDentists">Dentists</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openMapModal(clinicId, clinicData) {
            document.getElementById('modalClinicId').value = clinicId;
            document.getElementById('modalClinicName').textContent = clinicData.clinic_name;
            document.getElementById('modalAddress').textContent = clinicData.address;
            document.getElementById('modalContact').textContent = clinicData.contact_number;
            document.getElementById('modalEmail').textContent = clinicData.email;
            document.getElementById('modalHours').textContent = clinicData.operating_hours || 'Hours not set';
            document.getElementById('modalServices').textContent = clinicData.service_names || 'Contact clinic for current services';
            document.getElementById('modalFees').textContent = clinicData.lowest_fee === null ? 'Contact clinic' : '₱' + Number(clinicData.lowest_fee).toFixed(2) + (clinicData.highest_fee > clinicData.lowest_fee ? ' - ₱' + Number(clinicData.highest_fee).toFixed(2) : '');
            document.getElementById('modalDentists').textContent = clinicData.dentist_names || 'Not listed';
            document.getElementById('mapModal').classList.add('active');
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.remove('active');
        }

        function checkLogin(event, clinicId) {
            <?php if (!isset($_SESSION['user_id'])): ?>
            event.preventDefault();
            window.location.href = 'login/login_new.php';
            <?php endif; ?>
        }

        // Close modal when clicking outside
        document.getElementById('mapModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeMapModal();
            }
        });
    </script>

<?php require_once 'includes/footer.php'; ?>
