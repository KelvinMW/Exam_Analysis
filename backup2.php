<?php
//This is the meanDeviation.php file that I am using
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

// ...
echo "---------------------------------------------------------------";
echo "<br>";
// ...
foreach ($courses as $courseID) {
    foreach ([$exam_type1, $exam_type2] as $examType) {
        $sql = "SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore
        FROM gibbonInternalAssessmentEntry AS entry
        INNER JOIN gibbonInternalAssessmentColumn AS `column`
        ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID
        WHERE `column`.gibbonCourseClassID = :courseID AND `column`.type = :examType
        AND entry.attainmentValue IS NOT NULL
        AND entry.attainmentValue != ''
        ";

        $params = ['courseID' => $courseID, 'examType' => $examType];

        // Debug output for SQL query
        echo "SQL Query for Course $courseID and Exam Type $examType: $sql\n";
        echo "Params: ";
        print_r($params);

        $result = $connection2->prepare($sql);
        $result->execute($params);
        echo "Number of Rows for Course $courseID and Exam Type $examType: " . $result->rowCount() . "\n";

        // Fetch all rows from the result set for debugging
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "SQL Result for Course $courseID and Exam Type $examType: ";
        print_r($rows);

        // Check if the result set is empty
        if (empty($rows)) {
            echo "Error: No data retrieved for Course $courseID and Exam Type $examType\n";
        } else {
            // Debug output for mean score
            echo "Mean Score for Course $courseID and Exam Type $examType: " . $rows[0]['meanScore'] . "\n";
            ${$examType . 'MeanScores'}[$courseID][$examType] = $rows[0]['meanScore'];
        }

        echo "\n";
    }
}
echo "<br>";
echo "---------------------------------------------------------------";
echo "<br>";
// Loop through selected form groups
foreach ($gibbonFormGroupID as $formGroupID) {
    foreach ([$exam_type1, $exam_type2] as $examType) {
        $sql = "SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore
                FROM gibbonInternalAssessmentEntry AS entry
                INNER JOIN gibbonInternalAssessmentColumn AS `column`
                ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID
                INNER JOIN gibbonCourseClassMap AS map
                ON `column`.gibbonCourseClassID = map.gibbonCourseClassID
                WHERE map.gibbonFormGroupID = :formGroupID
                AND `column`.type = :examType";

        $params = ['formGroupID' => $formGroupID, 'examType' => $examType];

        // Debug output for SQL query
        echo "SQL Query for Form Group $formGroupID and Exam Type $examType: $sql\n";
        echo "Params: ";
        print_r($params);

        $result = $connection2->prepare($sql);
        $result->execute($params);
        echo "Number of Rows for Form Group $formGroupID and Exam Type $examType: " . $result->rowCount() . "\n";

        // Fetch the mean score directly into the array
        ${$examType . 'MeanScores'}[$formGroupID][$examType] = $result->fetchColumn();

        // Debug output for mean score
        echo "Mean Score for Form Group $formGroupID and Exam Type $examType: ${$examType . 'MeanScores'}[$formGroupID][$examType]\n";
    }
}

// Arrays to store mean deviations for each course and form group
$courseMeanDeviations = [];
$formGroupMeanDeviations = [];
// Calculate mean deviations
foreach ($courses as $courseID) {
    $courseMeanDeviations[$courseID] = $exam_type2MeanScores[$courseID] - $exam_type1MeanScores[$courseID];
}
foreach ($gibbonFormGroupID as $formGroupID) {
    $formGroupMeanDeviations[$formGroupID] = $exam_type2MeanScores[$formGroupID] - $exam_type1MeanScores[$formGroupID];
}
// ...

