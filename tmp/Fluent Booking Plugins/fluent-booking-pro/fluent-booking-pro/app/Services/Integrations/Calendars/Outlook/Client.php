<?php

namespace FluentBookingPro\App\Services\Integrations\Calendars\Outlook;

use FluentBooking\App\Services\Helper;
use FluentBooking\Framework\Support\Arr;

class Client
{
    public $clientId;
    public $clientSecret;
    public $redirectUrl;

    private $accessToken;

    public $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    public $revokeUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/logout';
    public $refreshTokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    public $authScope = 'Calendars.ReadWrite offline_access openid';

    public $calendarEvent = 'https://graph.microsoft.com/v1.0/me/calendars/{calendarId}/events';

    public function __construct($clientID, $clientSecret)
    {
        $this->tokenUrl = apply_filters('fluent_booking/outlook_token_url', $this->tokenUrl);
        $this->revokeUrl = apply_filters('fluent_booking/outlook_revoke_url', $this->revokeUrl);
        $this->refreshTokenUrl = apply_filters('fluent_booking/outlook_refresh_token_url', $this->refreshTokenUrl);

        $this->clientId = $clientID;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl = OutlookHelper::getAppRedirectUrl();
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getUserDetails()
    {
        $headers = [
            'Authorization' => 'Bearer ' . '',
            'Content-Type'  => 'application/json'
        ];

        return $this->makeRequest('https://graph.microsoft.com/v1.0/me', [], 'GET', $headers);
    }

    public function generateAuthCode($code)
    {
        $body = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $this->authScope,
            'redirect_uri'  => $this->redirectUrl,
            'grant_type'    => 'authorization_code',
            'code'          => $code
        ];

        return $this->makeRequest($this->tokenUrl, $body, 'POST');
    }

    public function reGenerateToken($refreshToken)
    {
        $body = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUrl,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken
        ];

        $tokens = $this->makeRequest($this->refreshTokenUrl, $body, 'POST');

        if (is_wp_error($tokens)) {
            return $tokens;
        }

        $tokens['expires_in'] += time();

