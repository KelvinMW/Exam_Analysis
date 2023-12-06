<?php
// FILEPATH: /c:/Users/Kelvin/Documents/Projects/devilbox/data/www/sis.loc/htdocs/modules/Exam Analysis/meanDeviation.php
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

//check user access on the page
if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/meanDeviation.php') == false) {
    $page->addError('You do not have permission to access this page.');
    $page->addError('Please contact your system administrator if you require access to this page.');
} else
{
//form for the query parameters
$settingGateway = $container->get(SettingGateway::class);
$form = Form::create('filterForm', '');
$form->setFactory(DatabaseFormFactory::create($pdo));

$form->addHiddenValue('address', $_SESSION[$guid]['address']);

$form->addRow()->addHeading(__('Filter'));
//select exam type
$exam_type = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
$row = $form->addRow();
$row->addLabel('exam_type[]', __('Select Exam Type'))
    ->description(__('Select the type of exam'))
    ->required();
$row->addSelect('exam_type[]')->fromString($exam_type)->selectMultiple()->required()->placeholder();
//active courses for the current school year
$data = array('gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
//sql for courses
$sql = "SELECT gibbonCourseID as value, name as name FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name";
$row = $form->addRow()->addClass('course bg-blue-100');
$row->addLabel('courses[]', __('Select Courses'));
$selected=array();
$row->addSelect('courses[]')->fromQuery($pdo, $sql, $data)
    ->selectMultiple()
    ->setSize(6)
    ->required()
    ->selected($selected);
$row = $form->addRow();
$row->addFooter();
$row->addSearchSubmit($gibbon->session);

echo $form->getOutput();

$selectedCourses = $_POST['courses'];
$selectedExamTypes = $_POST['exam_type'];

if (empty($selectedCourses) || empty($selectedExamTypes)) {
    // Show an error message or skip the query execution
    echo "Please select at least one course and one exam type.";
} else {
    $courseParams = array_map(function($key) { return ":course$key"; }, array_keys($selectedCourses));
    $examTypeParams = array_map(function($key) { return ":examType$key"; }, array_keys($selectedExamTypes));

    $coursePlaceholders = implode(',', $courseParams);
    $examTypePlaceholders = implode(',', $examTypeParams);

    $sql = "SELECT c.name AS CourseName, iac.type AS exam_type, AVG(e.attainmentValue) AS mean_score
    FROM gibbonCourse AS c
    INNER JOIN gibbonCourseClass AS cc ON c.gibbonCourseID = cc.gibbonCourseID
    INNER JOIN gibbonInternalAssessmentColumn AS iac ON cc.gibbonCourseClassID = iac.gibbonCourseClassID
    INNER JOIN gibbonInternalAssessmentEntry AS e ON iac.gibbonInternalAssessmentColumnID = e.gibbonInternalAssessmentColumnID
    INNER JOIN gibbonInternalAssessmentEntry AS e2 ON e.gibbonPersonIDStudent = e2.gibbonPersonIDStudent
    INNER JOIN gibbonStudentEnrolment AS se ON e.gibbonPersonIDStudent = se.gibbonPersonID
    INNER JOIN gibbonFormGroup AS fg ON se.gibbonFormGroupID = fg.gibbonFormGroupID
    INNER JOIN gibbonSchoolYear AS sy ON fg.gibbonSchoolYearID = sy.gibbonSchoolYearID
    WHERE sy.gibbonSchoolYearID = :gibbonSchoolYearID
    AND iac.type IN ($examTypePlaceholders)
    AND c.gibbonCourseID IN ($coursePlaceholders)
    GROUP BY c.name, iac.type";

$params = array(':gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
foreach ($selectedCourses as $key => $value) {
$params[$courseParams[$key]] = $value;
}
foreach ($selectedExamTypes as $key => $value) {
$params[$examTypeParams[$key]] = $value;
}

$result = $connection2->prepare($sql);
$result->execute($params);

// Fetch the results
$results = $result->fetchAll(PDO::FETCH_ASSOC);

// Prepare the data for the chart
$examTypes = [];
$courseNames = [];
$meanScores = [];
foreach ($results as $row) {
if (!in_array($row['exam_type'], $examTypes)) {
    $examTypes[] = $row['exam_type'];
}
if (!in_array($row['CourseName'], $courseNames)) {
    $courseNames[] = $row['CourseName'];
    $meanScores[] = [];
}
$courseIndex = array_search($row['CourseName'], $courseNames);
$meanScores[$courseIndex][] = $row['mean_score'];
}
}
// Generate the graph using a charting library like Chart.js

?>

<!DOCTYPE html>
<html>
<head>
    <title>Mean Deviation Analysis</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <canvas id="meanDeviationChart"></canvas>

    <script>
    var ctx = document.getElementById('meanDeviationChart').getContext('2d');
    var chart;
    <?php if (!empty($meanScores)): ?>
        chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($examTypes); ?>,
                datasets: [
                    <?php foreach ($courseNames as $index => $courseName): ?>
                        {
                            label: '<?php echo $courseName; ?>',
                            data: [<?php echo implode(',', $meanScores[$index]); ?>],
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                    <?php endforeach; ?>
                ]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    <?php else: ?>
        ctx.innerHTML = "No data available for the selected parameters.";
    <?php endif; ?>
</script>
</body>
</html>