<?php
/** the full meanDeviation.php file
**/
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
    $row->addLabel('exam_type1', __('Select First Exam Type'))
        ->description(__('Select the type of exam'))
        ->required();
    $row->addSelect('exam_type1')->fromString($exam_type)->select()->required()->placeholder();
    //select second exam type
    $row = $form->addRow();
    $row->addLabel('exam_type2', __('Select Second Exam Type'))
        ->description(__('Select the type of exam'))
        ->required();
    $row->addSelect('exam_type2')->fromString($exam_type)->select()->required()->placeholder();
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
    $exam_type1 = isset($_POST['exam_type1']) ? $_POST['exam_type1'] : [];
    $exam_type2 = isset($_POST['exam_type2']) ? $_POST['exam_type2'] : [];
    $selectedFormGroups = isset($_POST['gibbonFormGroupID']) ? $_POST['gibbonFormGroupID'] : [];
    if (empty($selectedCourses) || empty($exam_type1) || empty($exam_type2) || empty($selectedFormGroups)) {
        // Show an error message or skip the query execution
        echo "Please select at least one course and one exam type.";
    } else {
$exam_type1 = $_POST['exam_type1'];
$exam_type2 = $_POST['exam_type2'];
$gibbonFormGroupID = $_POST['gibbonFormGroupID'];  // Array
$courses = $_POST['courses'];  // Array
$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
// Create the SQL query with placeholders
$sql = "SELECT
fg.name AS FormGroupName,
iac.type AS exam_type,
AVG(CASE WHEN c.name = 'ICT' THEN e.attainmentValue ELSE NULL END) AS ICT,
AVG(CASE WHEN c.name = 'English' THEN e.attainmentValue ELSE NULL END) AS English,
AVG(CASE WHEN c.name = 'Maths' THEN e.attainmentValue ELSE NULL END) AS Maths,
AVG(CASE WHEN c.name = 'Physics' THEN e.attainmentValue ELSE NULL END) AS Physics,
AVG(CASE WHEN c.name = 'Chemistry' THEN e.attainmentValue ELSE NULL END) AS Chemistry,
AVG(CASE WHEN c.name = 'Biology' THEN e.attainmentValue ELSE NULL END) AS Biology,
fg_mean.mean_score AS form_group_mean_score,
AVG(CASE WHEN iac.type = :exam_type1 THEN e.attainmentValue ELSE NULL END) - AVG(CASE WHEN iac.type = :exam_type2 THEN e.attainmentValue ELSE NULL END) AS mean_deviation
FROM
gibbonCourse AS c
INNER JOIN gibbonCourseClass AS cc ON c.gibbonCourseID = cc.gibbonCourseID
INNER JOIN gibbonInternalAssessmentColumn AS iac ON cc.gibbonCourseClassID = iac.gibbonCourseClassID
INNER JOIN gibbonInternalAssessmentEntry AS e ON iac.gibbonInternalAssessmentColumnID = e.gibbonInternalAssessmentColumnID
INNER JOIN gibbonStudentEnrolment AS se ON e.gibbonPersonIDStudent = se.gibbonPersonID
INNER JOIN gibbonFormGroup AS fg ON se.gibbonFormGroupID = fg.gibbonFormGroupID
INNER JOIN gibbonSchoolYear AS sy ON fg.gibbonSchoolYearID = sy.gibbonSchoolYearID
LEFT JOIN (
SELECT fg.gibbonFormGroupID,
  iac.type AS exam_type,
  AVG(e.attainmentValue) AS mean_score
FROM
  gibbonInternalAssessmentEntry AS e
INNER JOIN gibbonInternalAssessmentColumn AS iac ON e.gibbonInternalAssessmentColumnID = iac.gibbonInternalAssessmentColumnID
INNER JOIN gibbonStudentEnrolment AS se ON e.gibbonPersonIDStudent = se.gibbonPersonID
INNER JOIN gibbonFormGroup AS fg ON se.gibbonFormGroupID = fg.gibbonFormGroupID
WHERE
  fg.gibbonFormGroupID IN($gibbonFormGroupID) AND fg.gibbonSchoolYearID = $gibbonSchoolYearID
GROUP BY
  fg.gibbonFormGroupID,
  iac.type
) AS fg_mean ON fg.gibbonFormGroupID = fg_mean.gibbonFormGroupID AND iac.type = fg_mean.exam_type
WHERE
fg.gibbonFormGroupID IN($gibbonFormGroupID) AND fg.gibbonSchoolYearID = $gibbonSchoolYearID AND
iac.type IN(:exam_type1, :exam_type2) AND
c.gibbonCourseID IN($courses) AND
c.name IN('ICT', 'English', 'Maths', 'Physics', 'Chemistry', 'Biology')
GROUP BY
fg.gibbonFormGroupID,
iac.type,
fg_mean.mean_score
ORDER BY
fg.gibbonFormGroupID,
iac.type;
";
// Prepare the statement
$stmt = $connection2->prepare($sql);
echo "SQL Query: $sql<br>";
echo "Parameters:";
$formGroups = (implode(',', $gibbonFormGroupID));
echo "Form Groups : $formGroups <br>";
$coursesss = (implode(',', $courses));
echo "Coursesss : $coursesss <br>";
// Bind the form variables to the placeholders
$formGroups = implode(',', $gibbonFormGroupID);
$coursesString = implode(',', $courses);

$stmt->bindValue(':gibbonFormGroupID', implode(',', $formGroups));
$stmt->bindValue(':courses', implode(',', $coursesString));
$stmt->bindValue(':gibbonSchoolYearID', $gibbonSchoolYearID);
$stmt->bindValue(':exam_type1', $exam_type1);
$stmt->bindValue(':exam_type2', $exam_type2);

$coursesArray = explode(',', $coursesString);

foreach ($coursesArray as $courseID) {
  $stmt->bindValue(':courseID', $courseID, PDO::PARAM_INT);
  // Add the placeholder to the WHERE clause dynamically
  $sql .= " OR c.gibbonCourseID = :courseID";
}
// Execute the statement
$stmt->execute();
        // Fetch all rows
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); 
        echo "Rows : $rows";
        // Initialize an array to store the mean scores
        $meanScores = [];
        // Loop through the results and calculate the mean scores
        foreach ($rows as $resultRow) {
            $courseName = $resultRow['CourseName'];
            echo "Courses Name: $courseName <br>";
            $examType = $resultRow['exam_type'];
            echo "Exam Type: $examType";
            $meanScore = $resultRow['mean_score'];
            echo "Mean score: $meanscore <br>";
            // Store the mean score for this course and exam type
            $meanScores[$courseName][$examType] = $meanScore;
        }
        // Start table output
        echo 'Table:';
        // Start table output
        echo '<table>';

        // Iterate through results by year group (FormGroupName)
        foreach ($rows as $yearGroup) {
            echo "<tr><th colspan='" . count($selectedCourses) * 2 + 1 . "'>" . $yearGroup['FormGroupName'] . "</th></tr>";

            // Display course headers
            echo "<tr><th>Course Name</th>";
            foreach ($selectedExamTypes as $examType) {
                echo "<th>" . $examType . "</th>";
            }
            echo "</tr>";

            // Iterate through courses within the year group
            foreach ($selectedCourses as $courseName) {
                echo "<tr>";
                echo "<td>" . $courseName . "</td>";

                // Iterate through exam types within the course
                foreach ($selectedExamTypes as $examType) {
                    // Check if data exists for this course and exam type
                    $data = array_filter($rows, function ($row) use ($courseName, $examType) {
                        return $row['CourseName'] === $courseName && $row['exam_type'] === $examType;
                    });

                    if (!empty($data)) {
                        $meanDeviation = number_format($data[0]['mean_score'], 2);
                        echo "<td>" . $meanDeviation . "</td>";
                    } else {
                        echo "<td>-</td>"; // Display placeholder for missing data
                    }
                }
                echo "</tr>";
            }
        }

        echo '</table>';
    }
}
