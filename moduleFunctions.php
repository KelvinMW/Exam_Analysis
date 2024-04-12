<?php
    use Gibbon\Domain\FormGroups\FormGroupGateway;
    function collectPostData() {
        // Collect and return form submission data
        $selectedFormGroups = $_POST['gibbonFormGroupID'] ?? [];
        $selectedCourses = $_POST['courses'] ?? [];
        $examType1 = $_POST['examType1'] ?? '';
        $examType2 = $_POST['examType2'] ?? '';
        error_log("Collected POST Data: " . print_r([$selectedFormGroups, $selectedCourses, $examType1, $examType2], true));
        return [$selectedFormGroups, $selectedCourses, $examType1, $examType2];
    }

    function fetchData($connection2, $selectedFormGroups, $selectedCourses, $examType1, $examType2) {
        // Use global $connection2 for database operations
        $placeholders = str_repeat('?,', count($selectedFormGroups) - 1) . '?';
        $placeholdersCourses = str_repeat('?,', count($selectedCourses) - 1) . '?';
        $parameters = array_merge($selectedFormGroups, $selectedCourses, [$examType1, $examType2]);
        
    // Debugging SQL query parameters
    error_log("SQL Query Parameters: " . print_r([$selectedFormGroups, $selectedCourses, $examType1, $examType2], true));
    $sql = "SELECT gfm.gibbonFormGroupID, gfm.name AS formGroupName, gc.gibbonCourseID, gc.name AS courseName, giac.gibbonInternalAssessmentColumnID, giac.type AS examType, giae.attainmentValue
            FROM gibbonFormGroup gfm
            JOIN gibbonCourseClassMap gccm ON gfm.gibbonFormGroupID = gccm.gibbonFormGroupID
            JOIN gibbonCourseClass gcc ON gccm.gibbonCourseClassID = gcc.gibbonCourseClassID
            JOIN gibbonCourse gc ON gcc.gibbonCourseID = gc.gibbonCourseID
            JOIN gibbonInternalAssessmentColumn giac ON gcc.gibbonCourseClassID = giac.gibbonCourseClassID
            JOIN gibbonInternalAssessmentEntry giae ON giac.gibbonInternalAssessmentColumnID = giae.gibbonInternalAssessmentColumnID
            WHERE gfm.gibbonFormGroupID IN ($placeholders)
            AND gc.gibbonCourseID IN ($placeholdersCourses)
            AND giac.type IN (?, ?)
            ORDER BY gfm.name, gc.name, giac.type";
        $stmt = $connection2->prepare($sql);
        $stmt->execute($parameters);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    }
//Calculate Mean score
    function calculateMeanScores($data) {
        // Calculate mean scores
        $meanScores = [];

    foreach ($data as $row) {
        $key = $row['gibbonFormGroupID'] . '-' . $row['gibbonCourseID'] . '-' . $row['examType'];
        if (!isset($meanScores[$key])) {
            $meanScores[$key] = [
                'formGroup' => $row['formGroupName'],
                'course' => $row['courseName'],
                'examType' => $row['examType'],
                'scores' => [],
            ];
        }
        $meanScores[$key]['scores'][] = $row['attainmentValue'];
    }

    foreach ($meanScores as $key => &$details) {
        $details['meanScore'] = !empty($details['scores']) ? array_sum($details['scores']) / count($details['scores']) : 0;
        unset($details['scores']); // Cleanup scores array as it's no longer needed.
    }
    // Debugging mean scores
    error_log("Mean Scores: " . print_r($meanScores, true));
    return $meanScores;
    }
