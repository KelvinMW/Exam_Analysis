<?php
//on click this file will export the table in analysis_view.php to spreadsheet
require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
//ToDo
//$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/department_course_class.php&gibbonCourseClassID=$gibbonFormGroupID";
//$highestAction = getHighestGroupedAction($guid, '/modules/Students/student_view_details.php', $connection2);

//    $form = Form::create('action',$session->get('absoluteURL').'/index.php?q=/modules/Exam Analysis/exam_analysis.php');
//    $form->setFactory(DatabaseFormFactory::create($pdo));
//    $form->addHiddenValue('q', $session->get('address'));


$data = json_decode(base64_decode($_POST['data']), true);
//create new spreadsheet object and set active worksheet
$spreadsheet = new Spreadsheet();
$worksheet = $spreadsheet->getActiveSheet();

//set values
$headerRow = ['Rank', 'Student Name'];
$headerRow = array_merge($headerRow, $data[$course], ['Total Score', 'Mean Score']);

$worksheet->fromArray([$headerRow], NULL, 'A1');

$rowIndex = 2;
$rank = 0;
foreach ($students as $student) {
    $rank = $rank + 1;
    $total_score = 0;
    $rowData = [$rank, $student];
    foreach ($courses as $course) {
        $attainment = isset($data[$student][$course]) ? $data[$student][$course] : '-';
        $total_score += floatval($attainment);
        $rowData[] = $attainment;
    }
    $average_score = $student_averages[$student];
    $rowData[] = $total_score;
    $rowData[] = round($average_score, 2);
    $worksheet->fromArray([$rowData], NULL, 'A' . $rowIndex);
    $rowIndex++;
}

//create a write object and save it to server

$writer = new Xlsx($spreadsheet);
$filename = 'scores_' . date('YmdHis') . '.xlsx';
$writer->save($filename);

//output the file to user and remove the file from server
header('Content-Disposition: attachment; filename="' . $filename . '"');
readfile($filename);
unlink($filename);

?>