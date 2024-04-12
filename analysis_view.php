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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Gibbon\Forms\Input\Button;
use Gibbon\Forms\Input\Input;
use Gibbon\Domain\FormGroups\FormGroupGateway;
use Gibbon\Domain\School\YearGroupGateway;

//echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-aFq/bzH65dt+w6FI2ooMVUpc+21e0SRygnTpmBvdBgSdnuTN7QbdgL+OapgHtvPp" crossorigin="anonymous">
//<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha2/dist/js/bootstrap.bundle.min.js" integrity="sha384-qKXV1j0HvMUeCBQ+QVp7JcfGl760yU08IQ+GpUo5hlbpg51QRiuqHAJz8+BrxE/N" crossorigin="anonymous"></script>';
//get alternative headers
$settingGateway = $container->get(SettingGateway::class);
$attainmentAlternativeName = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
$effortAlternativeName = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');

if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/analysis_view.php') == false){
    $page->addError(__('You do not have access to this action.'));
}
else

{
    echo '
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 100%;
            padding: 10px;
        }

        h1 {
            text-align: center;
        }

        .table-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }

        .table {
            width: 100%;
            margin: 10px;
        }

        th, td {
            text-align: center;
            padding: 10px;
        }

        iframe {
            width: 100%;
            height: 400px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h5>Exam Analysis</h5>
    </div>
</body>
</html>
    ';
    // Module includes
require_once __DIR__ . '/moduleFunctions.php';
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

//add courses sample
$data = array('gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
$sql = "SELECT gibbonCourseID as value, name as name FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name";
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
foreach ($courses as $courseID) {
    echo "<br>Course ID: $courseID";
}
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

// Sort courses alphabetically
sort($courses);
// Build the table rows
$student_averages = array();

$table = '';
foreach ($students as $student) {
    $table .= '<tr><td>' . $student . '</td>';
    $total_score = 0;
    $num_scores = 0;
    foreach ($courses as $course) {
        $attainment = isset($data[$student][$course]) ? $data[$student][$course] : '-';
        if ($attainment !== '' && $attainment !== null) {
            $total_score += floatval($attainment);
            $num_scores++;
        }
        $table .= '<td>' . $attainment . '</td>';
    }
    $average_score = $num_scores > 0 ? $total_score / $num_scores : '-';
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
$table .= '<button onclick="exportTableToCSV()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
<svg class="fill-current w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M13 8V2H7v6H2l8 8 8-8h-5zM0 18h20v2H0v-2z"/></svg>
<span>Export</span>
</button>';
$table .= '</td><tr>';
$formGroupGateway = $container->get(FormGroupGateway::class);
//$gateway = $container->get(FormGroupGateway::class);
$table .= '<tr><th colspan="' . (count($courses) + 4) . '">';
foreach($formGroups as $formGroup){
   // getFormGroupByID
$formGroupName=$formGroupGateway->getFormGroupByID($formGroup);
$table .= '<b>' . $formGroupName['name']. ', ' . '</b>';
}
$table .= '<b> Exam Name: ' . $exam_type. '' . '</b>';
$table .= '</th><tr>';

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
    $average_score = floatval($student_averages[$student]);
    $table .= '<td class="course bg-blue-100"><b>' . $total_score . '</b></td><td class="course bg-blue-100"><b>' . round($average_score,2) . '</b></td></tr>';
}
// Build the table footer with course totals
$table .= '<tr><td></td><td><b>Course Total<b></td>';
foreach ($courses as $course) {
    $course_attainments = array();
    foreach ($data as $student => $attainments) {
        if (isset($attainments[$course])) {
            $attainment = $attainments[$course];
            if ($attainment !== '' && $attainment !== null) {
                $course_attainments[] = $attainment;
            }
        }
    }
    $course_total = array_sum($course_attainments);
    $table .= '<td class="course bg-blue-100"><b>' . $course_total . '</b></td>';
}
$table .= '<td></td><td></td></tr>';
// Build the table footer with course averages
$table .= '<tr><td></td><td><b>Mean Score<b></td>';
$class_course_total_average=0;
$course_count = 0;
foreach ($courses as $course) {
        $course_count=$course_count+1;
    $course_attainments = array();
    foreach ($data as $student => $attainments) {
        if (isset($attainments[$course])) {
            $attainment = $attainments[$course];
            if ($attainment !== '' && $attainment !== null) {
                $course_attainments[] = $attainment;
            }
        }
    }
    $course_average = count($course_attainments) > 0 ? array_sum($course_attainments) / count($course_attainments) : '-';
    $class_course_total_average = $class_course_total_average+$course_average;
    $table .= '<td class="course bg-blue-100"><b>' . round($course_average, 2) . '</b></td>';
}
//add export
$allclass_mean = $class_course_total_average/(count($courses)*count($formGroups));
$table .= '<td  class="course bg-blue-100"><b>Form Group Mean: <b>'.round($allclass_mean,2).'</td><td><button onclick="exportTableToCSV()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
<svg class="fill-current w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M13 8V2H7v6H2l8 8 8-8h-5zM0 18h20v2H0v-2z"/></svg>
<span>Export</span>
</button></td></tr>';
$table .= '</table>';

// Output the table
echo $table;

// Add JavaScript function to export table to CSV
echo '<script>
function exportTableToCSV() {
    var csv = [];
    var rows = document.querySelectorAll("table tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (var j = 0; j < cols.length; j++) 
            row.push(cols[j].innerText);
        
        csv.push(row.join(","));
    }

    var csvContent = "data:text/csv;charset=utf-8," + csv.join("\\n");
    var encodedUri = encodeURI(csvContent);
    var link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    var filename = "ExamAnalysis";
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
}
</script>';


}
}