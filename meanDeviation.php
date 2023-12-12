<?php
//This is the code that finally works, how do I improve the graphs to make them look better?
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
                if (empty($results)) {
            echo 'No data found in the database.';
        } else {
            // Continue with processing and building datasets
        }        
// Prepare the data for the chart
$datasets = [];
$labels = [];

foreach ($results as $row) {
    $examType = $row['exam_type'];
    $courseName = $row['CourseName'];
    $meanScore = $row['mean_score'];

    // Populate labels array if not already present
    if (!in_array($examType, $labels)) {
        $labels[] = $examType;
    }

    // Populate datasets array
    if (!isset($datasets[$courseName])) {
        $datasets[$courseName] = [
            'label' => $courseName,
            'data' => [],
            'backgroundColor' => 'rgba(121,150,248,0.2)',
            'borderColor' => 'rgba(145,145,14,0.2)',
            'borderWidth' => 1,
        ];
    }

    // Add mean score to the corresponding dataset
    $datasets[$courseName]['data'][] = $meanScore;
}

// Transform datasets array into a simple indexed array
$datasetsForChart = array_values($datasets);

// Create an associative array for the chart data
$chartData = ['labels' => $labels, 'datasets' => $datasetsForChart];

// Print the data for debugging
echo 'Labels: ';
print_r($chartData['labels']);

echo 'Datasets: ';
print_r($chartData['datasets']);
        // Function to generate random colors
        function generateRandomColors($count)
        {
            $colors = [];
            for ($i = 0; $i < $count; $i++) {
                $colors[] = 'rgba(' . mt_rand(0, 255) . ',' . mt_rand(0, 255) . ',' . mt_rand(0, 255) . ',0.2)';
            }
            return $colors;
        }
        // Adjust colors dynamically based on the number of datasets
        $colors = generateRandomColors(count($datasets));
        $borderColors = generateRandomColors(count($datasets));


        echo 'Labels: ';
        print_r(isset($datasetsForChart[0]) ? array_keys($datasetsForChart[0]) : []);

        echo 'Datasets: ';
        print_r(isset($datasetsForChart) ? $datasetsForChart : []);
    }
}

function complex_array_encode($complex_array) {
    $result = [];
    foreach ($complex_array as $key => $value) {
        if (is_array($value)) {
            $result[$key] = complex_array_encode($value);
        } else {
            $result[$key] = $value;
        }
    }
    return json_encode($result);
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
// Example of customizing bar chart options
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
        },
        indexAxis: 'y', // Use 'y' for horizontal bar chart
        elements: {
            bar: {
                backgroundColor: 'rgba(121,150,248,0.8)', // Customize bar color
                borderColor: 'rgba(145,145,14,0.8)', // Customize bar border color
                borderWidth: 1 // Customize bar border width
            }
        },
        barPercentage: 0.8, // Adjust the width of bars (percentage of available space)
        categoryPercentage: 0.9, // Adjust the width of bar categories (percentage of available space)
        maxBarThickness: 50 // Maximum bar thickness
    }
});

    </script>
</body>
</html>