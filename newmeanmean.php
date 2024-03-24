<?php
// Include the Gibbon class
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\Form;

require_once __DIR__ . '/moduleFunctions.php';

// Access Check
if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/meanDeviation.php') == false) {
    $page->addError('You do not have permission to access this page.');
} else {
  // Setup
  $settingGateway = $container->get(SettingGateway::class);
  $form = setupForm($pdo, $settingGateway, $session, $container);
  
    // Check for Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Collect POST data
        collectPostData();

        // Fetch Data
        $data = fetchSelectedFormData($pdo, $_POST['gibbonFormGroupID'], $_POST['courses'], $_POST['examType1'], $_POST['examType2']);
        error_log("Fetched Data: " . print_r($data, true));

        // Calculate and Display Results if Data is Available
        if (!empty($data)) {
            $meanScores = calculateMeanScores($pdo, $data, $examType1, $examType2);
            $deviations = calculateDeviations($meanScores, $examType1, $examType2);
            echo renderHtmlTable($meanScores, $deviations);
        } else {
            echo "No data available for the selected criteria.";
        }
    } else {
        // Display form if not submitted
        echo $form->getOutput();
    }
}