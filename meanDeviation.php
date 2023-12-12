<?php
// Include the Gibbon class
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
use Gibbon\Forms\FormFactory;

//check user access on the page
if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/meanDeviation.php') == false) {
    $page->addError('You do not have permission to access this page.');
    $page->addError('Please contact your system administrator if you require access to this page.');
} else {
//Function to prepare data for the chart
    function prepareDataForChart($fetchedData) {
        $labels = [];
        $datasets = [];
    
        foreach ($fetchedData as $data) {
            if (!in_array($data['CourseName'], $labels)) {
                $labels[] = $data['CourseName'];
            }
    
            $datasetIndex = array_search($data['exam_type'], array_column($datasets, 'label'));
            if ($datasetIndex === false) {
                $color = 'rgba('.rand(0,255).','.rand(0,255).','.rand(0,255).',0.2)';
                $datasets[] = [
                    'label' => $data['exam_type'],
                    'data' => [$data['mean_score']],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'borderWidth' => 1
                ];
            } else {
                $datasets[$datasetIndex]['data'][] = $data['mean_score'];
            }
        }
    
        return ['labels' => $labels, 'datasets' => $datasets];
    }
    //form for the query parameters
    $settingGateway = $container->get(SettingGateway::class);
    $form = Form::create('action', $session->get('absoluteURL') . '/index.php?q=/modules/Exam Analysis/meanDeviation.php');
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
    $row->addSearchSubmit($gibbon->session);

    echo $form->getOutput();

    $selectedCourses = isset($_POST['courses']) ? $_POST['courses'] : [];
    $selectedExamTypes = isset($_POST['exam_type']) ? $_POST['exam_type'] : [];
    $selectedFormGroups = isset($_POST['gibbonFormGroupID']) ? $_POST['gibbonFormGroupID'] : [];
    $datasetsForChart = [];
    if (empty($selectedCourses) || empty($selectedExamTypes) || empty($selectedFormGroups)) {
        // Show an error message or skip the query execution
        echo "Please select at least one course and one exam type.";
    } else {
        $courseParams = array_map(function ($key) {
            return ":course$key";
        }, array_keys($selectedCourses));
        $examTypeParams = array_map(function ($key) {
            return ":examType$key";
        }, array_keys($selectedExamTypes));
        $formGroupParams = array_map(function ($key) {
            return ":formGroup$key";
        }, array_keys($selectedFormGroups));
        
        $coursePlaceholders = implode(',', $courseParams);
        $examTypePlaceholders = implode(',', $examTypeParams);
        $formGroupPlaceholders = implode(',', $formGroupParams);
        
        $sql = "SELECT c.name AS CourseName, iac.type AS exam_type, fg.name as FormGroupName, AVG(e.attainmentValue) AS mean_score 
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
        AND fg.gibbonFormGroupID IN ($formGroupPlaceholders)
        GROUP BY c.name, iac.type";
               
        // Prepare sql statement
        $params = array(':gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
        foreach ($selectedCourses as $key => $value) {
            $params[$courseParams[$key]] = $value;
        }
        foreach ($selectedExamTypes as $key => $value) {
            $params[$examTypeParams[$key]] = $value;
        }
        foreach ($selectedFormGroups as $key => $value) {
            $params[$formGroupParams[$key]] = $value;
        }
        
        $result = $connection2->prepare($sql);
        $result->execute($params);
        
        // Fetch the results
        $results = $result->fetchAll(PDO::FETCH_ASSOC);
        $chartData = prepareDataForChart($results);
                if (empty($results)) {
            echo 'No data found in the database.';
        } else {
            // Continue with processing and building datasets
        }        

    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mean Deviation Analysis</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <canvas id="myChart"></canvas>
    <script>
    var ctx = document.getElementById('myChart').getContext('2d');
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartData['labels']); ?>,
            datasets: <?php echo json_encode($chartData['datasets']); ?>
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>
</body>
</html>