        return $tokens;
    }

    public function getCalendarLists($accessToken = null)
    {
        $lists = $this->makeRequest('https://graph.microsoft.com/v1.0/me/calendars?$top=25', [], 'GET', $this->getAuthorizationHeader($accessToken));

        if (is_wp_error($lists)) {
            return $lists;
        }

        $formattedLists = [];

        foreach ($lists['value'] as $item) {
            $formattedLists[] = [
                'id'        => $item['id'],
                'title'     => $item['name'] . ' (' . Arr::get($item, 'owner.address') . ')',
                'can_write' => Arr::isTrue($item, 'canEdit') ? 'yes' : 'no'
            ];
        }

        return $formattedLists;
    }

    public function getCalendarEvents($id, $args = [])
    {
        $minDate = $args['startDateTime'];
        $maxDate = $args['endDateTime'];

        $url = 'https://graph.microsoft.com/v1.0/me/calendars/' . $id . '/calendarview';
        $url .= '?$select=subject,recurrence,showAs,start,end,subject,isAllDay,transactionId&startdatetime=' . $minDate . '&enddatetime=' . $maxDate . '&$top=' . $args['maxResults'];

        // recurrence,showAs,start,end,subject

        $lists = $this->makeRequest($url, [], 'GET', $this->getAuthorizationHeader());

        if (is_wp_error($lists)) {
            return $lists;
        }

        $isHoliday = Arr::get($args, 'isHoliday', false);

        $formattedLists = [];
        foreach ($lists['value'] as $item) {
            if (empty($item['start']['dateTime'])) {
                continue;
            }

            if ((Arr::get($item, 'showAs') == 'free' && !$isHoliday)) {
                continue;
            }

            $formattedLists[] = [
                'id'     => $item['id'],
                'start'  => Arr::get($item, 'start.dateTime'),
                'end'    => Arr::get($item, 'end.dateTime'),
                'status' => Arr::get($item, 'showAs')
            ];
        }

        return $formattedLists;
    }

    public function createEvent($calendarId, $data, $args = [])
    {
        $url = 'https://graph.microsoft.com/v1.0/me/calendars/' . $calendarId . '/events';

        if ($args) {
            $url = add_query_arg($args, $url);
        }

        $siteUid = OutlookHelper::getUniqueSiteIdHash();

        $data['transactionId'] = $siteUid . '__' . $data['transactionId'];

        return $this->makeRequest($url, wp_json_encode($data), 'POST', $this->getAuthorizationHeader());
    }

    public function patchEvent($eventId, $data, $args = [])
    {
        $url = 'https://graph.microsoft.com/v1.0/me/events/' . $eventId;

        if ($args) {
            $url = add_query_arg($args, $url);
        }

        return $this->makeRequest($url, wp_json_encode($data), 'PATCH', $this->getAuthorizationHeader());
    }

    public function getEvent($calendarId, $eventId, $args = [])
    {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . $calendarId . '/events/' . $eventId;

        if ($args) {
            $url = add_query_arg($args, $url);
        }

        return $this->makeRequest($url, '', 'GET', $this->getAuthorizationHeader());
    }

    public function deleteEvent($eventId)
    {
        return $this->makeRequest("https://graph.microsoft.com/v1.0/me/events/{$eventId}", [], 'DELETE', $this->getAuthorizationHeader());
    }

    public function revokeConnection()
    {
        return $this->makeRequest($this->revokeUrl, [], 'GET', $this->getAuthorizationHeader());
    }

    public function getAuthorizationHeader($accessToken = null)
    {
        if (!$accessToken) {
            $accessToken = $this->accessToken;
        }

        return [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json'
        ];
    }

    public function makeRequest($url, $body = null, $type = 'GET', $headers = null, $xtraArgs = [])
    {
        if (!$headers) {
            $headers = [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
        }

        $args = [
            'headers'     => $headers,
            'method'      => $type,
            'timeout'     => 20,
            'httpversion' => '1.1',
        ];

        if ($body) {
            if ($type == 'GET') {
                $url = add_query_arg($body, $url);
            } else {
                $args['body'] = $body;
            }
        }


        $request = wp_remote_request($url, $args);

        if (is_wp_error($request)) {
            $message = $request->get_error_message();
            Helper::debugLog([
                'message' => $message,
                'url'     => $url,
                'body'    => $body,
                'method'  => __METHOD__,
                'type'    => 'wp_request_error'
            ]);
            return new \WP_Error('wp_error', $message, $request->get_all_error_data());
        }


        $resCode = wp_remote_retrieve_response_code($request);

        $resBody = json_decode(wp_remote_retrieve_body($request), true);

        if ($resCode > 299) {
            $message = Arr::get($resBody, 'error_description', __('Unexpected error from outlook api', 'fluent-booking-pro'));

            Helper::debugLog([
                'message' => $resBody,
                'url'     => $url,
                'body'    => $body,
                'header'  => $headers,
                'method'  => __METHOD__,
                'type'    => 'api_error'
            ]);

            return new \WP_Error('api_error', $message, $resBody);
        }

        return $resBody;
    }

    public function makeCurlPost($url, $body, $header)
    {
        // Initialize cURL session
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($body));

        // Execute the cURL session
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return new \WP_Error('curl_error', $error_msg);
        }

        // Close cURL session
        curl_close($ch);

        // Decode the response
        return json_decode($response, true);
    }

    public function getAuthUrl($userId)
    {
        return add_query_arg([
            'client_id'    => $this->clientId,
            'redirect_uri' => urlencode_deep(add_query_arg([
                'state' => $userId
            ], admin_url('admin-ajax.php?action=fluent_booking_outlook_auth')))
        ], $this->redirectUrl);
    }
}
