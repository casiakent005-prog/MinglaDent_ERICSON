<?php
session_start();
require_once 'database/config.php';
$pageTitle = 'Services - MinglaDent';
require_once 'includes/header.php';
?>
<style>
    /* Hero Section */
    .hero {
        background: url('https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=1920&q=80');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 100px 60px 40px 60px;
    }

    .hero h1 {
        color: #6784c8;
        font-size: 64px;
        font-weight: 300;
        margin-bottom: 20px;
        letter-spacing: 2px;
        text-shadow: 4px 4px 8px rgba(0, 0, 0, 0.9), 3px 3px 6px rgba(255, 251, 238, 0.95);
    }

    .hero p {
        color: #6784c8;
        font-size: 28px;
        max-width: 800px;
        line-height: 1.6;
        text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.9), 2px 2px 4px rgba(255, 251, 238, 0.95);
    }

    /* Services Section */
    .services-section {
        background-color: #fffbee;
        padding: 60px;
    }

    .services-section h2 {
        color: #6784c8;
        font-size: 48px;
        font-weight: 600;
        margin-bottom: 40px;
        text-align: center;
    }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .service-card {
        background-color: #fffbee;
        border: 2px solid #6784c8;
        border-radius: 10px;
        padding: 40px 30px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .service-card:hover {
        background-color: #6784c8;
    }

    .service-card:hover h3,
    .service-card:hover p {
        color: #fffbee;
    }

    .service-card:hover .service-icon {
        background-color: #fffbee;
        color: #6784c8;
    }

    .service-icon {
        width: 80px;
        height: 80px;
        background-color: #6784c8;
        color: #fffbee;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 700;
        margin: 0 auto 20px auto;
    }

    .service-card h3 {
        color: #6784c8;
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .service-card p {
        color: #6784c8;
        font-size: 16px;
        line-height: 1.6;
    }
</style>

    <div class="hero">
        <h1>OUR DENTAL SERVICES</h1>
        <p>World-class dental care with the latest technology and experienced professionals</p>
    </div>

    <div class="services-section">
        <h2>Comprehensive Dental Care</h2>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">V</div>
                <h3>Veneers</h3>
                <p>Transform your smile with our premium porcelain veneers. Perfect for correcting chips, gaps, and discoloration.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">I</div>
                <h3>Dental Implants</h3>
                <p>Restore your smile with permanent dental implants that look and feel like natural teeth.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">C</div>
                <h3>Cosmetic Dentistry</h3>
                <p>Enhance your smile with teeth whitening, bonding, and other cosmetic procedures.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">O</div>
                <h3>Orthodontics</h3>
                <p>Straighten your teeth with modern braces and clear aligners for a perfect smile.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">R</div>
                <h3>Root Canal</h3>
                <p>Save your natural teeth with our painless root canal treatments.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">E</div>
                <h3>Extractions</h3>
                <p>Safe and comfortable tooth extractions performed by experienced dentists.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">C</div>
                <h3>Cleaning & Scaling</h3>
                <p>Professional dental cleaning to maintain your oral health and prevent gum disease.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">F</div>
                <h3>Fillings</h3>
                <p>Tooth-colored fillings that blend seamlessly with your natural teeth.</p>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>
