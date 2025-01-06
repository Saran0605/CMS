<?php
include("db.php");
session_start();
if (isset($_POST['faculty_id'])) {
    $faculty_id = $_SESSION['faculty_id']; //'faculty_id' is stored in session
}
if (isset($_POST['hod_id'])) {
    $hod_id =  $_session['hod_id']; //hod id in session
}
if (isset($_POST['eo_id'])) {
    $eo_id = $_session['eo_id']; //eo id in session
}

// Define the counter file path
$counterFilePath = './uploads/counter.txt';

// Function to get the next file number
function getNextFileNumber($counterFilePath)
{
    if (file_exists($counterFilePath)) {
        $file = fopen($counterFilePath, 'r');
        $lastNumber = (int)fgets($file);
        fclose($file);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }
    $file = fopen($counterFilePath, 'w');
    fwrite($file, $nextNumber);
    fclose($file);
    return $nextNumber;
}


$action = $_GET['action'] ?? '';

switch ($action) {
    //Worker backend
    //accept complaint by head
    case 'wacceptcomp':
        $problem_id = $_POST['user_id'] ?? null;

        if ($problem_id) {
            // Prepare the SQL query
            $updateQuery = "UPDATE complaints_detail SET status = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $updateQuery);
    
            if ($stmt) {
                // Bind parameters to the prepared statement
                $status = 10;
                mysqli_stmt_bind_param($stmt, "ii", $status, $problem_id);
    
                // Execute the prepared statement
                if (mysqli_stmt_execute($stmt)) {
                    echo "Success: Complaint accepted and status updated successfully!";
                } else {
                    echo "Error: Failed to update complaint status.";
                }
    
                // Close the statement
                mysqli_stmt_close($stmt);
            } else {
                echo "Error: Failed to prepare the update query.";
            }
        } else {
            echo "Error: Problem ID is missing.";
        }
        break;

        //view complaint in head
        case 'whviewcomp':
            $complain_id = $_POST['user_id'];

    // First query
    $query = "
        SELECT cd.*, faculty_details.faculty_name, faculty_details.faculty_contact, 
               faculty_details.faculty_mail, faculty_details.department, cd.block_venue
        FROM complaints_detail cd
        JOIN faculty_details ON cd.faculty_id = faculty_details.faculty_id
        WHERE cd.id = ?
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $complain_id);
    mysqli_stmt_execute($stmt);
    $User_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

   

    // Response
    if ($User_data) {
        echo json_encode([
            'status' => 200,
            'message' => 'Details fetched successfully by ID',
            'data' => $User_data,
        ]);
    } else {
        echo json_encode([
            'status' => 404,
            'message' => 'Details not found'
        ]);
    }
    break;

    //bacnkend for workers
    case 'wviewcomp':
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : null;

    if ($task_id === null) {
        die(json_encode(['error' => 'Task ID not provided']));
    }

    $sql = "SELECT 
        f.faculty_name, 
        f.faculty_contact, 
        cd.block_venue, 
        cd.venue_name, 
        cd.problem_description, 
        cd.days_to_complete
    FROM 
        complaints_detail AS cd
    JOIN 
        faculty_details AS f ON cd.faculty_id = f.faculty_id
    WHERE 
        cd.id = (SELECT problem_id FROM manager WHERE task_id = ?)
