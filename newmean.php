<?
// Include the Gibbon class
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
$form -> loadAllValuesFrom($request);
    echo $form->getOutput();
    $selectedCourses = isset($_POST['courses']) ? $_POST['courses'] : [];
    $examType1 = isset($_POST['examType1']) ? $_POST['examType1'] : [];
    $examType2 = isset($_POST['examType2']) ? $_POST['examTtype2'] : [];
    $selectedFormGroups = isset($_POST['gibbonFormGroupID']) ? $_POST['gibbonFormGroupID'] : [];
    if (empty($selectedCourses) || empty($exam_type1) || empty($exam_type2) || empty($selectedFormGroups)) {
        // Show an error message or skip the query execution
        $page->addError("Please select at least one course and one exam type.");
    } else {;
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
$data = fetchSelectedFormData($pdo, $selectedFormGroups, $selectedCourses, $examType1, $examType2);
// Calculating mean scores
function calculateMeanScores($pdo, $selectedFormData, $examType1, $examType2) {
  $meanScores = [];

  foreach ($selectedFormData as $data) {
      $key = $data['gibbonFormGroupID'] . '-' . $data['gibbonCourseID'] . '-' . $data['examType'];
      if (!isset($meanScores[$key])) {
          $meanScores[$key] = [
              'formGroup' => $data['formGroupName'],
              'course' => $data['courseName'],
              'examType' => $data['examType'],
              'scores' => [],
          ];
      }
      $meanScores[$key]['scores'][] = $data['attainmentValue'];
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
$examType1 = $_POST['exam_type1'];
$examType2 = $_POST['exam_type2'];

// Fetch the selected form data
$selectedFormData = fetchSelectedFormData($pdo, $selectedFormGroups, $selectedCourses, $examType1, $examType2);

// Calculate mean scores
$meanScores = calculateMeanScores($pdo, $selectedFormData, $examType1, $examType2);

// Calculate deviations
$deviations = calculateDeviations($meanScores, $examType1, $examType2);

// Render HTML table
echo renderHtmlTable($meanScores, $deviations);
    }
  }