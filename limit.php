<?php
ignore_user_abort(true);
header("Cache-Control: no-cache");
header("content-type: application/json");
header("Access-Control-Allow-Methods:  'OPTIONS, POST'");
header("Access-Control-Allow-Origin: *"); // Origin
header("Access-Control-Allow-Headers: 'Access-Control-Allow-Headers, Origin,Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers'");

$webinarShortId           = ""; // Webinar short id
$validateWithEMail        = true; // email duplication handling
$validateWithIP           = false; // IP address duplication handling
$maximumRegistrationCount = 1; // Maximum registration

# Handle preflight request
if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
    http_response_code(204);
    exit;
}

# Handle request
if ('POST' == $_SERVER['REQUEST_METHOD']) {
    # Get ip address
    $ip = $_SERVER['REMOTE_ADDR'];

    # Check content type
    if (!isset($_SERVER['CONTENT_TYPE']) || 'application/json' != $_SERVER['CONTENT_TYPE']) {
        http_response_code(412);
        echo json_encode("content-type is not set to application/json");
        exit;
    }

    # Get user data
    $users = json_decode(file_get_contents(__DIR__ . '/user.json'));

    # Get request data
    $raw_data = file_get_contents("php://input");

    if (!$data = json_decode($raw_data)) {
        http_response_code(422);
        echo json_encode("registration data is not valid json");
        exit;
    }

    # User email
    $email     = $data->email;
    $duplicate = false;

    # is email exists
    foreach ($users as $key => $user) {
        # Check email
        if ($validateWithEMail && $email === $user->email) {
            # Is maximum count exceed
            if ($maximumRegistrationCount < $user->count + 1) {
                http_response_code(406);
                echo json_encode([
                    "message" => "you are already registered in this event, please check your email!",
                ]); // Email matched message
                exit;
            }
            $duplicate = true;
        }

        # Check ip
        if ($validateWithIP && $ip === $user->ip) {
            # Is maximum count exceed
            if ($maximumRegistrationCount < $user->count + 1) {
                http_response_code(406);
                echo json_encode([
                    "message" => "you are already registered in this event, please check your email!",
                ]); // IP matched message
                exit;
            }
            $duplicate = true;
        }

        # Update count
        if ($duplicate) {
            $users[$key]->count = $user->count + 1;
        }
    }

    # Add ip address to custom filed
    if (isset($data->customFields)) {
        $data->customFields->ip_address = $ip;
    } else {
        $data->customFields = [
            "ip_address" => $ip,
        ];
    }

    # Post data to StealthSeminar
    $curl_handle = curl_init();
    curl_setopt($curl_handle, CURLOPT_URL, "https://api.joinnow.live/webinars/$webinarShortId/registration");
    curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($data)),
    ]);
    $response  = curl_exec($curl_handle);
    $http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    curl_close($curl_handle);

    # Return data to user
    http_response_code($http_code);
    echo $response;

    # Add to user list
    if (!$duplicate) {
        array_push($users, [
            "email" => $email,
            "ip"    => $ip,
            "time"  => time(),
            "count" => 1,
        ]);
    }
    if ($http_code === 200) {
        file_put_contents(__DIR__ . '/user.json', json_encode($users));
    }
    exit;
}

# Default response
http_response_code(405);