//Calculate Deviations
function calculateDeviations($meanScores, $examType1, $examType2) {
    $deviations = [];

    foreach ($meanScores as $key => $details) {
        $otherExamType = ($details['examType'] === $examType1) ? $examType2 : $examType1;
        $pairKey = str_replace($details['examType'], $otherExamType, $key);

        if (isset($meanScores[$pairKey])) {
            // Reverse the order of subtraction here
            $deviation = $details['meanScore'] - $meanScores[$pairKey]['meanScore'];
        } else {
            $deviation = 0; // Or any other value indicating missing data
        }

        $deviationKey = $details['formGroup'] . '-' . $details['course'];
        $deviations[$deviationKey] = [
            'formGroup' => $details['formGroup'],
            'course' => $details['course'],
            'deviation' => $deviation,
        ];
    }
    error_log('Calculated Deviations: ' . print_r($deviations, true));
    return $deviations;
}    
    function getCourseNameById($connection2, $gibbonCourseID) {
        $sql = "SELECT name FROM gibbonCourse WHERE gibbonCourseID=:gibbonCourseID";
        $stmt = $connection2->prepare($sql);
        $stmt->execute(['gibbonCourseID' => $gibbonCourseID]);
        return $stmt->fetchColumn();
    }
    function arrayToCsvDownload($array, $filename = "export.csv", $delimiter=";") {
        // Open raw memory as file so no temp files needed, you might run out of memory though
        $f = fopen('php://memory', 'w'); 
        // loop over the input array
        foreach ($array as $line) { 
            // generate csv lines from the inner arrays
            fputcsv($f, $line, $delimiter); 
        }
        // reset the file pointer to the start of the file
        fseek($f, 0);
        // tell the browser it's going to be a csv file
        header('Content-Type: application/csv');
        // tell the browser we want to save it instead of displaying it
        header('Content-Disposition: attachment; filename="'.$filename.'";');
        // make php send the generated csv lines to the browser
        fpassthru($f);
    }    
// Render HTML table  
function renderHtmlTable($meanScores, $deviations, $examType1, $examType2) {
    $html = '<style>
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    box-shadow: 0 2px 3px rgba(0,0,0,0.1);
                    font-family: Arial, sans-serif;
                    margin-top: 20px;
                }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 10px; 
                    text-align: left; 
                    font-size: 14px;
                }
                th {
                    background-color: #4CAF50;
                    color: black;
                    font-size: 16px;
                }
                tr:nth-child(even) {
                    background-color: #f2f2f2;
                }
                tr:hover {
                    background-color: #ddd;
                }
                .positive-deviation { 
                    color: #28a745; 
                    font-weight: bold;
                }
                .negative-deviation { 
                    color: #dc3545; 
                    font-weight: bold;
                }
                button {
                    background-color: #4CAF50;
                    color: white;
                    padding: 10px 15px;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                }
                button:hover {
                    background-color: #45a049;
                }
            </style>';
    // Begin table HTML
    $html .= "<table>
                <tr>
                    <th>Form Group</th>
                    <th>Exam Type</th>";

    // Generate table headers for courses
    $courseNames = array_unique(array_column($meanScores, 'course'));
    foreach ($courseNames as $courseName) {
        $html .= "<th>$courseName</th>";
    }
    $html .= "<th>FORM GROUP MEAN</th></tr>";

    // Organize scores and calculate form group means
    $organizedScores = [];
    $formGroupMeans = [];
    foreach ($meanScores as $score) {
        $organizedScores[$score['formGroup']][$score['examType']][$score['course']] = $score['meanScore'];
        $formGroupMeans[$score['formGroup']][$score['examType']][] = $score['meanScore'];
    }

    // Generate rows for scores
    foreach ($organizedScores as $formGroup => $examTypes) {
        foreach ($examTypes as $examType => $courses) {
            $formGroupTotal = array_sum($courses);
            $courseCount = count($courses);
            $formGroupMean = $courseCount > 0 ? $formGroupTotal / $courseCount : 0;

            $html .= "<tr><td>$formGroup</td><td>$examType</td>";
            foreach ($courseNames as $courseName) {
                $html .= "<td>" . (isset($courses[$courseName]) ? number_format($courses[$courseName], 2) : 'N/A') . "</td>";
            }
            $html .= "<td>" . number_format($formGroupMean, 2) . "</td></tr>";
        }

        // Calculate and add deviation rows
        $html .= "<tr><td>$formGroup</td><td>MEAN DEVIATION</td>";
        foreach ($courseNames as $courseName) {
            $devKey = "$formGroup-$courseName";
            $deviation = isset($deviations[$devKey]) ? number_format($deviations[$devKey]['deviation'], 2) : 'N/A';
            $formattedDeviation = ($deviation === 'N/A') ? 'N/A' : number_format((float)$deviation, 2);
            // Apply styling based on deviation value
            $deviationStyle = '';
            if ($deviation !== 'N/A' && $deviation < 0) {
                $deviationStyle = 'negative-deviation';
            } elseif ($deviation !== 'N/A' && $deviation > 0) {
                $deviationStyle = 'positive-deviation';
            }
        $html .= "<td class='$deviationStyle'>$formattedDeviation</td>";
        }
        // Calculate form group mean deviation
        $meanDeviation = 'N/A';
        if (isset($formGroupMeans[$formGroup][$examType2], $formGroupMeans[$formGroup][$examType1])) {
            $meanExamType1 = array_sum($formGroupMeans[$formGroup][$examType1]) / count($formGroupMeans[$formGroup][$examType1]);
            $meanExamType2 = array_sum($formGroupMeans[$formGroup][$examType2]) / count($formGroupMeans[$formGroup][$examType2]);
            $meanDeviation = number_format($meanExamType2 - $meanExamType1, 2);
            if ($meanDeviation !== 'N/A' && $meanDeviation < 0) {
                $meanDeviationStyle = 'negative-deviation';
            } elseif ($meanDeviation !== 'N/A' && $meanDeviation > 0) {
                $meanDeviationStyle = 'positive-deviation';
            }
        }
        $html .= "<td class='$meanDeviationStyle'>$meanDeviation</td></tr>";
    }

