<?php
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


// Module includes
require_once __DIR__ . '/moduleFunctions.php';

//get alternative headers
$settingGateway = $container->get(SettingGateway::class);
$attainmentAlternativeName = $settingGateway->getSettingByScope('Markbook', 'attainmentAlternativeName');
$effortAlternativeName = $settingGateway->getSettingByScope('Markbook', 'effortAlternativeName');

if (isActionAccessible($guid, $connection2, '/modules/Exam Analysis/analysis_view.php') == false){
    $page->addError(__('You do not have access to this action.'));
}
else{
        // School Year Info
        $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
        $gibbonCourseID = $_GET['gibbonCourseID'] ?? null;
  /*
    $form = Form::create('examAnalysis', $session->get('absoluteURL').'/index.php','get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
//select analysis form
$row = $form->addRow();
$row->addLabel('examType', __('Select Exam Type'))
    ->description(__('Select the type of exam'));
$types = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
$row->addSelect('type')->fromString($types)->required()->placeholder();
// Get the form inputs from the user

*/
echo 'Kindly select the information of the exam you want to analyse :';
//<?php
//$form = Form::create('action', $_SESSION[$guid]['absoluteURL'].'/modules/'.$_SESSION[$guid]['module'].'/thing.php');
                
//$form->addHiddenValue('address', $_SESSION[$guid]['address']);

//$row = $form->addRow();
//    $row->addFooter();
 //   $row->addSubmit();

//echo $form->getOutput();

$form = Form::create('examAnalysis', $session->get('absoluteURL').'/analysis_view.php','get');
$form->setFactory(DatabaseFormFactory::create($pdo));
$form->addHiddenValue('address', $session->get('address'));
//select analysis form
    $row = $form->addRow();
    $row->addLabel('examType', __('Select Exam Type'))
        ->description(__('Select the type of exam'));
$types = $settingGateway->getSettingByScope('Formal Assessment', 'internalAssessmentTypes');
    $row->addSelect('type')->fromString($types)->required()->placeholder();

    $row = $form->addRow();
    $row->addLabel('gibbonFormGroupID', __('Form Group'));
    $row->addSelectFormGroup('gibbonFormGroupID', $session->get('gibbonSchoolYearID'))->required()->selected($gibbonFormGroupID)->placeholder();
//add courses
$data = array('gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
$sql = "SELECT gibbonCourseID as value, nameShort as name FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name";
$row = $form->addRow()->addClass('course bg-blue-100');
$row->addLabel('courses[]', __('Select Courses'));
$row->addSelect('courses[]')->fromQuery($pdo, $sql, $data)->selectMultiple()->setSize(6)->required()->selected($selected);

echo $form->getOutput();


}