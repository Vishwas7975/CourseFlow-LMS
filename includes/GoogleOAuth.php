<?php
/**
 * GoogleOAuth.php — Google Sign-In helper for CourseFlow LMS
 *
 * Adapted from the Scentify project's helpers/GoogleOAuth.php.
 * Same 3-step flow (auth URL -> token exchange -> user info), just
 * pointed at CourseFlow's own VS_GOOGLE_* constants from config/secrets.php
 * instead of Scentify's GOOGLE_* constants.
 *
 * Upload to: public_html/includes/GoogleOAuth.php
 */

class GoogleOAuth
{
    // Step 1: Build the URL that sends the browser to Google's consent screen
    public static function getAuthUrl(): string
    {
        $params = http_build_query([
            'client_id'     => VS_GOOGLE_CLIENT_ID,
            'redirect_uri'  => VS_GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);

        return 'https://accounts.google.com/o/oauth2/auth?' . $params;
    }

    // Step 2: Exchange the authorization code Google sent back for an access token
    public static function getAccessToken(string $code): ?string
    {
        $response = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => 'Content-Type: application/x-www-form-urlencoded',
                'content'       => http_build_query([
                    'code'          => $code,
                    'client_id'     => VS_GOOGLE_CLIENT_ID,
                    'client_secret' => VS_GOOGLE_CLIENT_SECRET,
                    'redirect_uri'  => VS_GOOGLE_REDIRECT_URI,
                    'grant_type'    => 'authorization_code',
                ]),
                'ignore_errors' => true,
            ],
        ]));

        if (!$response) {
            error_log('GoogleOAuth: token endpoint did not respond (check allow_url_fopen on the server).');
            return null;
        }

        $data = json_decode($response, true);

        if (empty($data['access_token'])) {
            error_log('GoogleOAuth: token exchange failed - ' . $response);
            return null;
        }

        return $data['access_token'];
    }

    // Step 3: Use the access token to fetch the signed-in user's Google profile
    public static function getUserInfo(string $accessToken): ?array
    {
        $response = @file_get_contents(
            'https://www.googleapis.com/oauth2/v2/userinfo',
            false,
            stream_context_create([
                'http' => [
                    'header'        => 'Authorization: Bearer ' . $accessToken,
                    'ignore_errors' => true,
                ],
            ])
        );

        if (!$response) {
            error_log('GoogleOAuth: userinfo endpoint did not respond.');
            return null;
        }

        $data = json_decode($response, true);

        if (empty($data['email'])) {
            error_log('GoogleOAuth: userinfo response missing email - ' . $response);
            return null;
        }

        return $data;
    }
}