<?php

function validate_token() {
    $headers = apache_request_headers();
    if (!isset($headers['Authorization'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Access Token is missing']);
        exit;
    }

    $access_token = $headers['Authorization'];

    if (!is_valid_token($access_token)) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Invalid Access Token']);
        exit;
    }

    return get_user_from_token($access_token);
}

function is_valid_token($access_token) {
    return true; // Προσωρινή τιμή για δοκιμές.
}

function get_user_from_token($access_token) {
    return [
        'user_id' => 12345,
        'username' => 'john_doe',
        'email' => 'john.doe@university.edu'
    ];
}
?>