// Debug output for SQL queries and mean scores
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th></th>';
echo '<th></th>';
echo '<th colspan="' . (count($courses) * 2) . '">COURSE MEAN SCORE</th>';
echo '<th></th>';
echo '</tr>';
echo '<tr>';
echo '<th>Form Group</th>';
echo '<th>Exam Type</th>';
foreach ($courses as $courseID) {
    echo '<th>' . $courseID . '</th>';
    echo '<th></th>'; // Add an empty column for each course
}
echo '<th>FORM GROUP MEAN</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($gibbonFormGroupID as $formGroupID) {
    echo '<tr>';
    echo '<td rowspan="2">Form Group ' . $formGroupID . '</td>';

    foreach ([$exam_type1, $exam_type2] as $examType) {
        echo '<td>' . $examType . '</td>';
        foreach ($courses as $courseID) {
            $meanScore = ${$examType . 'MeanScores'}[$courseID][$examType];

            // Debug output for mean score before table
            echo '<pre>';
            echo "Mean Score for Course $courseID and Exam Type $examType: $meanScore";
            echo '</pre>';

            echo '<td>' . $meanScore . '</td>';
        }
        echo '</tr><tr>';
    }
    // Additional row for mean deviations
    echo '<td colspan="2">MEAN DEVIATION</td>';
    foreach ($courses as $courseID) {
        $meanDeviation = $courseMeanDeviations[$courseID];

        // Debug output for mean deviation before table
        echo '<pre>';
        echo "Mean Deviation for Course $courseID: $meanDeviation";
        echo '</pre>';

        echo '<td>' . $meanDeviation . '</td>';
    }

    echo '<td>' . $formGroupMeanDeviations[$formGroupID] . '</td>';
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';

    }
}
/*
This is the debug output after form submit for this PHP file:
    ---------------------------------------------------------------
SQL Query for Course 00000033 and Exam Type Trial 1: SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore FROM gibbonInternalAssessmentEntry AS entry INNER JOIN gibbonInternalAssessmentColumn AS `column` ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID WHERE `column`.gibbonCourseClassID = :courseID AND `column`.type = :examType AND entry.attainmentValue IS NOT NULL AND entry.attainmentValue != '' Params: Array ( [courseID] => 00000033 [examType] => Trial 1 ) Number of Rows for Course 00000033 and Exam Type Trial 1: 1 SQL Result for Course 00000033 and Exam Type Trial 1: Array ( [0] => Array ( [meanScore] => ) ) Mean Score for Course 00000033 and Exam Type Trial 1: SQL Query for Course 00000033 and Exam Type Trial 2: SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore FROM gibbonInternalAssessmentEntry AS entry INNER JOIN gibbonInternalAssessmentColumn AS `column` ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID WHERE `column`.gibbonCourseClassID = :courseID AND `column`.type = :examType AND entry.attainmentValue IS NOT NULL AND entry.attainmentValue != '' Params: Array ( [courseID] => 00000033 [examType] => Trial 2 ) Number of Rows for Course 00000033 and Exam Type Trial 2: 1 SQL Result for Course 00000033 and Exam Type Trial 2: Array ( [0] => Array ( [meanScore] => ) ) Mean Score for Course 00000033 and Exam Type Trial 2: SQL Query for Course 00000035 and Exam Type Trial 1: SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore FROM gibbonInternalAssessmentEntry AS entry INNER JOIN gibbonInternalAssessmentColumn AS `column` ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID WHERE `column`.gibbonCourseClassID = :courseID AND `column`.type = :examType AND entry.attainmentValue IS NOT NULL AND entry.attainmentValue != '' Params: Array ( [courseID] => 00000035 [examType] => Trial 1 ) Number of Rows for Course 00000035 and Exam Type Trial 1: 1 SQL Result for Course 00000035 and Exam Type Trial 1: Array ( [0] => Array ( [meanScore] => ) ) Mean Score for Course 00000035 and Exam Type Trial 1: SQL Query for Course 00000035 and Exam Type Trial 2: SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore FROM gibbonInternalAssessmentEntry AS entry INNER JOIN gibbonInternalAssessmentColumn AS `column` ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID WHERE `column`.gibbonCourseClassID = :courseID AND `column`.type = :examType AND entry.attainmentValue IS NOT NULL AND entry.attainmentValue != '' Params: Array ( [courseID] => 00000035 [examType] => Trial 2 ) Number of Rows for Course 00000035 and Exam Type Trial 2: 1 SQL Result for Course 00000035 and Exam Type Trial 2: Array ( [0] => Array ( [meanScore] => ) ) Mean Score for Course 00000035 and Exam Type Trial 2: SQL Query for Course 00000034 and Exam Type Trial 1: SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore FROM gibbonInternalAssessmentEntry AS entry INNER JOIN gibbonInternalAssessmentColumn AS `column` ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID WHERE `column`.gibbonCourseClassID = :courseID AND `column`.type = :examType AND entry.attainmentValue IS NOT NULL AND entry.attainmentValue != '' Params: Array ( [courseID] => 00000034 [examType] => Trial 1 ) Number of Rows for Course 00000034 and Exam Type Trial 1: 1 SQL Result for Course 00000034 and Exam Type Trial 1: Array ( [0] => Array ( [meanScore] => ) ) Mean Score for Course 00000034 and Exam Type Trial 1: SQL Query for Course 00000034 and Exam Type Trial 2: SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore FROM gibbonInternalAssessmentEntry AS entry INNER JOIN gibbonInternalAssessmentColumn AS `column` ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID WHERE `column`.gibbonCourseClassID = :courseID AND `column`.type = :examType AND entry.attainmentValue IS NOT NULL AND entry.attainmentValue != '' Params: Array ( [courseID] => 00000034 [examType] => Trial 2 ) Number of Rows for Course 00000034 and Exam Type Trial 2: 1 SQL Result for Course 00000034 and Exam Type Trial 2: Array ( [0] => Array ( [meanScore] => ) ) Mean Score for Course 00000034 and Exam Type Trial 2:
---------------------------------------------------------------
SQL Query for Form Group 00021 and Exam Type Trial 1: SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore FROM gibbonInternalAssessmentEntry AS entry INNER JOIN gibbonInternalAssessmentColumn AS `column` ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID INNER JOIN gibbonCourseClassMap AS map ON `column`.gibbonCourseClassID = map.gibbonCourseClassID WHERE map.gibbonFormGroupID = :formGroupID AND `column`.type = :examType Params: Array ( [formGroupID] => 00021 [examType] => Trial 1 ) Number of Rows for Form Group 00021 and Exam Type Trial 1: 1 Mean Score for Form Group 00021 and Exam Type Trial 1: Array[00021][Trial 1] SQL Query for Form Group 00021 and Exam Type Trial 2: SELECT AVG(CAST(entry.attainmentValue AS DECIMAL(5,2))) as meanScore FROM gibbonInternalAssessmentEntry AS entry INNER JOIN gibbonInternalAssessmentColumn AS `column` ON entry.gibbonInternalAssessmentColumnID = `column`.gibbonInternalAssessmentColumnID INNER JOIN gibbonCourseClassMap AS map ON `column`.gibbonCourseClassID = map.gibbonCourseClassID WHERE map.gibbonFormGroupID = :formGroupID AND `column`.type = :examType Params: Array ( [formGroupID] => 00021 [examType] => Trial 2 ) Number of Rows for Form Group 00021 and Exam Type Trial 2: 1 Mean Score for Form Group 00021 and Exam Type Trial 2: Array[00021][Trial 2]
Mean Score for Course 00000033 and Exam Type Trial 1: 
Mean Score for Course 00000035 and Exam Type Trial 1: 
Mean Score for Course 00000034 and Exam Type Trial 1: 
Mean Score for Course 00000033 and Exam Type Trial 2: 
Mean Score for Course 00000035 and Exam Type Trial 2: 
Mean Score for Course 00000034 and Exam Type Trial 2: 
Mean Deviation for Course 00000033: 0
Mean Deviation for Course 00000035: 0
Mean Deviation for Course 00000034: 0
*/