// Add a button with an onclick event handler to call your export function
$html .= '<button onclick="exportTableToCSV(\'export.csv\')">Export to Excel</button>';
    
$html .= '</table>';
// Add JavaScript function for export
$html .= '<script>
    function exportTableToCSV(filename) {
        var csv = [];
        var rows = document.querySelectorAll("table tr");
        
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll("td, th");
            
            for (var j = 0; j < cols.length; j++) 
                row.push(cols[j].innerText);
            
            csv.push(row.join(","));
        }

        // Download CSV file
        downloadCSV(csv.join("\n"), filename);
    }
    
    function downloadCSV(csv, filename) {
        var csvFile;
        var downloadLink;
    
        // CSV file
        csvFile = new Blob([csv], {type: "text/csv"});
    
        // Download link
        downloadLink = document.createElement("a");
    
        // File name
        downloadLink.download = filename;
    
        // Create a link to the file
        downloadLink.href = window.URL.createObjectURL(csvFile);
    
        // Hide download link
        downloadLink.style.display = "none";
    
        // Add the link to DOM
        document.body.appendChild(downloadLink);
    
        // Click download link
        downloadLink.click();
    }
</script>';

return $html;
}
     
    // Helper function to get form group name by ID
    function getFormGroupNameById($formGroupId) {
    // Assume we retrieve the form group name based on the ID
    // This function needs to be implemented according to how you access your data
    return "Form Group Name for ID {$formGroupId}"; // Placeholder, replace with actual implementation
    }
    function getCourseNames($connection2)
    {
        $result = $connection2->prepare("SELECT name FROM gibbonCourse");
        $result->execute();
        return array_map(function ($row) {
            return $row['name'];
        }, $result->fetchAll());
    }
    function setupForm($pdo, $session) {
        // Setup form
        global $settingGateway; // Assuming $settingGateway is initialized globally
        $form = \Gibbon\Forms\Form::create('action', $session->get('absoluteURL') . '/index.php?q=/modules/Exam Analysis/meanDeviation.php');
        $form->setFactory(\Gibbon\Forms\DatabaseFormFactory::create($pdo));
        $form->addHiddenValue('q', '/modules/Exam Analysis/meanDeviation.php');
        $form->addHiddenValue('gibbonSchoolYearID', $session->get('gibbonSchoolYearID'));
    
        // Fetch exam types
        $exam_type = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
        
        // Exam Type 1
        $row = $form->addRow();
        $row->addLabel('examType1', __('Select First Exam Type'))
            ->description(__('Select the type of exam'))
            ->required();
        $row->addSelect('examType1')->fromString($exam_type)->select()->required()->placeholder('');
    
        // Exam Type 2
        $row = $form->addRow();
        $row->addLabel('examType2', __('Select Second Exam Type'))
            ->description(__('Select the type of exam'))
            ->required();
        $row->addSelect('examType2')->fromString($exam_type)->select()->required()->placeholder('');
    
        // Form Group
        $row = $form->addRow()->addClass('course bg-blue-100');
        $row->addLabel('gibbonFormGroupID', __('Form Group'))
            ->required();
        $row->addSelectFormGroup('gibbonFormGroupID', $session->get('gibbonSchoolYearID'))->selectMultiple()->placeholder('');
    
        // Courses
        $sql = "SELECT gibbonCourseID as value, name as name FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name";
        $row = $form->addRow()->addClass('course bg-blue-100');
        $row->addLabel('courses[]', __('Select Courses'))
            ->required();
        $row->addSelect('courses[]')->fromQuery($pdo, $sql, ['gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')])
            ->selectMultiple()
            ->setSize(6)
            ->placeholder('');
    
        $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();
    
        return $form;
    }
