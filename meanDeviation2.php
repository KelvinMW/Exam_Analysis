<?php
//Uncaught Exception: PDOException - SQLSTATE[HY093]: Invalid parameter number: number of bound variables does not match number of tokens in /shared/httpd/sis.loc/htdocs/modules/Exam Analysis/meanDeviation.php on line 116
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;
use Gibbon\Forms\Form;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Gibbon\Http\Url;
use Gibbon\Forms\Input\Button;
use Gibbon\Forms\Input\Input;
use Gibbon\Domain\FormGroups\FormGroupGateway;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Forms\FormFactory;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\Activities\ActivityGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\Students\StudentEnrolmentGateway;
use Gibbon\Domain\Timetable\CourseGateway;
use Gibbon\Domain\Timetable\CourseClassGateway;

// Check user access on the page
if (!isActionAccessible($guid, $connection2, '/modules/Exam Analysis/meanDeviation.php')) {
    $page->addError('You do not have permission to access this page.');
    $page->addError('Please contact your system administrator if you require access to this page.');
} else {
    // Form for the query parameters
    $settingGateway = $container->get(SettingGateway::class);
    $form = Form::create('action', $session->get('absoluteURL') . '/index.php?q=/modules/Exam Analysis/meanDeviation.php');

    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $_SESSION[$guid]['address']);
    $form->addRow()->addHeading(__('Filter'));

    // Select exam type
    $examTypes = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
    $form->addRow()->addLabel('exam_type[]', __('Select Exam Type'))->description(__('Select the type of exam'))->required()->addSelect('exam_type[]')->fromString($examTypes)->selectMultiple()->required()->placeholder();

    // Select form group
    $formGroupGateway = $container->get(FormGroupGateway::class);
    $formGroups = $formGroupGateway->getByID($gibbinFormGroupID);
 //   $formGroups = FormGroupGateway::selectFormGroups($pdo, $session->get('gibbonSchoolYearID'));
    $form->addRow()->addClass('course bg-blue-100')->addLabel('gibbonFormGroupID', __('Form Group'))->addSelectFormGroup('gibbonFormGroupID', $session->get('gibbonSchoolYearID'))->required()->selectMultiple()->selected([])->placeholder();

    // Active courses for the current school year
    $courseGateway = $container->get(CourseGateway::class);
 //   $courses = $courseGateway->selectCourses($session->get('gibbonSchoolYearID'));
    $courses = $courseGateway->selectClassesByCourseID($gibbonCourseID);

//    $courses = CourseGateway::selectCourses($pdo, $session->get('gibbonSchoolYearID'));
    $form->addRow()->addClass('course bg-blue-100')->addLabel('courses[]', __('Select Courses'))->addSelect('courses[]')->fromQuery($pdo, "SELECT gibbonCourseID as value, name as name FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name", ['gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')])->selectMultiple()->setSize(6)->required()->selected([]);

    $form->addRow()->addFooter()->addSearchSubmit($gibbon->session);

    echo $form->getOutput();

    // Handle form submission
    if (!empty($_POST)) {
        $selectedCourses = $form->getValue('courses');
        $selectedExamTypes = $form->getValue('exam_type');
        $selectedFormGroups = $form->getValue('gibbonFormGroupID');

        if (empty($selectedCourses) || empty($selectedExamTypes) || empty($selectedFormGroups)) {
            echo "Please select at least one course, one exam type, and one form group.";
        } else {
            // ... (remaining code for query execution and chart generation)
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
            // Debugging: Print or log the SQL query and parameters
            echo "SQL Query: $sql<br>";
            echo "Parameters: ";
            print_r($params);
            $result->execute($params);
    
            // Fetch the results
            $results = $result->fetchAll(PDO::FETCH_ASSOC);
    
            // Prepare the data for the chart
            $examTypes = [];
            $courseNames = [];
            $meanScores = [];
            $formGroupNames = [];
    
            foreach ($results as $row) {
                if (!in_array($row['exam_type'], $examTypes)) {
                    $examTypes[] = $row['exam_type'];
                }
                if (!in_array($row['CourseName'], $courseNames)) {
                    $courseNames[] = $row['CourseName'];
                    $meanScores[] = [];
                }
                if (!in_array($row['FormGroupName'], $formGroupNames)) {
                    $formGroupNames[] = $row['FormGroupName'];
                }
                $courseIndex = array_search($row['CourseName'], $courseNames);
                $meanScores[$courseIndex][] = $row['mean_score'];
        }
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
    <canvas id="meanDeviationChart"></canvas>
    <script>
        // ... (remaining JavaScript code for chart generation)
        var ctx = document.getElementById('meanDeviationChart').getContext('2d');
        var chart;
        var data = {
            labels: <?php echo json_encode($examTypes); ?>,
            datasets: [
                <?php foreach ($courseNames as $key => $courseName) { ?>
                    {
                        label: <?php echo json_encode($courseName); ?>,
                        data: <?php echo json_encode($meanScores[$key]); ?>,
                        backgroundColor: <?php echo json_encode($colors[$key]); ?>,
                        borderColor: <?php echo json_encode($colors[$key]); ?>,
                        borderWidth: 1,
                        fill: false
                    },
                <?php } ?>
            ]
        };
        var options = {
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        suggestedMax: 100
                    }
                }]
            }
        };
        chart = new Chart(ctx, {
            type: 'line',
            data: data,
            options: options
        });
    </script>
</body>

</html>
    