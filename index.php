<?php
// Database connection
$servername = "localhost";
$username = "root"; // Change to your MySQL username
$password = ""; // Change to your MySQL password
$dbname = "ats"; // The database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$supported_extensions = ['docx', 'doc', 'html', 'htm', 'rtf', 'pdf', 'txt'];
// Helper function to extract candidate details
function extractCandidateDetails($text) {
$namePattern = '/\b(?!Sent\b|sent\b|From\b|To\b|com\b|AM\b|Welcome\b|February\b|back\b|aishah\b|route@monster.com\b|monster\b|Workopolis\b|route\b|Sign\b|Out\b|Subject\b|CorporateWorks\b|NicheNetwork\b|Jobs\b|Overview\b|Customer\b|Support\b|Template\b)([A-Za-zÀ-ÿ]+(?:[-\sA-Za-zÀ-ÿ]+)*)\b/';
    $emailPattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}/';
    $phonePattern = '/\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/';

    preg_match($namePattern, $text, $nameMatches);
    preg_match($emailPattern, $text, $emailMatches);
    preg_match($phonePattern, $text, $phoneMatches);

    $skillsKeywords = ['PHP', 'Java', 'Python', 'JavaScript', 'SQL', 'C++', 'React', 'Angular', 'Node.js', 'HTML', 'CSS'];
    $skillsFound = [];

    foreach ($skillsKeywords as $skill) {
        if (stripos($text, $skill) !== false) {
            $skillsFound[] = $skill;
        }
    }

    return [
        'name' => $nameMatches[0] ?? '',
        'email' => $emailMatches[0] ?? '',
        'phone' => $phoneMatches[0] ?? '',
        'skills' => implode(', ', $skillsFound)
    ];
}

// Handle multiple file uploads
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['files'])) {
    $files = $_FILES['files'];
    
    // Loop through each file
$fileAlreadyUploaded = false; // Initialize the flag outside the loop
    for ($i = 0; $i < count($files['name']); $i++) {
        $file_name = $files['name'][$i];
        $file_tmp = $files['tmp_name'][$i];
        $file_error = $files['error'][$i];

        if ($file_error === 0) {
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $file_new_name = uniqid('', true) . '.' . $file_ext;
            $file_destination = 'uploads/' . $file_new_name;

            // Read file content and generate hash
            $file_content = file_get_contents($file_tmp);
            $file_hash = hash('sha256', $file_content);

            // Check if file hash already exists
            $stmt = $conn->prepare("SELECT id FROM files WHERE file_hash = ?");
            $stmt->bind_param("s", $file_hash);
            $stmt->execute();
            $stmt->store_result();

 if ($stmt->num_rows > 0) {
            // Only show the message once
            if (!$fileAlreadyUploaded) {
                echo "<p style='color: red;'>This file has already been uploaded.</p>";
                $fileAlreadyUploaded = true; // Prevent further messages
            }
        } else {
            if (move_uploaded_file($file_tmp, $file_destination)) {
                $file_url = "http://localhost/at/uploads/" . $file_new_name;

// Check if the file extension is supported
if (!in_array($file_ext, $supported_extensions)) {
    // Redirect to the index page with an error message
    header("Location: index.php?error=Unsupported file type.");
    exit;
}


                    $text = '';
                    switch ($file_ext) {
                        case 'docx':
                            require_once 'vendor/autoload.php';
                            $phpWord = \PhpOffice\PhpWord\IOFactory::load($file_destination);
                            foreach ($phpWord->getSections() as $section) {
                                foreach ($section->getElements() as $element) {
                                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                        foreach ($element->getElements() as $textElement) {
                                            if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                                $text .= $textElement->getText();
                                            }
                                        }
                                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                                        $text .= $element->getText();
                                    }
                                }
                            }
                            break;

				case 'doc':
     	  			 // Handle DOC files (you can use a library like PHPWord for DOC or other methods)
        		echo "DOC file type handling not implemented yet.";
       			 break;

                        case 'pdf':
                            require_once 'vendor/autoload.php';
                            $parser = new \Smalot\PdfParser\Parser();
                            $pdf = $parser->parseFile($file_destination);
                            $text = $pdf->getText();
                            break;
                            case 'html':
   				 case 'htm':
  	     		 $html = file_get_contents($file_destination);
      			  $dom = new DOMDocument;
        			@$dom->loadHTML($html);
       				 $text = strip_tags($dom->textContent);
       				 break;
                        case 'txt':
                        case 'rtf':
                            $text = file_get_contents($file_destination);
                            break;
    			default:
      			  // Redirect back to the index page if an unsupported file type is uploaded
       			 header("Location: index.php?error=Unsupported file type.");
       			 exit;
}

                    $text = preg_replace('/\s+/', ' ', $text);
                    $text = trim($text);

                    $candidate = extractCandidateDetails($text);
                    $name = $candidate['name'];
                    $email = $candidate['email'];
                    $phone = $candidate['phone'];
                    $skills = $candidate['skills'];

                    $stmt = $conn->prepare("INSERT INTO files (file_name, file_path, file_url, name, email, phone, file_type, file_hash, skills) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssssss", $file_name, $file_destination, $file_url, $name, $email, $phone, $file_ext, $file_hash, $skills);
                    $stmt->execute();

                    echo "File uploaded and data inserted successfully!";
                } else {
                    echo "Failed to upload file.";
                }
            }
        } else {
            echo "Error uploading file.";
        }
    }
}

// Handle saving hot candidates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hot_candidates'])) {
    $conn->query("DELETE FROM hot_candidates");

    if (!empty($_POST['hot_candidates'])) {
        $stmt = $conn->prepare("INSERT INTO hot_candidates (file_id) VALUES (?)");
        foreach ($_POST['hot_candidates'] as $file_id) {
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
        }
    }
    echo "Hot candidates updated successfully!";
}

// Handle saving notes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_note'])) {
    $file_id = $_POST['file_id'];
    $note = $_POST['note'];

    // Insert the note into the database
    $stmt = $conn->prepare("INSERT INTO notes (file_id, note) VALUES (?, ?)");
    $stmt->bind_param("is", $file_id, $note);
    $stmt->execute();

    // Redirect to avoid resubmission on refresh
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_file'])) {
    $file_id = $_POST['delete_file_id'];

    // Delete related records in hot_candidates and notes
    $conn->begin_transaction(); // Start a transaction

    try {
        // Delete related notes
        $stmt = $conn->prepare("DELETE FROM notes WHERE file_id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();

        // Delete related hot candidates
        $stmt = $conn->prepare("DELETE FROM hot_candidates WHERE file_id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();

        // Delete the file record itself
        $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();

        // Commit the transaction
        $conn->commit();

        echo "File and related data deleted successfully!";
    } catch (Exception $e) {
        // Rollback the transaction if something goes wrong
        $conn->rollback();
        echo "Error deleting file: " . $e->getMessage();
    }
}

// Fetch all uploaded files
// Pagination setup
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $limit;

// Fetch total number of files
$totalResult = $conn->query("SELECT COUNT(*) AS total FROM files");
$totalFiles = $totalResult->fetch_assoc()['total'];
$totalPages = ($totalFiles > 0) ? ceil($totalFiles / $limit) : 1; // Ensure at least 1 page

// Fetch paginated results
$stmt = $conn->prepare("
    SELECT f.*, hc.file_id AS hot 
    FROM files f 
    LEFT JOIN hot_candidates hc ON f.id = hc.file_id 
    ORDER BY f.uploaded_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

include 'upload_page.html'; // Include the HTML page

?>
<?php
$conn->close();
?>












