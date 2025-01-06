<?php
include "db.php";

session_start(); // Ensure the session is started


if (isset($_POST['approve_user'])) {
    $customer_id = $_POST['user_id']; // Assuming the input is already sanitized before this point

    // Begin the transaction
    mysqli_begin_transaction($conn);

    try {
        // First query: Update the status in complaints_detail table
        $query = "UPDATE complaints_detail SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
        }

        // Bind parameters (s for string, i for integer)
        $status = '8';
        mysqli_stmt_bind_param($stmt, 'si', $status, $customer_id);

        // Execute the query
        $query_run = mysqli_stmt_execute($stmt);
        if (!$query_run) {
            throw new Exception('Failed to execute query: ' . mysqli_stmt_error($stmt));
        }

        // Commit transaction if succeeded
        mysqli_commit($conn);
        echo json_encode(['status' => 200]);

        // Close the prepared statement
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $res = [
            'status' => 500,
            'message' => 'Error occurred: ' . $e->getMessage()
        ];
        echo json_encode($res);
    }
}


//requirements rejected

if (isset($_POST['save_reason'])) {
    try {
        // Sanitize input values
        $reason = $_POST['reason']; // Assuming validation and sanitization before this point
        $customer_id = $_POST['problem_id']; // Assuming validation and sanitization before this point

        // Start the transaction
        mysqli_begin_transaction($conn);

        // First query: Update the status in complaints_detail table
        $query = "UPDATE complaints_detail SET feedback = ?, status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
        }

        // Bind parameters (s for string, i for integer)
        $status = 19;
        mysqli_stmt_bind_param($stmt, 'sii', $reason, $status, $customer_id);

        // Execute the query
        $query_run = mysqli_stmt_execute($stmt);
        if (!$query_run) {
            throw new Exception('Failed to execute query: ' . mysqli_stmt_error($stmt));
        }

        // Commit transaction if all succeeded
        mysqli_commit($conn);
        echo json_encode(['status' => 200]);

        // Close the prepared statement
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $res = [
            'status' => 500,
            'message' => 'Error: ' . $e->getMessage()
        ];
        echo json_encode($res);
    }
}

//comments query to give by user

if (isset($_POST['edit_user'])) {
    $customer_id = mysqli_real_escape_string($conn, $_POST['user_id']);

    $query = "SELECT * FROM manager WHERE task_id='$customer_id'";
    $query_run = mysqli_query($conn, $query);

    $User_data = mysqli_fetch_array($query_run);
    $query_date = $User_data['query_date'];
    $current_date = date('Y-m-d');

    // Calculate the difference in days between current date and query date
    $date_diff = (strtotime($current_date) - strtotime($query_date)) / (60 * 60 * 24);

    if($date_diff < 5 && !empty($User_data['comment_query'])){
        $readonly = true;
    }// Check if the reply is still empty and 5 days have passed
    else{
        $readonly = false; // Make it editable if conditions are met
    }

    if ($query_run) {
        $res = [
            'status' => 200,
            'message' => 'details Fetch Successfully by id',
            'data' => $User_data,
            'readonly' => $readonly,
            'date_diff' => $date_diff
        ];
        echo json_encode($res);
        return;
    } else {
        $res = [
            'status' => 500,
            'message' => 'Details Not Deleted'
        ];
        echo json_encode($res);
        return;
    }
}

//query save user
if (isset($_POST['save_edituser'])) {
    $customer_id = mysqli_real_escape_string($conn, $_POST['task_id']);
    $query = mysqli_real_escape_string($conn, $_POST['comment_query']);
    $reply = mysqli_real_escape_string($conn, $_POST['comment_reply']);

    $query = "UPDATE manager SET comment_query='$query',query_date=NOW() WHERE task_id='$customer_id'";
    $query_run = mysqli_query($conn, $query);

    if ($query_run) {
        $res = [
            'status' => 200,
            'message' => 'details Updated Successfully'
        ];
        echo json_encode($res);
        return;
    } else {
        $res = [
            'status' => 500,
            'message' => 'Details Not Deleted'
        ];
        echo json_encode($res);
        return;
    }
}

//get image
if (isset($_POST['get_image'])) {
    $user_id = $_POST['user_id'];

    // Query to fetch the image based on user ID
    $query = "SELECT id, images FROM complaints_detail WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['status' => 200, 'data' => $row]);
    } else {
        echo json_encode(['status' => 500, 'message' => 'Image not found']);
    }

    $stmt->close();
    $conn->close();
}

//after images
if (isset($_POST['after_image'])) {
    $user_id = $_POST['user_id'];

    // Query to fetch the image based on user ID
    $query = "SELECT id, after_photo FROM worker_taskdet WHERE task_id= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['status' => 200, 'data' => $row]);
    } else {
        echo json_encode(['status' => 500, 'message' => 'Image not found']);
    }

    $stmt->close();
    $conn->close();
}




if (isset($_POST['facultydetails'])) {
    $fac_id = $_POST['fac_id'];
    $query1 = "SELECT * FROM facultys WHERE id='$fac_id'";

    $query_run1 = mysqli_query($conn,$query1);
    $fac_data = mysqli_fetch_array($query_run1);
    if ($query_run1) {
        $res = [
            'status' => 200,
            'message' => 'details Fetch Successfully by id',
            'data1'=>$fac_data,
        ];
        echo json_encode($res);
        return;
    } else {
        $res = [
            'status' => 500,
            'message' => 'Details Not Deleted'
        ];
        echo json_encode($res);
        return;
    }
}

?>