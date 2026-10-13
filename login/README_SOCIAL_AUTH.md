# Social Authentication Setup Guide

This guide explains how to configure Facebook and Google authentication for MinglaDent.

## Facebook Authentication Setup

### Step 1: Create a Facebook App

1. Go to [Facebook for Developers](https://developers.facebook.com/apps/)
2. Click "Create App"
3. Select "Consumer" (or appropriate type for your use case)
4. Fill in the required information:
   - App Display Name: "MinglaDent"
   - App Contact Email: Your email address
5. Complete the app creation process

### Step 2: Configure Facebook App Settings

1. In your Facebook App Dashboard, go to "App Settings" > "Basic"
2. Note your **App ID** and **App Secret**
3. Add your domain to the App Domains field (e.g., `localhost` for development)
4. In "Privacy Policy URL", add your privacy policy URL (can be placeholder for development)

### Step 3: Add Facebook Login Product

1. In the App Dashboard, click "Add Product" > "Facebook Login"
2. Configure the Facebook Login settings:
   - Enable "Client OAuth Login"
   - Enable "Web OAuth Login"
   - Set "Valid OAuth Redirect URIs" to:
     - Development: `http://localhost/innovate/login/facebook_callback.php`
     - Production: `https://yourdomain.com/login/facebook_callback.php`

### Step 4: Update Configuration

1. Open `database/social_auth_config.php`
2. Replace the placeholder values:
   ```php
   'facebook' => [
       'app_id' => 'YOUR_ACTUAL_FACEBOOK_APP_ID',
       'app_secret' => 'YOUR_ACTUAL_FACEBOOK_APP_SECRET',
       'redirect_uri' => 'http://localhost/innovate/login/facebook_callback.php', // Update for production
       'graph_version' => 'v18.0'
   ]
   ```

### Step 5: Enable in Login Page

1. Open `login/login_new.php`
2. Find the `handleFacebookLogin()` function
3. Uncomment the redirect line:
   ```javascript
   window.location.href = authUrl;
   ```

## Google Authentication Setup

### Step 1: Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Google+ API (if not already enabled)

### Step 2: Create OAuth 2.0 Credentials

1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "OAuth client ID"
3. Configure the consent screen if prompted:
   - User Type: External
   - Add your app name, logo, and other details
4. Select "Web application" as the application type
5. Add authorized redirect URIs:
   - Development: `http://localhost/innovate/login/google_callback.php`
   - Production: `https://yourdomain.com/login/google_callback.php`
6. Click "Create"
7. Note your **Client ID** and **Client Secret**

### Step 3: Update Configuration

1. Open `database/social_auth_config.php`
2. Replace the placeholder values:
   ```php
   'google' => [
       'client_id' => 'YOUR_ACTUAL_GOOGLE_CLIENT_ID',
       'client_secret' => 'YOUR_ACTUAL_GOOGLE_CLIENT_SECRET',
       'redirect_uri' => 'http://localhost/innovate/login/google_callback.php' // Update for production
   ]
   ```

### Step 4: Enable in Login Page

1. Open `login/login_new.php`
2. Find the `handleGoogleLogin()` function
3. Uncomment the redirect line:
   ```javascript
   window.location.href = authUrl;
   ```

## Security Best Practices

1. **Never commit credentials to version control**: Use environment variables instead
2. **Use HTTPS in production**: Always use HTTPS for OAuth redirect URIs
3. **Validate redirect URIs**: Ensure redirect URIs match exactly in both platforms
4. **Monitor authentication logs**: Check the `social_auth_log` table for suspicious activity
5. **Enable app verification**: Submit your apps for verification when going to production

## Testing Social Authentication

### Testing Facebook Login

1. Ensure your Facebook App is in "Development" mode
2. Add your test Facebook account as a "Test User" in the Facebook App Dashboard
3. Click the "Login with Facebook" button on the login page
4. Grant permissions when prompted
5. Verify that you are redirected and logged in successfully

### Testing Google Login

1. Ensure your OAuth consent screen is configured
2. Click the "Sign in with Google" button on the login page
3. Select your Google account
4. Grant permissions when prompted
5. Verify that you are redirected and logged in successfully

## Troubleshooting

### Facebook Login Issues

- **Error: "Redirect URI mismatch"**: Check that the redirect URI in Facebook App settings exactly matches the one in your config file
- **Error: "App not setup"**: Ensure Facebook Login product is enabled and properly configured
- **No email received**: Check that the `email` permission is requested and granted

### Google Login Issues

- **Error: "redirect_uri_mismatch"**: Verify the redirect URI in Google Cloud Console matches your config
- **Error: "access_denied"**: Check that the OAuth consent screen is properly configured
- **No email received**: Ensure the email scope is included in the authorization request

## Production Deployment

When deploying to production:

1. Update all redirect URIs to use HTTPS
2. Submit your Facebook App for review and verification
3. Submit your Google OAuth app for verification
4. Use environment variables for credentials instead of hardcoding
5. Enable HTTPS and secure cookie settings
6. Update the social_auth_config.php with production URLs

## Environment Variables (Recommended)

Instead of hardcoding credentials, use environment variables:

```bash
# For Facebook
export FACEBOOK_APP_ID="your_app_id"
export FACEBOOK_APP_SECRET="your_app_secret"

# For Google
export GOOGLE_CLIENT_ID="your_client_id"
export GOOGLE_CLIENT_SECRET="your_client_secret"
```

The configuration file already supports reading from environment variables using `getenv()`.
