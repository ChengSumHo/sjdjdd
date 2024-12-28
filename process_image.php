<?php
// Disable error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// But log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Increase execution time limit
set_time_limit(300); // 5 minutes

// Function to send JSON response
function send_json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Error handler to catch any PHP errors and return them as JSON
function json_error_handler($errno, $errstr, $errfile, $errline) {
    $error = array(
        'success' => false,
        'error' => "PHP Error: [$errno] $errstr in $errfile on line $errline"
    );
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    send_json_response($error);
}

// Set the custom error handler
set_error_handler("json_error_handler");

// Main code starts here
error_log("Script started");
$response = array('success' => false, 'error' => 'Unknown error occurred');

// Use forward slashes for paths to avoid issues
$input_dir = "C:/xampp/htdocs/FYPpage/FOCAL-main/demo/input/";
$output_dir = "C:/xampp/htdocs/FYPpage/FOCAL-main/demo/output/";
$main_script = "C:/xampp/htdocs/FYPpage/FOCAL-main/main.py";

// Web-accessible path to the output directory
$web_output_dir = "/FYPpage/FOCAL-main/demo/output/";

// Check if directories exist
if (!file_exists($input_dir) || !file_exists($output_dir)) {
    error_log("Directory not found. Input dir: " . (file_exists($input_dir) ? "exists" : "not found") . ", Output dir: " . (file_exists($output_dir) ? "exists" : "not found"));
    $response['error'] = "Input or output directory not found.";
    send_json_response($response);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received");
    if (isset($_FILES["imageToUpload"])) {
        $input_file = $input_dir . basename($_FILES["imageToUpload"]["name"]);
        $input_filename = basename($_FILES["imageToUpload"]["name"]);
        
        error_log("Input file: $input_file");
        
        $file_name_without_ext = pathinfo($input_filename, PATHINFO_FILENAME);
        $output_filename = $file_name_without_ext . '.png';
        $expected_output_file = $output_dir . $output_filename;
        
        error_log("Expected output file: $expected_output_file");

        if (move_uploaded_file($_FILES["imageToUpload"]["tmp_name"], $input_file)) {
            error_log("File uploaded successfully");
            error_log("Input file permissions: " . substr(sprintf('%o', fileperms($input_file)), -4));
            error_log("Output directory permissions: " . substr(sprintf('%o', fileperms($output_dir)), -4));
            
            // Run the FOCAL model
            $command = "python \"$main_script\" --type test_single --input_size 1024 --gt_ratio 16 --train_bs 4 --test_bs 8 --save_res 1 --gpu 0,1,2,3 --metric cosine --path_input demo/input/ --path_gt demo/gt/ --nickname demo";
            error_log("Executing command: $command");
            $output = shell_exec($command);
            error_log("Command output: $output");

            // Add a delay to allow time for file creation (adjust as needed)
            sleep(5);

            // Check if the expected output file exists, including possible variations
            error_log("Checking for output file: $expected_output_file");
            $output_file_found = false;
            $actual_output_file = '';

            // List all files in the output directory
            $files = scandir($output_dir);
            foreach($files as $file) {
                // Check if the file name starts with the expected name (ignoring extra dots)
                if (strpos($file, $file_name_without_ext) === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'png') {
                    $output_file_found = true;
                    $actual_output_file = $output_dir . $file;
                    break;
                }
            }

            if ($output_file_found) {
                error_log("Output file found: $actual_output_file");
                error_log("Output file size: " . filesize($actual_output_file) . " bytes");
                error_log("Output file permissions: " . substr(sprintf('%o', fileperms($actual_output_file)), -4));
                $response['success'] = true;
                $response['result'] = $web_output_dir . basename($actual_output_file);
            } else {
                error_log("Output file not found. Listing directory contents:");
                foreach($files as $file) {
                    error_log($file);
                }
                $response['error'] = "Output file not found. Expected something like: $expected_output_file";
            }

            // Clean up: remove the input file after processing
            if (file_exists($input_file)) {
                unlink($input_file);
                error_log("Input file removed");
            } else {
                error_log("Input file not found for removal");
            }
        } else {
            error_log("Failed to move uploaded file");
            $response['error'] = "Failed to move uploaded file.";
        }
    } else {
        error_log("No file uploaded");
        $response['error'] = "No file uploaded.";
    }
} else {
    error_log("Invalid request method");
    $response['error'] = "Invalid request method. Use POST to upload an image.";
}

error_log("Sending response: " . json_encode($response));
send_json_response($response);
?>