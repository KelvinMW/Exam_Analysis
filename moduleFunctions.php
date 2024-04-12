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
// modify this function to dynamically generate the courses name from the selected courses  
function renderHtmlTable($meanScores, $deviations, $examType1, $examType2) {
    // Begin table HTML
    $html = "<table>
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
            $html .= "<td>$deviation</td>";
        }
        // Calculate form group mean deviation
        $meanDeviation = 'N/A';
        if (isset($formGroupMeans[$formGroup][$examType2], $formGroupMeans[$formGroup][$examType1])) {
            $meanExamType1 = array_sum($formGroupMeans[$formGroup][$examType1]) / count($formGroupMeans[$formGroup][$examType1]);
            $meanExamType2 = array_sum($formGroupMeans[$formGroup][$examType2]) / count($formGroupMeans[$formGroup][$examType2]);
            $meanDeviation = number_format($meanExamType2 - $meanExamType1, 2);
        }
        $html .= "<td>$meanDeviation</td></tr>";
    }

    $html .= "</table>";
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