";


    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $response = array();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response = array(
            'faculty_name' => $row['faculty_name'],
            'faculty_contact' => $row['faculty_contact'],
            'block_venue' => $row['block_venue'],
            'venue_name' => $row['venue_name'],
            'problem_description' => $row['problem_description'],
            'days_to_complete' => $row['days_to_complete']
        );
        echo json_encode($response);
    } else {
        $response['error'] = 'No details found for this complaint.';
    }

  

    $stmt->close();
    break;


    //work completion status update
    case 'workcompletion':
        $taskId = $_POST['task_id'];
    $completionStatus = $_POST['completion_status'];
    $reason = $_POST['reason'];
    $p_id = $_POST['p_id'];
    $oname = $_POST['o_name'];
    $wname = $_POST['w_name'];
    $amt = $_POST['amt'];
    $name = current(array_filter([$oname, $wname]));

    $insertQuery = "UPDATE manager SET worker_id='$name' WHERE task_id='$taskId'";
    if (mysqli_query($conn, $insertQuery)) {
          
        
            $updateComplaintSql = "UPDATE complaints_detail 
                                   SET status = 11,worker_id='$name',amount_spent='$amt', task_completion = ?,reason = ?,date_of_completion = NOW()
                                   WHERE id = (SELECT problem_id FROM manager WHERE task_id = ?)";
            if ($stmt = $conn->prepare($updateComplaintSql)) {
                $stmt->bind_param("ssi", $completionStatus,$reason,$taskId);
                if (!$stmt->execute()) {
                    echo "Update failed: (" . $stmt->errno . ") " . $stmt->error;
                } else {
                    echo "Complaint status and task completion updated successfully.";
                }
                $stmt->close();
            } else {
                echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
            }
        
            $imgAfterName = null;
            if (isset($_FILES['img_after']) && $_FILES['img_after']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'imgafter/';
                $imgAfterName = basename($_FILES['img_after']['name']); 
                $uploadFile = $uploadDir . $imgAfterName; 
            
                if (move_uploaded_file($_FILES['img_after']['tmp_name'], $uploadFile)) {
                    echo "File successfully uploaded: " . $imgAfterName;
            
                    $insertTaskDetSql = "INSERT INTO worker_taskdet (task_id, task_completion, after_photo, work_completion_date) 
                                         VALUES (?, ?, ?, NOW())";
                    if ($stmt = $conn->prepare($insertTaskDetSql)) {
                        $stmt->bind_param("sss", $taskId, $completionStatus, $imgAfterName);
                        if (!$stmt->execute()) {
                            echo "Insertion into worker_taskdet failed: (" . $stmt->errno . ") " . $stmt->error;
                        } else {
                            echo "Record inserted successfully into worker_taskdet.";
                        }
                        $stmt->close();
                    } else {
                        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
                    }
                } else {
                    echo "File upload failed.";
                }
            } else {
                echo "No file uploaded or file upload error.";
            }   

        
    }
    break;



    //show before image for workers
    case 'wbeforeimg':
        $task_id = isset($_POST['task_id']) ? $_POST['task_id'] : ''; 

    if (empty($task_id)) {
        echo json_encode(['status' => 400, 'message' => 'Task ID not provided']);
        exit;
    }

    $query = "SELECT images FROM complaints_detail WHERE id = (SELECT problem_id FROM manager WHERE task_id = ?)";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(['status' => 500, 'message' => 'Prepare statement failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $image_filename = basename($row['images']); 
        $image_path = 'uploads/' . $image_filename; 
        
        echo json_encode(['status' => 200, 'data' => ['after_photo' => $image_path]]);
    } else {
        echo json_encode(['status' => 500, 'message' => 'No image found']);
    }

    $stmt->close();
    break;


    //after image for workers
    case 'wafterimage':
        $task_id = isset($_POST['task_id']) ? $_POST['task_id'] : ''; 

        if (empty($task_id)) {
            echo json_encode(['status' => 400, 'message' => 'Task ID not provided']);
            exit;
        }
    
        $query = "SELECT after_photo FROM worker_taskdet WHERE task_id = ?";
        $stmt = $conn->prepare($query);
    
        if (!$stmt) {
            echo json_encode(['status' => 500, 'message' => 'Prepare statement failed: ' . $conn->error]);
            exit;
        }
    
        $stmt->bind_param('i', $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            $image_filename = basename($row['after_photo']); 
            $image_path = 'imgafter/' . $image_filename; 
            
            echo json_encode(['status' => 200, 'data' => ['after_photo' => $image_path]]);
        } else {
            echo json_encode(['status' => 500, 'message' => 'No image found']);
        }
    
        $stmt->close();
        exit;



        //worker assign in completion
        case 'wworkerassign':
            $work = $_POST['worker_dept'];  
    $sql8 = "SELECT worker_id, worker_first_name FROM worker_details WHERE worker_dept = ? AND usertype = 'worker'";
    $stmt = $conn->prepare($sql8);
    $stmt->bind_param("s",$work);
    $stmt->execute();
    $result8 = $stmt->get_result();


   
    $options = '';


    while ($row = mysqli_fetch_assoc($result8)) {
        $options .= '<option value="' . $row['worker_id'] . '">' . $row['worker_id'] . ' - ' . $row['worker_first_name'] . '</option>';

    }


    echo $options;
    break; 





    //Faculty backend Starts
    //faculty raise complaint
    case 'facraisecomp':
        $faculty_id = mysqli_real_escape_string($conn, $_POST['faculty_id']);
    $fac_id = mysqli_real_escape_string($conn,$_POST['cfaculty']);
    $fac_id = preg_replace('/\D/', '', $fac_id); 
    $block_venue = mysqli_real_escape_string($conn, $_POST['block_venue']);
    $venue_name = mysqli_real_escape_string($conn, $_POST['venue_name']);
    $type_of_problem = mysqli_real_escape_string($conn, $_POST['type_of_problem']);
    $problem_description = mysqli_real_escape_string($conn, $_POST['problem_description']);
    $itemno = mysqli_real_escape_string($conn, $_POST['itemno']);
    $date_of_reg = mysqli_real_escape_string($conn, $_POST['date_of_reg']);
    $status = $_POST['status'];

    // Handle file upload
    $images = "";
    $uploadFileDir = './uploads/';

    if (!is_dir($uploadFileDir) && !mkdir($uploadFileDir, 0755, true)) {
        echo json_encode(['status' => 500, 'message' => 'Failed to create upload directory.']);
        exit;
    }

    if (isset($_FILES['images']) && $_FILES['images']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['images']['tmp_name'];
        $fileNameCmps = explode(".", $_FILES['images']['name']);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $nextFileNumber = getNextFileNumber($counterFilePath);
            $newFileName = str_pad($nextFileNumber, 10, '0', STR_PAD_LEFT) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $images = $newFileName;
            } else {
                echo json_encode(['status' => 500, 'message' => 'Error moving the uploaded file.']);
                exit;
            }
        } else {
            echo json_encode(['status' => 500, 'message' => 'Upload failed. Allowed types: jpg, jpeg, png.']);
            exit;
        }
    }





    // Insert data into the database
    $query = "INSERT INTO complaints_detail (faculty_id,fac_id,block_venue, venue_name, type_of_problem, problem_description,itemno, images, date_of_reg, status) 
              VALUES ('$faculty_id','$fac_id', '$block_venue', '$venue_name', '$type_of_problem', '$problem_description','$itemno', '$images', '$date_of_reg', '$status')";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 200, 'message' => 'Success']);
    } else {
        echo json_encode(['status' => 500, 'message' => 'Error inserting data: ' . mysqli_error($conn)]);
    }
    break;



    //Deleting the complaint
    case 'facdelcomp':
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $query = "DELETE FROM complaints_detail WHERE id='$user_id'";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 200, 'message' => 'Deleted successfully']);
    } else {
        echo json_encode(['status' => 500, 'message' => 'Error deleting data: ' . mysqli_error($conn)]);
    }
    break;



    //Show before image 
    case 'facbimg':
        $id = $_POST['problem_id']; // Ensure id is set


    // Query to fetch the image based on id
    $query = "SELECT id, images FROM complaints_detail WHERE id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        echo json_encode(['status' => 500, 'message' => 'Prepare statement failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['status' => 200, 'data' => $row]);
    } else {
        echo json_encode(['status' => 500, 'message' => 'No image found']);
    }

    $stmt->close();
    $conn->close();
    break;


    //worker details showing in faculty
    case 'facworkerdet':
        $id = $_POST['id'];

    // SQL query to get worker details
    $query = "
    SELECT w.worker_first_name,
     w.worker_mobile
    FROM complaints_detail cd
    INNER JOIN manager m ON cd.id = m.problem_id
    INNER JOIN worker_details w ON m.worker_id = w.worker_id
    WHERE cd.id = ?
