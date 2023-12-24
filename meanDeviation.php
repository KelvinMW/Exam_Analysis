<?php
//This is the meanDeviation.php code, 
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
        $selectedExamTypes = isset($_POST['exam_type']) ? $_POST['exam_type'] : [];
        $selectedFormGroups = isset($_POST['gibbonFormGroupID']) ? $_POST['gibbonFormGroupID'] : [];
        if (empty($selectedCourses) || empty($selectedExamTypes) || empty($selectedFormGroups)) {
          // Show an error message or skip the query execution
          echo "Please select at least one course and one exam type.";
      } else {
        // Generate placeholders and bind values for courses
foreach ($selectedCourses as $key => $course) {
    $courseParams[":course$key"] = $course;
}
// Generate placeholders and bind values for exam types
foreach ($selectedExamTypes as $key => $examType) {
    $examTypeParams[":examType$key"] = $examType;
}
// Generate placeholders and bind values for form groups
foreach ($selectedFormGroups as $key => $formGroup) {
    $formGroupParams[":formGroup$key"] = $formGroup;
}

// Prepare the SQL query
$sql = 'SELECT c.name AS CourseName, fg.name AS FormGroupName, iac.type AS exam_type, AVG(e.attainmentValue) AS mean_score
    FROM gibbonCourse AS c
    INNER JOIN gibbonCourseClass AS cc ON c.gibbonCourseID = cc.gibbonCourseID
    INNER JOIN gibbonInternalAssessmentColumn AS iac ON cc.gibbonCourseClassID = iac.gibbonCourseClassID
    INNER JOIN gibbonInternalAssessmentEntry AS e ON iac.gibbonInternalAssessmentColumnID = e.gibbonInternalAssessmentColumnID
    WHERE sy.gibbonSchoolYearID = :gibbonSchoolYearID
    AND iac.type IN (' . implode(',', array_keys($examTypeParams)) . ')
    AND c.gibbonCourseID IN (' . implode(',', array_keys($courseParams)) . ')
    AND fg.gibbonFormGroupID IN (' . implode(',', array_keys($formGroupParams)) . ')
    GROUP BY c.name, fg.name, iac.type';


// Merge all parameters into one array
$data = array_merge([':gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')], $courseParams, $examTypeParams, $formGroupParams);

// Prepare and execute the statement
$result = $connection2->prepare($sql);
$result->execute($data);

        //test output $result shows empty array 
        echo  '<pre>'; print_r("Print of Results :"); echo '</pre>';
        //The print_r($results) shows empty array
        echo '<pre>'; print_r($result); echo '</pre>';
        // Fetch all rows
        $row = $result->fetchAll(PDO::FETCH_ASSOC); // Assuming $result stores your query results
        //test output
        echo '<pre>'; print_r($row); echo '</pre>';
        // Initialize an array to store the mean scores
        $meanScores = [];
        // Loop through the results and calculate the mean scores
        foreach ($rows as $row) {
          $courseNanome = $row['CourseName'];
          $examType = $row['exam_type'];
          $meanScore = $row['mean_score'];

          // Store the mean score for this course and exam type
          $meanScores[$courseName][$examType] = $meanScore;
        }

        // Calculate the mean deviation for each course
        $meanDeviations = [];
        foreach ($meanScores as $courseName => $scores) {
          if (isset($scores[$selectedExamTypes[0]]) && isset($scores[$selectedExamTypes[1]])) {
              $meanDeviations[$courseName] = $scores[$selectedExamTypes[1]] - $scores[$selectedExamTypes[0]];
          }
        }

            unset($row); // Unset reference to avoid data duplication
            usort($row, function ($a, $b) {
                return $b['mean_score'] <=> $a['mean_score'];
              });

              $formattedData = [];

            foreach ($row as $row) {
            $formattedData[] = [
                'CourseName' => $row['CourseName'],
                'FormGroupName' => $row['FormGroupName'],
                'exam_type' => $row['exam_type'],
                'mean_score' => number_format($row['mean_score'], 2),
                'standard_deviation' => number_format($row['standard_deviation'], 2),
            ];
            }

// Now you can use $formattedData to generate your HTML table with properly formatted cells
// Define the courses
$courses = array_unique(array_column($formattedData, 'CourseName'));
          // Start the table
echo '<br><table>';
echo '<thead>';
$courseMeanSpan = count($courses);
echo "<tr><td></td><td></td><th colspan=\"$courseMeanSpan\" style=\"text-align:center;\">COURSE MEAN SCORE</th><td></td></tr>";
echo '<tr><th>Form Groups</th><th>Exam Type</th>';
foreach ($courses as $course) {
    echo "<th>$course</th>";
}
echo '<th>Form Group Mean</th></tr>';
echo '</thead><tbody>';

// Loop through form groups
foreach ($formGroups as $formGroup) {
    // Get the data for this form group
    $formGroupData = array_filter($yearGroup, function ($row) use ($formGroup) {
        return $row['FormGroupName'] === $formGroup;
    });

    // Calculate the rowspan
    $rowspan = count($formGroupData);

    // Loop through exam types within the form group
    foreach ($formGroupData as $data) {
        echo '<tr>';

        // Add the form group name in the first row
        if ($rowspan > 0) {
            echo "<td rowspan=\"$rowspan\">$formGroup</td>";
            $rowspan = 0; // Reset rowspan after first row
        }

        // Add the exam type
        echo "<td>{$data['exam_type']}</td>";

        // Add the mean scores for each course
        foreach ($courses as $course) {
            $courseData = array_filter($data, function ($row) use ($course) {
                return $row['CourseName'] === $course;
            });

            if (!empty($courseData)) {
                $meanScore = number_format($courseData[0]['mean_score'], 2);
                echo "<td>$meanScore</td>";
            } else {
                echo "<td>-</td>"; // Display placeholder for missing data
            }
        }

        // Add the form group mean
        $formGroupMean = number_format(array_sum(array_column($data, 'mean_score')) / count($data), 2);
        echo "<td>$formGroupMean</td>";

        echo '</tr>';
    }
}

echo '</tbody></table>';             

  }

}
?>