<?php
require_once dirname(__DIR__) . '/config/database.php';

// Define font path for FPDF before autoload
define('FPDF_FONTPATH', dirname(__DIR__) . '/fpdf/font/');

// Load FPDF first (required by FPDI)
$fpdfPath = dirname(__DIR__) . '/fpdf/fpdf.php';
if (file_exists($fpdfPath)) {
    require_once $fpdfPath;
} else {
    die("FPDF library not found at: $fpdfPath");
}

// Now load FPDI via Composer autoloader
require_once dirname(__DIR__) . '/fpdf/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

try {
    $pdo = getDBConnection();
    
    // Get loan ID from POST data (preferred) or loan_no (fallback for compatibility)
    $loan_id = $_POST['loan_id'] ?? '';
    $loan_no = $_POST['loan_no'] ?? '';
    
    if (empty($loan_id) && empty($loan_no)) {
        die("Loan ID or loan number is required.");
    }
    
    // Fetch loan and customer data - use loan_id if available, otherwise use loan_no
    // Since multiple loans can have the same loan_no, prefer loan_id (unique)
    // Include all customer and loan fields for complete data
    if (!empty($loan_id)) {
        $stmt = $pdo->prepare("
            SELECT 
                l.*,
                l.recovery_period,
                l.loan_days,
                l.interest_rate,
                c.id as customer_id,
                c.name as customer_name,
                c.mobile,
                c.address,
                c.place,
                c.pincode,
                c.customer_no,
                c.additional_number,
                c.reference,
                c.customer_photo,
                DATE_FORMAT(l.loan_date, '%d-%m-%Y') as formatted_loan_date,
                DATE_FORMAT(l.loan_date, '%Y-%m-%d') as loan_date_iso
            FROM loans l
            INNER JOIN customers c ON l.customer_id = c.id
            WHERE l.id = ?
        ");
        $stmt->execute([$loan_id]);
    } else {
        // Fallback: use loan_no and get the latest loan (highest id) for that customer
        $customer_id = $_POST['customer_id'] ?? '';
        if (!empty($customer_id)) {
            // If customer_id is provided, get the specific loan for that customer
            $stmt = $pdo->prepare("
                SELECT 
                    l.*,
                    l.recovery_period,
                    l.loan_days,
                    l.interest_rate,
                    c.id as customer_id,
                    c.name as customer_name,
                    c.mobile,
                    c.address,
                    c.place,
                    c.pincode,
                    c.customer_no,
                    c.additional_number,
                    c.reference,
                    c.customer_photo,
                    DATE_FORMAT(l.loan_date, '%d-%m-%Y') as formatted_loan_date,
                    DATE_FORMAT(l.loan_date, '%Y-%m-%d') as loan_date_iso
                FROM loans l
                INNER JOIN customers c ON l.customer_id = c.id
                WHERE l.loan_no = ? AND l.customer_id = ?
                ORDER BY l.loan_date DESC, l.id DESC
                LIMIT 1
            ");
            $stmt->execute([$loan_no, $customer_id]);
        } else {
            // No customer_id, just get latest by loan_no
            $stmt = $pdo->prepare("
                SELECT 
                    l.*,
                    l.recovery_period,
                    l.loan_days,
                    l.interest_rate,
                    c.id as customer_id,
                    c.name as customer_name,
                    c.mobile,
                    c.address,
                    c.place,
                    c.pincode,
                    c.customer_no,
                    c.additional_number,
                    c.reference,
                    c.customer_photo,
                    DATE_FORMAT(l.loan_date, '%d-%m-%Y') as formatted_loan_date,
                    DATE_FORMAT(l.loan_date, '%Y-%m-%d') as loan_date_iso
                FROM loans l
                INNER JOIN customers c ON l.customer_id = c.id
                WHERE l.loan_no = ?
                ORDER BY l.loan_date DESC, l.id DESC
                LIMIT 1
            ");
            $stmt->execute([$loan_no]);
        }
    }
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        die("Loan not found. Please ensure the loan exists for the selected customer and date.");
    }
    
    // Calculate loan period in days from loan_days or recovery_period
    $loanPeriodDays = "180 days"; // Default fallback (6 months = 180 days)
    if (!empty($loan["loan_days"])) {
        // Use loan_days directly (preferred)
        $days = intval($loan["loan_days"]);
        $loanPeriodDays = $days . " days";
    } elseif (!empty($loan["recovery_period"])) {
        // If recovery_period exists, try to extract days or convert from months
        $recoveryPeriod = trim($loan["recovery_period"]);
        if (preg_match('/(\d+)\s*(day|days?|d)/i', $recoveryPeriod, $matches)) {
            // Already in days format
            $loanPeriodDays = $matches[1] . " days";
        } elseif (preg_match('/(\d+)\s*(month|months?|m)/i', $recoveryPeriod, $matches)) {
            // Convert months to days (1 month = 30 days)
            $months = intval($matches[1]);
            $days = $months * 30;
            $loanPeriodDays = $days . " days";
        } elseif (is_numeric($recoveryPeriod)) {
            // If it's just a number, assume it's days
            $days = intval($recoveryPeriod);
            $loanPeriodDays = $days . " days";
        } else {
            // Keep as is if it's text
            $loanPeriodDays = $recoveryPeriod;
        }
    }
    
    // Get interest rate (ensure it's formatted correctly)
    $interestRate = !empty($loan["interest_rate"]) 
        ? number_format(floatval($loan["interest_rate"]), 2, '.', '') 
        : "1.00";
    
    // Get customer photo path
    $basePath = dirname(__DIR__);
    $customerPhotoPath = null;
    
    if (!empty($loan["customer_photo"])) {
        // Check if photo path exists in database
        $photoPath = $basePath . '/' . $loan["customer_photo"];
        if (file_exists($photoPath)) {
            $customerPhotoPath = $photoPath;
        }
    }
    
    // If photo not found in database path, try to find it by customer folder name
    if (empty($customerPhotoPath)) {
        $customerFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $loan["customer_name"]);
        $customerFolderName = str_replace(' ', '_', $customerFolderName);
        $customerFolderName = strtolower($customerFolderName);
        
        // Try different extensions
        $extensions = ['jpg', 'jpeg', 'png', 'gif'];
        foreach ($extensions as $ext) {
            $possiblePhotoPath = $basePath . '/uploads/' . $customerFolderName . '/customer_photo.' . $ext;
            if (file_exists($possiblePhotoPath)) {
                $customerPhotoPath = $possiblePhotoPath;
                break;
            }
        }
    }
    
    // Get ornament photo path
    $ornamentPhotoPath = null;
    
    if (!empty($loan["ornament_file"])) {
        // Check if ornament file path exists in database
        $ornamentPath = $basePath . '/' . $loan["ornament_file"];
        if (file_exists($ornamentPath)) {
            // Check if it's an image file (not PDF)
            $ornamentExtension = strtolower(pathinfo($ornamentPath, PATHINFO_EXTENSION));
            if (in_array($ornamentExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $ornamentPhotoPath = $ornamentPath;
            }
        }
    }
    
    // Continue with PDF generation using the existing template
    date_default_timezone_set('Asia/Kolkata');
    $currentDate = date('Y-m-d');
    
    // Load the existing PDF template
    $pdf = new Fpdi();
    $existing_pdf_path = dirname(__DIR__) . '/fpdf/Loan Statement.pdf';
    
    // Set the source file
    $pageCount = $pdf->setSourceFile($existing_pdf_path);
    
    // Loop through all the pages of the PDF
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $pdf->AddPage();
        
        // Import each page
        $tplId = $pdf->importPage($pageNo);
        $pdf->useTemplate($tplId, 10, 10, 200);
    
        // Apply changes only to the first page
        if ($pageNo == 1) {
    
            $pdf->SetTextColor(0, 0, 0);
    
            // Insert customer photo on the left side, centered
            if (!empty($customerPhotoPath)) {
                try {
                    // Photo position: left side, centered vertically
                    // Fixed size for customer photo
                    $photoX = 80;  // Left side position (in points)
                    $photoY = 43;  // Top position for photo (in points)
                    $photoWidth = 35;  // Fixed width in points
                    $photoHeight = 35; // Fixed height in points
                    
                    // Get image and handle EXIF orientation to auto-rotate
                    $imageExtension = strtolower(pathinfo($customerPhotoPath, PATHINFO_EXTENSION));
                    $tempImagePath = null;
                    
                    if (in_array($imageExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        // Check if GD library is available for image manipulation
                        if (function_exists('imagecreatefromjpeg') && function_exists('imagerotate')) {
                            // Read EXIF data for orientation (only works for JPEG)
                            $orientation = 1; // Default: no rotation
                            if (in_array($imageExtension, ['jpg', 'jpeg']) && function_exists('exif_read_data')) {
                                $exif = @exif_read_data($customerPhotoPath);
                                if (!empty($exif['Orientation'])) {
                                    $orientation = $exif['Orientation'];
                                }
                            }
                            
                            // Load image based on type
                            $sourceImage = null;
                            switch ($imageExtension) {
                                case 'jpg':
                                case 'jpeg':
                                    $sourceImage = @imagecreatefromjpeg($customerPhotoPath);
                                    break;
                                case 'png':
                                    $sourceImage = @imagecreatefrompng($customerPhotoPath);
                                    break;
                                case 'gif':
                                    $sourceImage = @imagecreatefromgif($customerPhotoPath);
                                    break;
                            }
                            
                            if ($sourceImage) {
                            $width = imagesx($sourceImage);
                            $height = imagesy($sourceImage);
                            
                            // Apply rotation based on EXIF orientation
                            $rotatedImage = $sourceImage;
                            $angle = 0;
                            $flip = false;
                            
                            switch ($orientation) {
                                case 3: // 180 degrees
                                    $angle = 180;
                                    break;
                                case 6: // 90 degrees clockwise (rotate -90 or 270)
                                    $angle = -90;
                                    $temp = $width;
                                    $width = $height;
                                    $height = $temp;
                                    break;
                                case 8: // 90 degrees counter-clockwise (rotate 90)
                                    $angle = 90;
                                    $temp = $width;
                                    $width = $height;
                                    $height = $temp;
                                    break;
                            }
                            
                            // Rotate image if needed
                            if ($angle != 0) {
                                $rotatedImage = imagerotate($sourceImage, $angle, 0);
                                imagedestroy($sourceImage);
                            }
                            
                            // Create temporary file for rotated image
                            $tempImagePath = sys_get_temp_dir() . '/customer_photo_' . uniqid() . '.jpg';
                            
                            // Save rotated image
                            imagejpeg($rotatedImage, $tempImagePath, 90);
                            imagedestroy($rotatedImage);
                            
                            // Calculate aspect ratio for fixed size box
                            $aspectRatio = $width / $height;
                            
                            // Adjust dimensions to fit in fixed box while maintaining aspect ratio
                            $finalWidth = $photoWidth;
                            $finalHeight = $photoHeight;
                            
                            if ($aspectRatio > ($photoWidth / $photoHeight)) {
                                // Image is wider, fit to width
                                $finalHeight = $photoWidth / $aspectRatio;
                            } else {
                                // Image is taller, fit to height
                                $finalWidth = $photoHeight * $aspectRatio;
                            }
                            
                            // Center the image in the fixed box
                            $offsetX = $photoX + (($photoWidth - $finalWidth) / 2);
                            $offsetY = $photoY + (($photoHeight - $finalHeight) / 2);
                            
                            // Insert the corrected image
                            $pdf->Image($tempImagePath, $offsetX, $offsetY, $finalWidth, $finalHeight);
                            
                            // Clean up temporary file
                            if (file_exists($tempImagePath)) {
                                @unlink($tempImagePath);
                            }
                            } else {
                                // Fallback: use original image if GD loading fails
                                $imageInfo = @getimagesize($customerPhotoPath);
                                if ($imageInfo !== false) {
                                    $imgWidth = $imageInfo[0];
                                    $imgHeight = $imageInfo[1];
                                    $aspectRatio = $imgWidth / $imgHeight;
                                    
                                    $finalWidth = $photoWidth;
                                    $finalHeight = $photoHeight;
                                    
                                    if ($aspectRatio > ($photoWidth / $photoHeight)) {
                                        $finalHeight = $photoWidth / $aspectRatio;
                                    } else {
                                        $finalWidth = $photoHeight * $aspectRatio;
                                    }
                                    
                                    $offsetX = $photoX + (($photoWidth - $finalWidth) / 2);
                                    $offsetY = $photoY + (($photoHeight - $finalHeight) / 2);
                                    
                                    $pdf->Image($customerPhotoPath, $offsetX, $offsetY, $finalWidth, $finalHeight);
                                }
                            }
                        } else {
                            // GD library not available - use original image without rotation
                            $imageInfo = @getimagesize($customerPhotoPath);
                            if ($imageInfo !== false) {
                                $imgWidth = $imageInfo[0];
                                $imgHeight = $imageInfo[1];
                                $aspectRatio = $imgWidth / $imgHeight;
                                
                                $finalWidth = $photoWidth;
                                $finalHeight = $photoHeight;
                                
                                if ($aspectRatio > ($photoWidth / $photoHeight)) {
                                    $finalHeight = $photoWidth / $aspectRatio;
                                } else {
                                    $finalWidth = $photoHeight * $aspectRatio;
                                }
                                
                                $offsetX = $photoX + (($photoWidth - $finalWidth) / 2);
                                $offsetY = $photoY + (($photoHeight - $finalHeight) / 2);
                                
                                $pdf->Image($customerPhotoPath, $offsetX, $offsetY, $finalWidth, $finalHeight);
                            }
                        }
                    }
                } catch (Exception $e) {
                    // If image insertion fails, continue without photo
                    error_log("Failed to insert customer photo: " . $e->getMessage());
                }
            }
    
            // Date (use loan date if available, otherwise current date)
            // Position to the right of the photo
            $pdf->SetFont('Arial', 'B', 9);
            $displayDate = !empty($loan["formatted_loan_date"]) 
                ? $loan["formatted_loan_date"] 
                : date('d-m-Y', strtotime($currentDate));
            // Position date to the right of photo (photo ends around x=50, so start at x=55)
            $pdf->Text(35, 46, $displayDate);
            
            // Extract year from date for display
            $year = '';
            if (!empty($loan["formatted_loan_date"])) {
                $dateParts = explode('-', $loan["formatted_loan_date"]);
                if (count($dateParts) == 3) {
                    $year = $dateParts[2]; // Format is dd-mm-yyyy
                }
            } 
            // Display year below date
           
            //Name - Position to the right of the photo
            $pdf->SetFont('Arial', 'B', 9);
            // Position name to the right of photo, slightly below date
            $pdf->Text(35, 57, $loan["customer_name"]);
    
            //Amount lent
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 53, $loan["principal_amount"]);
    
            //Cover Number
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 57, $loan["customer_no"]);
    
            //Debt Number
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 60, $loan["loan_no"]);
    
            //Debt Period (fetched from loan data - displayed in days)
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 63.5, $loanPeriodDays);
            
            //Amount Lent per gram (Interest Rate - fetched from loan data)
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(170, 67, $interestRate . "%");
    
            //Amount in Paragraphs
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(133, 83.75, $loan["principal_amount"]);
    
            //Amount in Declaration
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Text(101, 105.5, $loan["principal_amount"]);
    
            //Customer Details
            //Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(66, 126, $loan["customer_name"]);
    
            //Date of birth (using loan date or date_of_birth if available)
            $pdf->SetFont('Arial', 'B', 9);
            $dateToShow = !empty($loan["date_of_birth"]) 
                ? date('d-m-Y', strtotime($loan["date_of_birth"]))
                : (!empty($loan["formatted_loan_date"]) 
                    ? $loan["formatted_loan_date"] 
                    : date('d-m-Y', strtotime($loan["loan_date"])));
            $pdf->Text(66, 134, $dateToShow);
    
            //Address
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(66, 142, $loan["address"] ?? 'N/A');
    
            //Contact Number
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(66, 147, $loan["mobile"]);
    
            // Insert ornament photo in the customer details section (small fixed size in box)
            if (!empty($ornamentPhotoPath)) {
                try {
                    // Ornament photo position: right side of customer details section
                    // Small fixed size box
                    $ornamentX = 120;  // Right side position (in points)
                    $ornamentY = 121;  // Top position for ornament photo (in points)
                    $ornamentWidth = 33;  // Fixed width in points (small box)
                    $ornamentHeight = 33; // Fixed height in points (small box)
                    
                    // Get image and handle EXIF orientation to auto-rotate
                    $imageExtension = strtolower(pathinfo($ornamentPhotoPath, PATHINFO_EXTENSION));
                    $tempOrnamentImagePath = null;
                    
                    if (in_array($imageExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        // Check if GD library is available for image manipulation
                        if (function_exists('imagecreatefromjpeg') && function_exists('imagerotate')) {
                            // Read EXIF data for orientation (only works for JPEG)
                            $orientation = 1; // Default: no rotation
                            if (in_array($imageExtension, ['jpg', 'jpeg']) && function_exists('exif_read_data')) {
                                $exif = @exif_read_data($ornamentPhotoPath);
                                if (!empty($exif['Orientation'])) {
                                    $orientation = $exif['Orientation'];
                                }
                            }
                            
                            // Load image based on type
                            $sourceImage = null;
                            switch ($imageExtension) {
                                case 'jpg':
                                case 'jpeg':
                                    $sourceImage = @imagecreatefromjpeg($ornamentPhotoPath);
                                    break;
                                case 'png':
                                    $sourceImage = @imagecreatefrompng($ornamentPhotoPath);
                                    break;
                                case 'gif':
                                    $sourceImage = @imagecreatefromgif($ornamentPhotoPath);
                                    break;
                            }
                            
                            if ($sourceImage) {
                                $width = imagesx($sourceImage);
                                $height = imagesy($sourceImage);
                                
                                // Apply rotation based on EXIF orientation
                                $rotatedImage = $sourceImage;
                                $angle = 0;
                                
                                switch ($orientation) {
                                    case 3: // 180 degrees
                                        $angle = 180;
                                        break;
                                    case 6: // 90 degrees clockwise (rotate -90 or 270)
                                        $angle = -90;
                                        $temp = $width;
                                        $width = $height;
                                        $height = $temp;
                                        break;
                                    case 8: // 90 degrees counter-clockwise (rotate 90)
                                        $angle = 90;
                                        $temp = $width;
                                        $width = $height;
                                        $height = $temp;
                                        break;
                                }
                                
                                // Rotate image if needed
                                if ($angle != 0) {
                                    $rotatedImage = imagerotate($sourceImage, $angle, 0);
                                    imagedestroy($sourceImage);
                                }
                                
                                // Create temporary file for rotated image
                                $tempOrnamentImagePath = sys_get_temp_dir() . '/ornament_photo_' . uniqid() . '.jpg';
                                
                                // Save rotated image
                                imagejpeg($rotatedImage, $tempOrnamentImagePath, 90);
                                imagedestroy($rotatedImage);
                                
                                // Calculate aspect ratio for fixed size box
                                $aspectRatio = $width / $height;
                                
                                // Calculate size to fit in fixed box while maintaining aspect ratio
                                $finalWidth = $ornamentWidth;
                                $finalHeight = $ornamentHeight;
                                
                                if ($aspectRatio > ($ornamentWidth / $ornamentHeight)) {
                                    // Image is wider, fit to width
                                    $finalHeight = $ornamentWidth / $aspectRatio;
                                } else {
                                    // Image is taller, fit to height
                                    $finalWidth = $ornamentHeight * $aspectRatio;
                                }
                                
                                // Center the image in the fixed box
                                $offsetX = $ornamentX + (($ornamentWidth - $finalWidth) / 2);
                                $offsetY = $ornamentY + (($ornamentHeight - $finalHeight) / 2);
                                
                                // Draw a border box around the ornament photo area
                                $pdf->SetDrawColor(200, 200, 200); // Light gray border
                                $pdf->SetLineWidth(0.5);
                                $pdf->Rect($ornamentX, $ornamentY, $ornamentWidth, $ornamentHeight);
                                
                                // Insert the corrected image
                                $pdf->Image($tempOrnamentImagePath, $offsetX, $offsetY, $finalWidth, $finalHeight);
                                
                                // Clean up temporary file
                                if (file_exists($tempOrnamentImagePath)) {
                                    @unlink($tempOrnamentImagePath);
                                }
                            } else {
                                // Fallback: use original image if GD loading fails
                                $imageInfo = @getimagesize($ornamentPhotoPath);
                                if ($imageInfo !== false) {
                                    $imgWidth = $imageInfo[0];
                                    $imgHeight = $imageInfo[1];
                                    $aspectRatio = $imgWidth / $imgHeight;
                                    
                                    $finalWidth = $ornamentWidth;
                                    $finalHeight = $ornamentHeight;
                                    
                                    if ($aspectRatio > ($ornamentWidth / $ornamentHeight)) {
                                        $finalHeight = $ornamentWidth / $aspectRatio;
                                    } else {
                                        $finalWidth = $ornamentHeight * $aspectRatio;
                                    }
                                    
                                    $offsetX = $ornamentX + (($ornamentWidth - $finalWidth) / 2);
                                    $offsetY = $ornamentY + (($ornamentHeight - $finalHeight) / 2);
                                    
                                    // Draw a border box around the ornament photo area
                                    $pdf->SetDrawColor(200, 200, 200); // Light gray border
                                    $pdf->SetLineWidth(0.5);
                                    $pdf->Rect($ornamentX, $ornamentY, $ornamentWidth, $ornamentHeight);
                                    
                                    $pdf->Image($ornamentPhotoPath, $offsetX, $offsetY, $finalWidth, $finalHeight);
                                }
                            }
                        } else {
                            // GD library not available - use original image without rotation
                            $imageInfo = @getimagesize($ornamentPhotoPath);
                            if ($imageInfo !== false) {
                                $imgWidth = $imageInfo[0];
                                $imgHeight = $imageInfo[1];
                                $aspectRatio = $imgWidth / $imgHeight;
                                
                                $finalWidth = $ornamentWidth;
                                $finalHeight = $ornamentHeight;
                                
                                if ($aspectRatio > ($ornamentWidth / $ornamentHeight)) {
                                    $finalHeight = $ornamentWidth / $aspectRatio;
                                } else {
                                    $finalWidth = $ornamentHeight * $aspectRatio;
                                }
                                
                                $offsetX = $ornamentX + (($ornamentWidth - $finalWidth) / 2);
                                $offsetY = $ornamentY + (($ornamentHeight - $finalHeight) / 2);
                                
                                // Draw a border box around the ornament photo area
                                $pdf->SetDrawColor(200, 200, 200); // Light gray border
                                $pdf->SetLineWidth(0.5);
                                $pdf->Rect($ornamentX, $ornamentY, $ornamentWidth, $ornamentHeight);
                                
                                $pdf->Image($ornamentPhotoPath, $offsetX, $offsetY, $finalWidth, $finalHeight);
                            }
                        }
                    }
                } catch (Exception $e) {
                    // If image insertion fails, continue without ornament photo
                    error_log("Failed to insert ornament photo: " . $e->getMessage());
                }
            }
    
            //Purpose (using pledge items)
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(30, 166.5, $loan["pledge_items"] ?? 'N/A');
    
            //Nett Weight
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(47, 174.5, $loan["net_weight"] . 'g');
    
            //Total Weight
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(114, 174.5, $loan["total_weight"] . 'g');
    
            //Amount
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(127, 195.2, $loan["principal_amount"]);
    
            //Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(167, 195.5, $loan["customer_name"]);
    
            //Vaarisu Niyamana
            //Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(50, 230, $loan["customer_name"]);
    
            //Debt Person Name
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(75, 262, $loan["customer_name"]);
    
            //Date
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Text(75, 267, $currentDate);
        }
    }
    
    // Output the PDF
    $filename = 'loan_statement_' . $loan_no . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
    
} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
}
?>
