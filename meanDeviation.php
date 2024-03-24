<?
//The database has correct data
//After Form submit there is no data or table being deisplayed
// This function does not diplay anything echo renderHtmlTable($meanScores, $deviations);
// Since no table is being displayed, Add debug to $_POST, mean execution, meanDeviation
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\Form;
require_once __DIR__ . '/moduleFunctions.php';
if(isActionAccessible($guid, $connection2, '/modules/Exam Analysis/meanDeviation.php')==false){
    $page->addError('You do not have permission to access this page.');
    $page->addError('Please contact your system administrator if you require access to this page.');
} else {
    $settingGateway = $container->get(SettingGateway::class);
    //This is the PHP code of the form
    $form = Form::create('action', $session->get('absoluteURL') . '/index.php?q=/modules/Exam Analysis/meanDeviation.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('q', '/modules/Exam Analysis/meanDeviation.php');
    $form->addHiddenValue('gibbonSchoolYearID', $session->get('gibbonSchoolYearID'));
    //select exam type
    $exam_type = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
    //select first exam type
    $row = $form->addRow();
    $row->addLabel('examType1', __('Select First Exam Type'))
        ->description(__('Select the type of exam'))
        ->required();
    $row->addSelect('examType1')->fromString($exam_type)->select()->required()->placeholder();
    //select second exam type
    $row = $form->addRow();
    $row->addLabel('examType2', __('Select Second Exam Type'))
        ->description(__('Select the type of exam'))
        ->required();
    $row->addSelect('examType2')->fromString($exam_type)->select()->required()->placeholder();
    //select form group
    $row = $form->addRow()->addClass('course bg-blue-100');
    $row->addLabel('gibbonFormGroupID', __('Form Group'));
    $gibbonFormGroupID = array();
    $row->addSelectFormGroup('gibbonFormGroupID', $session->get('gibbonSchoolYearID'))->required()->selectMultiple()->selected($gibbonFormGroupID)->placeholder();

    //active courses for the current school year
    $data = array('gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
    //sql for courses
    $sql = "SELECT gibbonCourseID as value, name as name FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name";
    $row = $form->addRow()->addClass('course bg-blue-100');
    $row->addLabel('courses[]', __('Select Courses'));
    $selected = array();
    $row->addSelect('courses[]')->fromQuery($pdo, $sql, $data)
        ->selectMultiple()
        ->setSize(6)
        ->required()
        ->selected($selected);
    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

//debug
error_log("POST Data: " . print_r($_POST, true));
$form -> loadAllValuesFrom($request);
    echo $form->getOutput();
    if (!empty($selectedFormGroups) && !empty($selectedCourses) && !empty($examType1) && !empty($examType2)) {
        // After fetching selected form data, to ensure data is being fetched correctly
   $data = fetchSelectedFormData($pdo, $selectedFormGroups, $selectedCourses, $examType1, $examType2);
error_log("Selected Form Data: " . print_r($selectedFormData, true));

        $meanScores = calculateMeanScores($pdo, $data, $examType1, $examType2);
        // After calculating mean scores, to see if the calculations appear correct
$meanScores = calculateMeanScores($pdo, $data, $examType1, $examType2);
error_log("Mean Scores: " . print_r($meanScores, true));
        $deviations = calculateDeviations($meanScores, $examType1, $examType2);
        // After calculating deviations, to verify the deviations are computed as expected
$deviations = calculateDeviations($meanScores, $examType1, $examType2);
error_log("Deviations: " . print_r($deviations, true));
        echo renderHtmlTable($meanScores, $deviations);
    } else {
        $page->addError("Please select at least one course and one exam type.");
        return; // Stop further execution if the required fields are not filled.
    } 
function fetchSelectedFormData($pdo, $selectedFormGroups, $selectedCourses, $examType1, $examType2) {
    $placeholders = str_repeat('?,', count($selectedFormGroups) - 1) . '?';
    $placeholdersCourses = str_repeat('?,', count($selectedCourses) - 1) . '?';

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

    $parameters = array_merge($selectedFormGroups, $selectedCourses, [$examType1, $examType2]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);

    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }

    return $data;
}
// Calculating mean scores
function calculateMeanScores($pdo, $data, $examType1, $examType2) {
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

  return $meanScores;
}
// Calclulate Mean Deviation
function calculateDeviations($meanScores, $examType1, $examType2) {
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

  return $deviations;
}
// Display results
function renderHtmlTable($meanScores, $deviations) {
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
// Placeholder for form submission data
$selectedFormGroups = $_POST['gibbonFormGroupID'];
$selectedCourses = $_POST['courses'];
$examType1 = $_POST['examType1'];
$examType2 = $_POST['examType2'];
// Fetch the selected form data
$data = fetchSelectedFormData($pdo, $selectedFormGroups, $selectedCourses, $examType1, $examType2);
error_log("Selected Form Data: " . print_r($data, true));

// Calculate mean scores
$meanScores = calculateMeanScores($pdo, $data, $examType1, $examType2);

// After calculating mean scores, to see if the calculations appear correct
$meanScores = calculateMeanScores($pdo, $data, $examType1, $examType2);
error_log("Mean Scores: " . print_r($meanScores, true));
// Calculate deviations
$deviations = calculateDeviations($meanScores, $examType1, $examType2);
// After calculating deviations, to verify the deviations are computed as expected
$deviations = calculateDeviations($meanScores, $examType1, $examType2);
error_log("Deviations: " . print_r($deviations, true));
// Render HTML table
echo renderHtmlTable($meanScores, $deviations);
    }
  