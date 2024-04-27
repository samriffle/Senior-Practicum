<?php
// Get the posted data
$data = json_decode(file_get_contents("php://input"));

// Ensure data is valid
if (isset($data->id) && isset($data->password)) {
    $id = $data->id;
    $password = $data->password;

    // Append the data to the CSV file
    $file = 'userids.csv';
    $line = "$id,$password\n";
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

    http_response_code(200);
    echo json_encode(array("message" => "Data appended successfully."));
} else {
    // Set bad request response code
    http_response_code(400);
    echo json_encode(array("message" => "Invalid data."));
}
?>
