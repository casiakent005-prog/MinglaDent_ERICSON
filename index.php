<?php
require_once 'database/config.php';
$pageTitle = 'MinglaDent';
require_once 'includes/header.php';
?>
<style>
    /* Hero Section */
    .hero {
        background: url('https://images.unsplash.com/photo-1606811841689-23dfddce3e95?w=1920&q=80');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        text-align: left;
        padding: 100px 60px 40px 60px;
    }

    .hero h1 {
        color: #6784c8;
        font-size: 72px;
        font-weight: 300;
        margin-bottom: 20px;
        letter-spacing: 2px;
        text-shadow: 4px 4px 8px rgba(0, 0, 0, 0.9), 3px 3px 6px rgba(255, 251, 238, 0.95);
    }

    .hero p {
        color: #6784c8;
        font-size: 24px;
        font-weight: 400;
        max-width: 600px;
        line-height: 1.6;
        margin-bottom: 40px;
        text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.9), 2px 2px 4px rgba(255, 251, 238, 0.95);
    }

    .buttons {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .btn-primary {
        background-color: #6784c8;
        color: #fffbee;
        padding: 15px 40px;
        border: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 2px;
        text-decoration: none;
        border-radius: 5px;
    }

    /* Features Section */
    .features-section {
        background-color: #fffbee;
        padding: 80px 60px;
    }

    .features-section h2 {
        color: #6784c8;
        font-size: 48px;
        font-weight: 600;
        margin-bottom: 50px;
        text-align: center;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 40px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .feature-card {
        background-color: #fffbee;
        border: 2px solid #6784c8;
        border-radius: 10px;
        padding: 40px 30px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .feature-card:hover {
        background-color: #6784c8;
    }

    .feature-card:hover h3,
    .feature-card:hover p {
        color: #fffbee;
    }

    .feature-card .icon {
        font-size: 48px;
        margin-bottom: 20px;
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

    /* Stats Section */
    .stats-section {
        background-color: #6784c8;
        padding: 80px 60px;
        text-align: center;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 40px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .stat-item h3 {
        color: #fffbee;
        font-size: 48px;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .stat-item p {
        color: #fffbee;
        font-size: 18px;
        font-weight: 500;
    }

    /* Testimonials Section */
    .testimonials-section {
        background-color: #fffbee;
        padding: 80px 60px;
    }

    .testimonials-section h2 {
        color: #6784c8;
        font-size: 48px;
        font-weight: 600;
        margin-bottom: 50px;
        text-align: center;
    }

    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .testimonial-card {
        background-color: #fffbee;
        border: 2px solid #6784c8;
        border-radius: 10px;
        padding: 30px;
    }

    .testimonial-card p {
        color: #6784c8;
        font-size: 16px;
        line-height: 1.8;
        font-style: italic;
        margin-bottom: 20px;
    }

    .testimonial-card .author {
        color: #6784c8;
        font-size: 18px;
        font-weight: 600;
    }

    .testimonial-card .location {
        color: #6784c8;
        font-size: 14px;
    }

    /* CTA Section */
    .cta-section {
        background: url('https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?w=1920&q=80');
        background-size: cover;
        background-position: center;
        padding: 100px 60px;
        text-align: center;
    }

    .cta-section h2 {
        color: #6784c8;
        font-size: 48px;
        font-weight: 600;
        margin-bottom: 20px;
        text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.8), 2px 2px 4px rgba(255, 251, 238, 0.9);
    }

    .cta-section p {
        color: #6784c8;
        font-size: 20px;
        margin-bottom: 40px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8), 1px 1px 2px rgba(255, 251, 238, 0.9);
    }

    .cta-section .buttons {
        display: flex;
        justify-content: center;
        gap: 20px;
    }
</style>

    <div class="hero">
        <h1>Your Smile, Our Passion</h1>
        <p>World-class dental care across 11 Minglanilla clinics. Unmistakably Filipino hospitality. Recognized as the best dental clinic in the Philippines for veneers, implants, and cosmetic dentistry.</p>
        <div class="buttons">
            <a href="locations.php" class="btn-primary">Book Appointment</a>
        </div>
    </div>

    <div class="features-section">
        <h2>Why Choose MinglaDent?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="icon">✨</div>
                <h3>Expert Dentists</h3>
                <p>Our team of highly skilled dentists brings years of experience and expertise to every patient.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🏥</div>
                <h3>Modern Facilities</h3>
                <p>State-of-the-art equipment and technology for accurate diagnosis and effective treatments.</p>
            </div>
            <div class="feature-card">
                <div class="icon">💙</div>
                <h3>Filipino Hospitality</h3>
                <p>Experience warm, caring service that makes you feel like family from the moment you walk in.</p>
            </div>
            <div class="feature-card">
                <div class="icon">📍</div>
                <h3>11 Locations</h3>
                <p>Conveniently located across Minglanilla to serve you better wherever you are.</p>
            </div>
        </div>
    </div>

    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-item">
                <h3>11</h3>
                <p>Clinics</p>
            </div>
            <div class="stat-item">
                <h3>50+</h3>
                <p>Expert Dentists</p>
            </div>
            <div class="stat-item">
                <h3>10K+</h3>
                <p>Happy Patients</p>
            </div>
            <div class="stat-item">
                <h3>15+</h3>
                <p>Years Experience</p>
            </div>
        </div>
    </div>

    <div class="testimonials-section">
        <h2>What Our Patients Say</h2>
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <p>"The best dental experience I've ever had! The staff is so friendly and professional. My smile has never looked better."</p>
                <p class="author">Shazkee </p>
                <p class="location">Poblacion</p>
            </div>
            <div class="testimonial-card">
                <p>"I was nervous about getting implants, but the team at MinglaDent made me feel so comfortable. Highly recommended!"</p>
                <p class="author">Kentt Casia</p>
                <p class="location">Tubod</p>
            </div>
            <div class="testimonial-card">
                <p>"Affordable prices without compromising quality. The Filipino hospitality here is unmatched. Truly world-class service!"</p>
                <p class="author">Denays kitli</p>
                <p class="location">Cadulawan</p>
            </div>
        </div>
    </div>

    <div class="cta-section">
        <h2>Ready to Transform Your Smile?</h2>
        <p>Book your appointment today and take the first step towards your perfect smile.</p>
        <div class="buttons">
            <button class="btn-primary">Book Now</button>
        </div>
    </div>

    <script>
        let lastScrollTop = 0;
        const nav = document.querySelector('nav');

        window.addEventListener('scroll', function() {
            let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (scrollTop > lastScrollTop && scrollTop > 100) {
                nav.classList.add('hidden');
            } else {
                nav.classList.remove('hidden');
            }

            lastScrollTop = scrollTop;
        });

        // Toggle patient menu
        function togglePatientMenu() {
            const menu = document.getElementById('patientMenu');
            menu.classList.toggle('active');
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('patientMenu');
            const hamburger = document.querySelector('.hamburger-icon');
            if (menu && !menu.contains(event.target) && !hamburger.contains(event.target)) {
                menu.classList.remove('active');
            }
        });
    </script>

<?php require_once 'includes/footer.php'; ?>
