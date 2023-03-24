<?php
//import classes
use Gibbon\Contracts\Services\Session;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Forms\CustomFieldHandler;
use Gibbon\Module\ExamAnalysis\Forms\BindValues;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\Timetable\CourseGateway;
use Gibbon\Domain\System\AlertLevelGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Http\Url;

//import phpSpreadsheet classes
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

//get alternative headers
$settingGateway = $container->get(SettingGateway::class);
$attainmentAlternativeName = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
$effortAlternativeName = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');

if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/analysis_view.php') == false){
    $page->addError(__('You do not have access to this action.'));
}
else

{
  // School Year Info
  $settingGateway = $container->get(SettingGateway::class);
  $attainmentAlternativeName = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
  $effortAlternativeName = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');

 $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
 $gibbonCourseID = $_GET['gibbonCourseID'] ?? null;

$form = Form::create('action',$session->get('absoluteURL').'/index.php?q=/modules/Exam Analysis/analysis_view.php');
$form->setFactory(DatabaseFormFactory::create($pdo));
$form->addHiddenValue('q', $session->get('address'));

//type of exam
$types = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
    $row = $form->addRow();
    $row->addLabel('examType', __('Select Exam Type'))
        ->description(__('Select the type of exam'))
        ->required();
    $row->addSelect('type')->fromString($types)->required()->placeholder();

//the Form group or class
    $row = $form->addRow()->addClass('course bg-blue-100');
    $row->addLabel('gibbonFormGroupID', __('Form Group'));
    $gibbonFormGroupID=array();
    $row->addSelectFormGroup('gibbonFormGroupID', $session->get('gibbonSchoolYearID'))->required()->selectMultiple()->selected($gibbonFormGroupID)->placeholder();

//add courses
$data = array('gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
$sql = "SELECT gibbonCourseID as value, nameShort as name FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name";
$row = $form->addRow()->addClass('course bg-blue-100');
$row->addLabel('courses[]', __('Select Courses'));
$selected=array();
$row->addSelect('courses[]')->fromQuery($pdo, $sql, $data)
    ->selectMultiple()
    ->setSize(6)
    ->required()
    ->selected($selected);
//go
$row = $form->addRow();
//$row = $form->addRow();
$row->addFooter();
$row->addSubmit();

$form->loadAllValuesFrom($request);
echo $form->getOutput();

//run on submit
if (!empty($_POST['courses'])) {
$courses = $_POST['courses']?? array();
$exam_type = $_POST['type']?? null;
$formGroups = $_POST['gibbonFormGroupID']?? null;
// Construct SQL query
$sql = "SELECT p.officialName AS StudentName, c.name AS CourseName, gibbonInternalAssessmentEntry.attainmentValue AS Attainment
        FROM gibbonInternalAssessmentEntry  
        INNER JOIN gibbonPerson AS p ON p.gibbonPersonID = gibbonInternalAssessmentEntry.gibbonPersonIDStudent 
        INNER JOIN gibbonStudentEnrolment AS e ON gibbonInternalAssessmentEntry.gibbonPersonIDStudent=e.gibbonPersonID 
        INNER JOIN gibbonFormGroup AS y ON e.gibbonFormGroupID=y.gibbonFormGroupID
        INNER JOIN gibbonInternalAssessmentColumn ON gibbonInternalAssessmentColumn.gibbonInternalAssessmentColumnID= gibbonInternalAssessmentEntry.gibbonInternalAssessmentColumnID 
        INNER JOIN gibbonCourseClass ON gibbonCourseClass.gibbonCourseClassID = gibbonInternalAssessmentColumn.gibbonCourseClassID
        INNER JOIN gibbonCourse AS c ON c.gibbonCourseID = gibbonCourseClass.gibbonCourseID
        INNER JOIN gibbonSchoolYear AS s ON y.gibbonSchoolYearID=s.gibbonSchoolYearID 
        WHERE c.gibbonCourseID IN (" . str_repeat('?,', count($courses) - 1) . "?) 
        AND y.gibbonFormGroupID IN (" . str_repeat('?,', count($formGroups) - 1) . "?) 
        AND s.gibbonSchoolYearID = ?
        AND gibbonInternalAssessmentColumn.type = ?
        ORDER BY p.officialName, c.name";

// Prepare and execute SQL query with parameters
$stmt = $connection2->prepare($sql);
$params = array_merge($courses, $formGroups, array($session->get('gibbonSchoolYearID'), $exam_type));
$stmt->execute($params);

// Build the output table
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
$students = array();
$courses = array();
foreach ($results as $row) {
    $student = $row['StudentName'];
    $course = $row['CourseName'];
    $attainment = $row['Attainment'];
    
    // Build array of unique students and courses
    if (!in_array($student, $students)) {
        $students[] = $student;
    }
    if (!in_array($course, $courses)) {
        $courses[] = $course;
    }
    
    // Store attainment value for each student and course
    $data[$student][$course] = $attainment;
}

// Sort students and courses alphabetically
//sort($students);
sort($courses);

// Build the table rows
$student_averages = array();

$table = '';
foreach ($students as $student) {
    $table .= '<tr><td>' . $student . '</td>';
    $total_score = 0;
    foreach ($courses as $course) {
        $attainment = isset($data[$student][$course]) ? $data[$student][$course] : '-';
        $total_score += floatval($attainment);
        $table .= '<td>' . $attainment . '</td>';
    }
    $average_score = count($courses) > 0 ? $total_score / count($courses) : '-';
    $table .= '<td>' . $total_score . '</td><td>' . $average_score . '</td></tr>';
    $student_averages[$student] = $average_score;
}

// Sort students by average score
arsort($student_averages);

// Reorder students array based on sorted keys
$students = array_keys($student_averages);
// Build the table headers assuming all query data is okay
$table = '<table>';
// Build the export button
$table .= '<tr><td colspan="' . (count($courses) + 2) . '">';
$table .= '<form method="post"><input type="submit" name="export" value="Export to CSV"></form>';
$table .= '</td><tr>';
$table .= '<tr><th>Rank</th>';
$table .= '<th>Student Name</th>';
foreach ($courses as $course) {
    $table .= '<th>' . $course . '</th>';
}
$table .= '<th>Total Score</th><th>Mean Score</th></tr>';

$rank=0;
// Build the table rows based on sorted students
foreach ($students as $student) {
    $rank = $rank + 1;
    $table .= '<tr><td class="course bg-blue-100">' . $rank . '</td>';
    $table .= '<td><b>' . $student . '</b></td>';
    $total_score = 0;
    foreach ($courses as $course) {
        // ...
        $attainment = isset($data[$student][$course]) ? $data[$student][$course] : '-';
        $total_score += floatval($attainment);
        $table .= '<td>' . $attainment . '</td>';
    }
    $average_score = $student_averages[$student];
    $table .= '<td class="course bg-blue-100"><b>' . $total_score . '</b></td><td class="course bg-blue-100"><b>' . round($average_score,2) . '</b></td></tr>';
}
// Build the table footer with course averages
$table .= '<tr><td></td><td><b>Mean Score<b></td>';
foreach ($courses as $course) {
    $course_attainments = array();
    foreach ($data as $student => $attainments) {
        if (isset($attainments[$course])) {
            $course_attainments[] = $attainments[$course];
        }
    }
    $course_average = count($course_attainments) > 0 ? array_sum($course_attainments) / count($course_attainments) : '-';
    $table .= '<td class="course bg-blue-100"><b>' . round($course_average, 2) . '</b></td>';
}
$table .= '<td></td><td></td></tr>';
$table .= '</table>';

// Output the table
echo $table;

// Check if export button was clicked
if (isset($_POST['export'])) {
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=data.csv');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write headers to CSV file
    fputcsv($output, array_merge(['Rank', 'Student Name'], array_values($courses), ['Total Score', 'Mean Score']));

    // Write rows to CSV file
    foreach ($students as$student) {
        // ...
        fputcsv($output, array_merge([$rank, $student], array_values($row), [$total_score,$average_score]));
        ++$rank;
     }

     fclose($output);
     exit();
}

}
}
?>