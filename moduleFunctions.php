
<?php
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

function calculateDeviations($meanScores, $examType1, $examType2) {
    // Calculate deviations
    $deviations = [];

    foreach ($meanScores as $key => $details) {
        if ($details['examType'] == $examType1) {
            $pairKey = str_replace($examType1, $examType2, $key);
            if (isset($meanScores[$pairKey])) {
                $deviationKey = $details['formGroup'] . '-' . $details['course'];
                $deviations[$deviationKey] = [
                    'formGroup' => $details['formGroup'],
                    'course' => $details['course'],
                    'deviation' => $meanScores[$pairKey]['meanScore'] - $details['meanScore'],
                ];
            }
        }
    }
  // Debugging deviations
  error_log("Deviations: " . print_r($deviations, true));
    return $deviations; 
}

function renderHtmlTable($meanScores, $deviations) {
    // Render HTML table
    $html = "<table border='1'><thead><tr><th>Form Group</th><th>Exam Type</th><th>Course</th><th>Mean Score</th><th>Deviation</th></tr></thead><tbody>";

    foreach ($deviations as $deviation) {
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($deviation['formGroup']) . "</td>";
        $html .= "<td>" . htmlspecialchars($deviation['course']) . "</td>";
        $html .= "<td>" . number_format($deviation['deviation'], 2) . "</td>";
        $html .= "</tr>";
    }
  
    $html .= "</tbody></table>";
  
    return $html;
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