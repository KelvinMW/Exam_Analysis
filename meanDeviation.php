<?php
require_once __DIR__ . '/moduleFunctions.php';

global $pdo, $connection2; // Assuming these are initialized elsewhere in your application

if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/meanDeviation.php') == false) {
    $page->addError('You do not have permission to access this page.');
} else {
    // Setup and display form
    $form = setupForm($pdo, $session);
    echo $form->getOutput();

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        list($selectedFormGroups, $selectedCourses, $examType1, $examType2) = collectPostData();

        // Fetch and process data only if all required fields are filled
        if (!empty($selectedFormGroups) && !empty($selectedCourses) && !empty($examType1) && !empty($examType2)) {
            $data = fetchData($connection2, $selectedFormGroups, $selectedCourses, $examType1, $examType2);
            $meanScores = calculateMeanScores($data);
            $deviations = calculateDeviations($meanScores, $examType1, $examType2);
            echo renderHtmlTable($meanScores, $deviations);
        } else {
            $page->addError("Please select at least one course and one exam type.");
        }
    }
}
