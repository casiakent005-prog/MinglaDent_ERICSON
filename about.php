<?php
session_start();
require_once 'database/config.php';
$pageTitle = 'About - MinglaDent';
require_once 'includes/header.php';
?>
<style>
    /* Hero Section */
    .hero {
        background: url('https://images.unsplash.com/photo-1609840114035-3c981b782dfe?w=1920&q=80');
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

    .hero .quote {
        color: #6784c8;
        font-size: 32px;
        font-weight: 400;
        font-style: italic;
        max-width: 800px;
        line-height: 1.4;
        text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.9), 2px 2px 4px rgba(255, 251, 238, 0.95);
    }

    /* Mission Section */
    .mission-section {
        background-color: #fffbee;
        padding: 60px;
        text-align: center;
        max-width: 1000px;
        margin: 0 auto;
    }

    .mission-section h2 {
        color: #6784c8;
        font-size: 48px;
        font-weight: 600;
        margin-bottom: 30px;
    }

    .mission-section p {
        color: #6784c8;
        font-size: 20px;
        line-height: 1.8;
    }

    /* Features Section */
    .features-section {
        background-color: #fffbee;
        padding: 60px;
    }

    .features-section h2 {
        color: #6784c8;
        font-size: 48px;
        font-weight: 600;
        margin-bottom: 40px;
        text-align: center;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .feature-card {
        background-color: #fffbee;
        border: 2px solid #6784c8;
        border-radius: 10px;
        padding: 30px;
        text-align: center;
    }

    .feature-card h3 {
        color: #6784c8;
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .feature-card p {
        color: #6784c8;
        font-size: 16px;
        line-height: 1.6;
    }
</style>

    <div class="hero">
        <h1>ABOUT OUR PLATFORM</h1>
        <p class="quote">"Connecting patients with the best dental care in Minglanilla"</p>
    </div>

    <div class="mission-section">
        <h2>Our Mission</h2>
        <p>We built this platform to help patients easily find and connect with trusted dental clinics in Minglanilla. Our goal is to make dental care accessible, transparent, and convenient for everyone in the community.</p>
    </div>

    <div class="features-section">
        <h2>What We Offer</h2>
        <div class="features-grid">
            <div class="feature-card">
                <h3>Easy Discovery</h3>
                <p>Find dental clinics near you with our comprehensive directory of Minglanilla's trusted dental providers.</p>
            </div>
            <div class="feature-card">
                <h3>Transparent Information</h3>
                <p>Access detailed information about clinics, services, and patient reviews to make informed decisions.</p>
            </div>
            <div class="feature-card">
                <h3>Easy Booking</h3>
                <p>Book appointments directly through our platform with just a few clicks.</p>
            </div>
            <div class="feature-card">
                <h3>Community Focus</h3>
                <p>We're dedicated to serving the Minglanilla community and improving access to quality dental care.</p>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>