";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $worker = $result->fetch_assoc();
        echo json_encode(['status' => 200, 'worker_first_name' => $worker['worker_first_name'], 'worker_mobile' => $worker['worker_mobile']]);
    } else {
        echo json_encode(['status' => 500, 'message' => 'No worker details found for this id']);
    }

    $stmt->close();
    $conn->close();
    break;


    //Showing feedback for faculty
    case 'facgetfeedback':
        $id = $_POST['id'];
        $feedback = $_POST['satisfaction_feedback']; // Combined feedback and satisfaction value
        $rating = $_POST['ratings']; // Get rating
    
        // Validate inputs
        if (empty($id) || empty($feedback)) {
            echo json_encode(['status' => 400, 'message' => 'Problem ID or Feedback is missing']);
            exit;
        }
    
        // Check if feedback already exists for the given id
        $checkQuery = "SELECT feedback FROM complaints_detail WHERE id = ?";
        $stmt = $conn->prepare($checkQuery);
    
        if (!$stmt) {
            echo json_encode(['status' => 500, 'message' => 'Prepare statement failed: ' . $conn->error]);
            exit;
        }
    
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();
        $feedbackExists = $stmt->num_rows > 0; // Check if a row exists for the given id
    
        $stmt->close();
    
        // Update feedback if it exists, and set status to 14
        if ($feedbackExists) {
            // Update existing feedback, rating, and set status to 14
            $query = "UPDATE complaints_detail SET feedback = ?, rating = ?, status = 14 WHERE id = ?";
        } else {
            // Insert new feedback (same query logic as update), with status set to 14
            $query = "UPDATE complaints_detail SET feedback = ?, rating = ?, status = 14 WHERE id = ?";
        }
    
        $stmt = $conn->prepare($query);
    
        if (!$stmt) {
            echo json_encode(['status' => 500, 'message' => 'Prepare statement failed: ' . $conn->error]);
            break;
        }
    
        // Bind parameters including the combined feedback value, rating, and ID
        $stmt->bind_param('sii', $feedback, $rating, $id);
    
        if ($stmt->execute()) {
            echo json_encode(['status' => 200, 'message' => 'Feedback updated successfully']);
        } else {
            echo json_encode(['status' => 500, 'message' => 'Query failed: ' . $stmt->error]);
        }
    
        $stmt->close();
        $conn->close();
        break;



        //geting faculty id and name for assigning
        $fac_id = $_SESSION['faculty_id'];

        case 'getfaculty':
            $sql8 =  "SELECT * FROM faculty WHERE dept=(SELECT department FROM faculty_details WHERE faculty_id='$fac_id')";
            $result8 = mysqli_query($conn, $sql8);
        
            $options = '';
            $options .= '<option value="">Select a Faculty</option>';
        
        
        
            while ($row = mysqli_fetch_assoc($result8)) {
                $options .= '<option value="' . $row['id'] . '">' . $row['id'] . ' - ' . $row['name'] . '</option>';
        
            }
        
        
            echo $options;
            break;

        //password change for faculty
        case 'facchangepass':
            $newp = $_POST['pass'];
            $sql = "UPDATE faculty_details SET password = '$newp' WHERE faculty_id ='$fac_id'";
            if(mysqli_query($conn,$sql)){
                 $res=[
                "status"=>200,
                "message"=>"password changed",
            ];
            echo json_encode($res);
            break;
    }
            


            
        





















}