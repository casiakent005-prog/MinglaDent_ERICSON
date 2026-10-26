<?php
require_once 'database/config.php';
$pageTitle = 'Contact - MinglaDent';
require_once 'includes/header.php';
?>
<style>
    /* Hero Section */
    .hero {
        background: url('https://images.unsplash.com/photo-1579684385127-1ef15d508118?w=1920&q=80');
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

    /* Contact Section */
    .contact-section {
        background-color: #fffbee;
        padding: 60px;
    }

    .contact-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 60px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .contact-info h2 {
        color: #6784c8;
        font-size: 36px;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .contact-info > p {
        color: #666;
        font-size: 16px;
        line-height: 1.6;
        margin-bottom: 30px;
    }

    .contact-details {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    .contact-item h3 {
        color: #6784c8;
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .contact-item p {
        color: #666;
        font-size: 16px;
        line-height: 1.6;
    }

    .contact-form {
        background-color: #fff;
        border: 2px solid #6784c8;
        border-radius: 10px;
        padding: 40px;
    }

    .contact-form h2 {
        color: #6784c8;
        font-size: 36px;
        font-weight: 600;
        margin-bottom: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        color: #6784c8;
        font-size: 16px;
        font-weight: 500;
        margin-bottom: 8px;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        border: 2px solid #6784c8;
        border-radius: 5px;
        background-color: #fffbee;
        color: #6784c8;
        font-family: 'Montserrat', sans-serif;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #6784c8;
    }

    .form-group textarea {
        min-height: 150px;
        resize: vertical;
    }

    .contact-form button {
        width: 100%;
        padding: 15px;
        background-color: #6784c8;
        color: #fffbee;
        border: none;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 5px;
        font-family: 'Montserrat', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .contact-form button:hover {
        background-color: #5a6fa8;
    }

    @media (max-width: 768px) {
        .contact-container {
            grid-template-columns: 1fr;
        }
    }
</style>

    <div class="hero">
        <h1>GET IN TOUCH</h1>
        <p>We'd love to hear from you. Reach out to us for appointments, inquiries, or feedback.</p>
    </div>

    <div class="contact-section">
        <div class="contact-container">
            <div class="contact-info">
                <h2>Contact Us</h2>
                <p>Have questions about our services or need to schedule an appointment? Our team is here to help you achieve your perfect smile.</p>

                <div class="contact-details">
                    <div class="contact-item">
                        <h3>📍 Main Office</h3>
                        <p>National Highway, Poblacion<br>Minglanilla, Cebu 6046</p>
                    </div>
                    <div class="contact-item">
                        <h3>📞 Phone</h3>
                        <p>(032) 123-4567<br>0917-123-4567</p>
                    </div>
                    <div class="contact-item">
                        <h3>✉️ Email</h3>
                        <p>info@mingladent.com<br>appointments@mingladent.com</p>
                    </div>
                    <div class="contact-item">
                        <h3>⏰ Business Hours</h3>
                        <p>Monday - Saturday: 8:00 AM - 6:00 PM<br>Sunday: Closed</p>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <h2>Send us a Message</h2>
                <form>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" placeholder="Enter your name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" placeholder="Enter your phone number">
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" placeholder="How can we help you?">
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea placeholder="Write your message here..."></textarea>
                    </div>
                    <button type="submit" class="submit-btn">Send Message</button>
                </form>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>
